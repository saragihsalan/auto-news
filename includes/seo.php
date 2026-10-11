<?php
/**
 * AUTO NEWS - SEO helpers
 * Sitemap, robots, schema.org, internal linking, cache.
 */

// ---- Build a URL for an article ----
function article_url($slug) {
    return BASE_URL . '/' . rawurlencode($slug);
}

// ---- Build sitemap.xml ----
function rebuild_sitemap() {
    try {
        $rows = db()->query("SELECT slug, published_at, updated_at FROM articles WHERE status = 'published' ORDER BY published_at DESC LIMIT 5000")->fetchAll();
    } catch (Exception $e) { return; }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // Homepage
    $xml .= "  <url><loc>" . htmlspecialchars(BASE_URL . '/') . "</loc><changefreq>hourly</changefreq><priority>1.0</priority></url>\n";

    foreach ($rows as $r) {
        $lastmod = date('c', strtotime($r['updated_at'] ?: $r['published_at']));
        $xml .= "  <url><loc>" . htmlspecialchars(article_url($r['slug'])) . "</loc><lastmod>$lastmod</lastmod><changefreq>daily</changefreq><priority>0.8</priority></url>\n";
    }

    $xml .= '</urlset>';
    @file_put_contents(ROOT_PATH . '/sitemap.xml', $xml);
}

// ---- Build robots.txt ----
function rebuild_robots() {
    $txt = "User-agent: *\n" .
           "Allow: /\n" .
           "Disallow: /admin/\n" .
           "Disallow: /cron/\n" .
           "Disallow: /includes/\n" .
           "\nSitemap: " . BASE_URL . "/sitemap.xml\n";
    @file_put_contents(ROOT_PATH . '/robots.txt', $txt);
}

// ---- Schema.org NewsArticle JSON-LD ----
function article_schema($article) {
    return json_pretty([
        '@context' => 'https://schema.org',
        '@type'    => 'NewsArticle',
        'headline' => $article['title'],
        'description' => $article['meta_desc'] ?: $article['excerpt'],
        'image'    => $article['image'] ? [$article['image']] : [],
        'datePublished' => $article['published_at'] ? date('c', strtotime($article['published_at'])) : '',
        'dateModified' => $article['updated_at'] ? date('c', strtotime($article['updated_at'])) : '',
        'author'   => ['@type' => 'Organization', 'name' => setting('site_name', APP_NAME)],
        'publisher'=> ['@type' => 'Organization', 'name' => setting('site_name', APP_NAME), 'logo' => ['@type' => 'ImageObject', 'url' => BASE_URL . '/assets/img/logo.svg']],
        'mainEntityOfPage' => article_url($article['slug']),
    ]);
}

// ---- Breadcrumb schema ----
function breadcrumb_schema($items) {
    $list = [];
    $i = 1;
    foreach ($items as $it) {
        $list[] = ['@type' => 'ListItem', 'position' => $i, 'name' => $it['name'], 'item' => $it['url']];
        $i++;
    }
    return json_pretty(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list]);
}

// ---- Organization / website schema (head) ----
function site_schema() {
    return json_pretty([
        '@context' => 'https://schema.org',
        '@type'    => 'WebSite',
        'name'     => setting('site_name', APP_NAME),
        'url'      => BASE_URL,
        'potentialAction' => ['@type' => 'SearchAction', 'target' => BASE_URL . '/?q={search_term_string}', 'query-input' => 'required name=search_term_string'],
    ]);
}

// ---- Find related articles (internal links) ----
function related_articles($sourceSite, $excludeId, $limit = 4) {
    $st = db()->prepare("SELECT id, slug, title, image FROM articles
        WHERE status='published' AND id != ? AND source_site = ?
        ORDER BY published_at DESC LIMIT " . (int)$limit);
    $st->execute([$excludeId, $sourceSite]);
    return $st->fetchAll();
}

// ---- Simple file cache ----
function cache_get($key, $ttl = 300) {
    $f = ROOT_PATH . '/cache/' . md5($key) . '.cache';
    if (file_exists($f) && (time() - filemtime($f) < $ttl)) return file_get_contents($f);
    return null;
}
function cache_set($key, $data) {
    @mkdir(ROOT_PATH . '/cache', 0777, true);
    @file_put_contents(ROOT_PATH . '/cache/' . md5($key) . '.cache', $data);
}
function cache_clear() {
    array_map('unlink', glob(ROOT_PATH . '/cache/*.cache'));
}

// ---- clearcache() alias used in cron ----
function clearcache() { cache_clear(); }
