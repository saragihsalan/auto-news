<?php
/**
 * AUTO NEWS - Frontend Router
 *
 * route '' / page/N           -> homepage (paginated)
 * route kategori/<slug>[/page/N] -> category listing (by source_site / portal)
 * route tag/<slug>[/page/N]    -> tag listing (by keywords)
 * route <slug>                 -> single article (SEO rich)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/seo.php';

$route = trim($_GET['route'] ?? '', '/');
$seg   = $route === '' ? [] : explode('/', $route);
$items = 12;  // per page

// ---- Parse: category / tag / page / article ----
if (($seg[0] ?? '') === 'kategori' && isset($seg[1])) {
    render_listing('category', $seg[1], $seg[3] ?? null, $items);
} elseif (($seg[0] ?? '') === 'tag' && isset($seg[1])) {
    render_listing('tag', $seg[1], isset($seg[2]) && $seg[2] === 'page' ? ($seg[3] ?? null) : null, $items);
} elseif (($seg[0] ?? '') === 'page' && isset($seg[1])) {
    render_listing('home', null, $seg[1], $items);
} elseif ($route !== '') {
    render_article_or_404($route);
} else {
    render_listing('home', null, null, $items);
}

// ==================== RENDER HELPERS ====================

function site_head($title, $desc, $url, $image, $type = 'article', $extraSchema = '', $extraLinks = '') {
    $siteName = setting('site_name', APP_NAME);
    $siteLang = setting('site_lang', DEFAULT_LANG);
    ?>
<!DOCTYPE html>
<html lang="<?= e($siteLang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link rel="canonical" href="<?= e($url) ?>">
<?= $extraLinks ?>
<meta name="description" content="<?= e($desc) ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">

<meta property="og:type" content="<?= e($type) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e($url) ?>">
<meta property="og:image" content="<?= e($image) ?>">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($desc) ?>">
<meta name="twitter:image" content="<?= e($image) ?>">

<link rel="icon" href="/assets/img/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
<script type="application/ld+json"><?= site_schema() ?></script>
<?php if ($extraSchema): ?>
<script type="application/ld+json"><?= $extraSchema ?></script>
<?php endif; ?>
</head>
<body>
<?php
}

function site_footer() {
    $siteName = setting('site_name', APP_NAME);
    $siteTag  = setting('site_tagline', '');
    ?>
<footer>
    <div class="container">
        <div class="foot">
            <div>
                <h4><?= e($siteName) ?></h4>
                <p><?= e($siteTag) ?></p>
                <p>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
            </div>
            <div>
                <h4>Kategori</h4>
                <ul class="foot-links">
                    <li><a href="/">Terkini</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
<?php
}

function site_nav($search = true) {
    $siteName = setting('site_name', APP_NAME);
    ?>
<nav class="nav"><div class="container nav-in">
    <a href="/" class="logo"><?= e($siteName) ?></a>
    <?php if ($search): ?><input type="search" class="search" placeholder="Cari berita..."><?php endif; ?>
</div></nav>
<?php
    if (function_exists('all_settings')) return; // noop
}

function card($a) { ?>
<article class="card">
    <a href="<?= e(article_url($a['slug'])) ?>">
        <?php if ($a['image']): ?><img src="<?= e($a['image']) ?>" loading="lazy" alt="<?= e($a['image_alt'] ?: $a['title']) ?>"><?php else: ?><div class="ph"></div><?php endif; ?>
    </a>
    <div class="card-body">
        <span class="cat"><a href="/kategori/<?= e(slugify($a['source_site'])) ?>"><?= e($a['source_site'] ?: 'Baca') ?></a></span>
        <h3><a href="<?= e(article_url($a['slug'])) ?>"><?= e($a['title']) ?></a></h3>
        <p><?= e(truncate($a['excerpt'] ?: strip_tags($a['body']), 120)) ?></p>
        <time><?= time_ago($a['published_at']) ?></time>
    </div>
</article>
<?php }

function render_article_or_404($slug) {
    $st = db()->prepare("SELECT * FROM articles WHERE slug = ? AND status = 'published' LIMIT 1");
    $st->execute([$slug]);
    $a = $st->fetch();

    if (!$a) {
        http_response_code(404);
        render_listing('home', null, null, 12, true);
        return;
    }

    db()->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([$a['id']]);

    $title = $a['meta_title'] ?: $a['title'];
    $desc  = $a['meta_desc'] ?: truncate($a['excerpt'] ?: strip_tags($a['body']), 158);
    $url   = article_url($a['slug']);
    $img   = $a['image'] ?: BASE_URL . '/assets/img/placeholder.svg';
    $catUrl = '/kategori/' . slugify($a['source_site']);

    $schema  = article_schema($a);
    if (!empty($a['faq_json'])) { $schema .= '\n' . $a['faq_json']; }
    $schema .= '\n' . breadcrumb_schema([
        ['name' => 'Beranda', 'url' => BASE_URL . '/'],
        ['name' => $a['source_site'] ?: 'Kategori', 'url' => BASE_URL . $catUrl],
        ['name' => $a['title'], 'url' => $url],
    ]);

    site_head($title, $desc, $url, $img, 'article', $schema);
    site_nav(true);
    ?>
<main class="container">
  <div class="breadcrumb"><a href="/">Beranda</a> / <a href="<?= e($catUrl) ?>"><?= e($a['source_site'] ?: 'Kategori') ?></a> / <span><?= e($a['title']) ?></span></div>
  <article class="article">
    <h1><?= e($a['title']) ?></h1>
    <div class="meta">
        <span><?= e($a['source_site'] ?: '') ?></span>
        <time><?= date('d M Y H:i', strtotime($a['published_at'])) ?></time>
        <span><?= number_format((int)$a['views']) ?> views</span>
    </div>
    <?php if ($a['image']): ?>
    <img src="<?= e($a['image']) ?>" alt="<?= e($a['image_alt'] ?: $a['title']) ?>" class="featured">
    <?php endif; ?>
    <div class="content"><?= $a['body'] ?></div>

    <?php if (!empty($a['keywords'])): ?>
    <div class="tags"><?php foreach (explode(',', $a['keywords']) as $t): ?><a href="/tag/<?= e(slugify($t)) ?>"><span><?= e(trim($t)) ?></span></a><?php endforeach; ?></div>
    <?php endif; ?>
  </article>

  <section class="related">
    <h2>Berita Terkait</h2>
    <div class="grid">
      <?php foreach (related_articles($a['source_site'], $a['id']) as $rel): card($rel); endforeach; ?>
    </div>
  </section>
</main>
<?php
    site_footer();
}

/**
 * Render a listing page: home / category <slug> / tag <slug>, with pagination.
 */
function render_listing($type, $slug = null, $page = null, $perPage = 12, $is404 = false) {
    $siteName = setting('site_name', APP_NAME);
    $siteTag  = setting('site_tagline', '');
    $pageNum  = max(1, (int)($page ?: 1));
    $offset   = ($pageNum - 1) * $perPage;

    // Build base query per type
    if ($type === 'category' && $slug) {
        $catName = ucwords(str_replace('-', ' ', $slug));
        $where = "source_site LIKE ? AND status='published'";
        $params = ['%' . str_replace('-', ' ', $slug) . '%'];
        $baseUrl = BASE_URL . '/kategori/' . rawurlencode($slug);
        $title = $catName . ' - ' . $siteName;
        $desc  = 'Kumpulan berita terbaru dari kategori ' . $catName . ' di ' . $siteName . '.';
    } elseif ($type === 'tag' && $slug) {
        $tagName = str_replace('-', ' ', $slug);
        $where = "keywords LIKE ? AND status='published'";
        $params = ['%' . $tagName . '%'];
        $baseUrl = BASE_URL . '/tag/' . rawurlencode($slug);
        $title = $tagName . ' - ' . $siteName;
        $desc  = 'Kumpulan berita terkait ' . $tagName . ' di ' . $siteName . '.';
    } else {
        // home
        $where = "status='published'";
        $params = [];
        $baseUrl = BASE_URL . '/';
        $title = $siteName . ' - ' . $siteTag;
        $desc  = 'Kumpulan berita terbaru ' . $siteTag . '. Informasi terkini, otomatis dan dioptimalkan untuk SEO.';
    }

    if ($is404) { $title = '404 - ' . $siteName; $desc = 'Halaman tidak ditemukan.'; http_response_code(404); }

    // Count total
    $c = db()->prepare("SELECT COUNT(*) c FROM articles WHERE " . $where);
    $c->execute($params);
    $total = (int)$c->fetch()['c'];
    $maxPage = max(1, (int)ceil($total / $perPage));
    $pageNum = min($pageNum, $maxPage);
    $offset = ($pageNum - 1) * $perPage;

    // Fetch rows
    $st = db()->prepare("SELECT * FROM articles WHERE " . $where . " ORDER BY published_at DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
    $st->execute($params);
    $rows = $st->fetchAll();

    // SEO extra links (rel prev/next)
    $extraLinks = '';
    if ($pageNum > 1) $extraLinks .= '<link rel="prev" href="' . e(page_url($baseUrl, $pageNum - 1)) . '">';
    if ($pageNum < $maxPage) $extraLinks .= '<link rel="next" href="' . e(page_url($baseUrl, $pageNum + 1)) . '">';

    // Schema for listing
    $listingSchema = '';
    if ($type !== 'home' && !$is404 && $slug) {
        $listingSchema = json_pretty([
            '@context' => 'https://schema.org',
            '@type'    => 'CollectionPage',
            'name'     => $title,
            'description' => $desc,
            'url'      => $baseUrl,
        ]);
    }

    site_head($title, $desc, $baseUrl, BASE_URL . '/assets/img/placeholder.svg', 'website', $listingSchema, $extraLinks);
    site_nav(true);
    ?>
<main class="container">
<?php if ($is404): ?>
    <div class="notfound"><h1>404</h1><p>Halaman tidak ditemukan.</p><a href="/">Kembali ke Beranda</a></div>
<?php else: ?>

  <div class="breadcrumb"><a href="/">Beranda</a><?php if ($type !== 'home'): ?> / <span><?= e(ucwords(str_replace('-',' ',(string)$slug))) ?></span><?php endif; ?></div>

  <?php if ($type === 'home' && !$is404): ?>
    <?php $hero = db()->query("SELECT * FROM articles WHERE status='published' AND is_featured=1 ORDER BY published_at DESC LIMIT 1")->fetch();
          if (!$hero) $hero = db()->query("SELECT * FROM articles WHERE status='published' ORDER BY published_at DESC LIMIT 1")->fetch(); ?>
    <?php if ($hero && $pageNum === 1): ?>
    <section class="hero">
      <a href="<?= e(article_url($hero['slug'])) ?>">
        <?php if ($hero['image']): ?><img src="<?= e($hero['image']) ?>" alt="<?= e($hero['title']) ?>"><?php endif; ?>
        <div class="hero-overlay"><span class="cat">Utama</span><h1><?= e($hero['title']) ?></h1></div>
      </a>
    </section>
    <?php endif; ?>
  <?php endif; ?>

  <section class="latest">
    <div class="section-title"><h2><?= $is404 ? '' : (($type === 'home') ? 'Berita Terbaru' : 'Kategori: ' . e(ucwords(str_replace('-',' ',(string)$slug)))) ?></h2></div>
    <div class="grid">
      <?php if ($rows): foreach ($rows as $a): card($a); endforeach; else: ?>
        <p class="empty">Belum ada artikel di halaman ini.</p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($maxPage > 1): ?>
  <nav class="pagination">
    <?php if ($pageNum > 1): ?><a class="page-link" href="<?= e(page_url($baseUrl, $pageNum - 1)) ?>">&laquo; Sebelumnya</a><?php endif; ?>
    <?php for ($p = 1; $p <= $maxPage; $p++): ?>
      <?php if ($p === $pageNum): ?><span class="page-current"><?= $p ?></span>
      <?php else: ?><a class="page-link" href="<?= e(page_url($baseUrl, $p)) ?>"><?= $p ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pageNum < $maxPage): ?><a class="page-link" href="<?= e(page_url($baseUrl, $pageNum + 1)) ?>">Berikutnya &raquo;</a><?php endif; ?>
  </nav>
  <?php endif; ?>

<?php endif; ?>
</main>
<?php
    site_footer();
}

/**
 * Build a paginated URL from a base URL.
 */
function page_url($baseUrl, $pageNum) {
    if ($pageNum <= 1) return $baseUrl;
    // home:  /page/2;  category/tag:  /kategori/x/page/2
    if ($baseUrl === BASE_URL . '/') return BASE_URL . '/page/' . $pageNum;
    return rtrim($baseUrl, '/') . '/page/' . $pageNum;
}
