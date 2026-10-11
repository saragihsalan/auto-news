<?php
/**
 * AUTO NEWS - Cron Runner (set and forget)
 *
 * - Fetches active RSS sources
 * - Dedups new items
 * - Rewrites each via AI
 * - Saves as published article
 * - Rebuilds sitemap
 *
 * Usage (cPanel cron):  php /home/USER/public_html/cron/run.php  (every 5 min)
 * Or via web:  https://site.com/cron/run.php?secret=YOUR_SECRET
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/rss_fetcher.php';
require_once dirname(__DIR__) . '/includes/ai_engine.php';
require_once dirname(__DIR__) . '/includes/seo.php';

// Prevent unauthorized web access (optional)
// - CLI: always allowed
// - Web: allowed only if ?secret= matches, OR admin-triggered from the admin panel
$secret = setting('cron_secret', '');
$adminTrigger = isset($_SERVER['CRON_ADMIN_TRIGGER']) && $_SERVER['CRON_ADMIN_TRIGGER'] === '1';
if (php_sapi_name() !== 'cli' && !$adminTrigger && $secret && (($_GET['secret'] ?? '') !== $secret)) {
    http_response_code(403);
    die('Forbidden');
}

// Optional lock to avoid overlap
$lockFile = sys_get_temp_dir() . '/auto_news_cron.lock';
$fh = fopen($lockFile, 'c');
if (!flock($fh, LOCK_EX | LOCK_NB)) { die("Already running\n"); }

$limit = (int)setting('batch_limit', '5');  // max new articles per run
$done = 0;

// ---- 1. Pull active sources ----
$sources = db()->query('SELECT * FROM rss_sources WHERE active = 1')->fetchAll();

foreach ($sources as $source) {
    // per-source throttle
    $interval = (int)$source['fetch_interval_min'];
    if ($source['last_fetched_at'] && (time() - strtotime($source['last_fetched_at'])) < ($interval * 60)) {
        continue;
    }

    $items = fetch_rss_items($source['url'], $source['id'], $source['source_site'], $source['language']);
    $new = collect_new_items($items);

    db()->prepare('UPDATE rss_sources SET last_fetched_at = NOW() WHERE id = ?')->execute([$source['id']]);

    echo "[{$source['name']}] fetched " . count($items) . " items, " . count($new) . " new\n";

    foreach ($new as $item) {
        if ($done >= $limit) break 2;

        // ---- Insert placeholder draft first (so guid unique is reserved) ----
        $title = $item['title'];
        $slug = slugify($title) . '-' . substr(md5($guid = $item['guid']), 0, 5);
        $articleId = insert_article($item, $title, $slug);

        // ---- AI rewrite ----
        echo "  rewrite: " . mb_substr($title, 0, 60) . " ...\n";
        $rewritten = ai_rewrite($item);

        if (!$rewritten) {
            log_event('error', $source['id'], $articleId, 'AI rewrite failed, keeping draft');
            $done++;
            continue;
        }

        // ---- Update with rewritten content ----
        update_article_rewritten($articleId, $rewritten, $item);
        $done++;
        usleep(500000); // 0.5s between AI calls
    }
}

// ---- Rebuild sitemap + robots ----
rebuild_sitemap();
clearcache();

echo "Done. Processed $done new article(s).\n";

// ===== Helper: insert draft =====
function insert_article($item, $title, $slug) {
    $stmt = db()->prepare(
        "INSERT INTO articles (guid, source_id, source_site, category, title, slug, lang, image, original_url, status)
         VALUES (?,?,?,?,?,?,?,?,?,'draft')"
    );
    $stmt->execute([
        $item['guid'], $item['source_id'], $item['source_site'], $item['category'] ?? null, $title, $slug,
        $item['lang'], $item['image'], $item['link'],
    ]);
    return db()->lastInsertId();
}

// ===== Helper: apply rewrite =====
function update_article_rewritten($articleId, $r, $item) {
    $faq = build_faq_json($r['faqs'] ?? []);
    $body = body_to_html($r['body'] ?? '');
    $slug = slugify($r['title'] ?? $item['title']) . '-' . substr(md5($item['guid']), 0, 5);

    // Taxonomy category: prefer AI-assigned, else RSS, else existing value
    $category = trim((string)($r['category'] ?? '')) ?: trim((string)($item['category'] ?? '')) ?: null;
    $category = $category ? mb_substr($category, 0, 120) : null;

    $stmt = db()->prepare(
        "UPDATE articles SET
            title = ?, slug = ?, excerpt = ?, body = ?,
            category = ?,
            meta_title = ?, meta_desc = ?, keywords = ?, tags = ?,
            image_alt = ?, faq_json = ?,
            status = 'published', published_at = NOW()
         WHERE id = ?"
    );
    $stmt->execute([
        $r['title'], $slug, $r['excerpt'] ?? '', $body,
        $category,
        $r['meta_title'] ?? $r['title'], $r['meta_description'] ?? '', trim($r['keywords'] ?? '', ", " ),
        is_array($r['tags'] ?? null) ? implode(',', $r['tags']) : ($r['tags'] ?? ''),
        $r['image_alt'] ?? '', $faq, $articleId,
    ]);
    log_event('ai_rewrite', $item['source_id'], $articleId, 'Rewritten & published');
}
