<?php
/**
 * AUTO NEWS - Admin shared layout
 * Provides admin_header() and admin_footer().
 */
if (!function_exists('admin_header')) {
    function admin_header($title) {
        ?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> - Admin</title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-wrap">
  <aside class="sidebar">
    <div class="brand">🤖 Auto News</div>
    <nav>
      <a href="index.php">📊 Dashboard</a>
      <a href="articles.php">📰 Artikel</a>
      <a href="sources.php">📡 RSS Sources</a>
      <a href="settings.php">⚙️ Pengaturan</a>
      <a href="run.php">▶️ Jalankan Sekarang</a>
      <a href="index.php?logout=1">🚪 Keluar</a>
    </nav>
  </aside>
  <main class="main">
    <div class="topbar"><h1><?= e($title) ?></h1></div>
<?php
    }
}

if (!function_exists('admin_footer')) {
    function admin_footer() {
        ?></main></div></body></html><?php
    }
}
