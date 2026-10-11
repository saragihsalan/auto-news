<?php
/**
 * AUTO NEWS - Admin Dashboard
 */
require_once __DIR__ . '/auth.php';

// ---- Stats ----
$total   = db()->query('SELECT COUNT(*) c FROM articles')->fetch()['c'];
$pub     = db()->query("SELECT COUNT(*) c FROM articles WHERE status='published'")->fetch()['c'];
$draft   = db()->query("SELECT COUNT(*) c FROM articles WHERE status='draft'")->fetch()['c'];
$views   = db()->query('SELECT COALESCE(SUM(views),0) v FROM articles')->fetch()['v'];
$sources = db()->query('SELECT COUNT(*) c FROM rss_sources')->fetch()['c'];
$errs    = db()->query("SELECT COUNT(*) c FROM scrape_logs WHERE type='error'")->fetch()['c'];
$latest  = db()->query("SELECT * FROM articles ORDER BY created_at DESC LIMIT 8")->fetchAll();

require_once __DIR__ . '/layout.php';
admin_header('Dashboard');

<div class="cards">
    <div class="stat"><span><?= $total ?></span><label>Total Artikel</label></div>
    <div class="stat"><span><?= $pub ?></span><label>Published</label></div>
    <div class="stat"><span><?= $draft ?></span><label>Draft</label></div>
    <div class="stat"><span><?= number_format($views) ?></span><label>Total Views</label></div>
    <div class="stat"><span><?= $sources ?></span><label>RSS Sumber</label></div>
    <div class="stat"><span><?= $errs ?></span><label>Error Log</label></div>
</div>

<table class="table">
  <thead><tr><th>Title</th><th>Status</th><th>Sumber</th><th>Views</th><th>Waktu</th></tr></thead>
  <tbody>
  <?php foreach ($latest as $a): ?>
    <tr>
      <td><a href="../<?= e($a['slug']) ?>"><?= e(mb_substr($a['title'],0,60)) ?></a></td>
      <td><span class="badge b-<?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
      <td><?= e($a['source_site']) ?></td>
      <td><?= number_format((int)$a['views']) ?></td>
      <td><?= time_ago($a['created_at']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php admin_footer();
