<?php
/**
 * AUTO NEWS - Installer
 * Run once from browser: https://site.com/install.php  -> then delete this file.
 *
 * Creates tables, default admin, and starter settings.
 */

require_once __DIR__ . '/includes/config.php';

// --- Import schema (split on ; safely) --- use the bundled file
$sqlFile = __DIR__ . '/database.sql';
$sql = file_get_contents($sqlFile);

// Remove comment lines and split
$sql = preg_replace('/^--.*$/m', '', $sql);
$sql = preg_replace('/^\s*\/\* .*? \*\/\s*$/ms', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $stmt) {
    if ($stmt === '' || stripos($stmt, 'SET NAMES') !== false) continue;
    try {
        db()->exec($stmt);
    } catch (Exception $e) {
        // table may already exist; ignore
    }
}

// --- Default admin ---
$count = db()->query('SELECT COUNT(*) c FROM admins')->fetch()['c'];
if ($count == 0) {
    $pass = password_hash('admin123', PASSWORD_DEFAULT);
    db()->prepare('INSERT INTO admins (username, password, role) VALUES (?,?,?)')->execute(['admin', $pass, 'admin']);
}

// --- Default settings ---
$defaults = [
    'site_name'      => 'AUTO NEWS',
    'site_tagline'   => 'Berita Terkini, Otomatis & SEO',
    'site_lang'      => 'id',
    'ai_endpoint'    => 'http://localhost:20128',
    'ai_api_key'     => '',
    'ai_model'       => 'openai/gpt-4o-mini',
    'ai_temperature' => '0.7',
    'batch_limit'    => '5',
    'cron_secret'    => bin2hex(random_bytes(8)),
];

foreach ($defaults as $k => $v) {
    try { set_setting($k, $v); } catch (Exception $e) {}
}

// --- Default RSS sources (ID portals) ---
$feeds = [
    ['CNN Indonesia', 'https://www.cnnindonesia.com/rss/1', 'cnnindonesia', 'id', 30],
    ['Detik News',    'https://www.detik.com/rss/index.xml', 'detik', 'id', 30],
    ['Kompas',        'https://www.kompas.com/rss', 'kompas', 'id', 30],
    ['Antara',        'https://www.antaranews.com/rss', 'antara', 'id', 30],
];

$n = db()->query('SELECT COUNT(*) c FROM rss_sources')->fetch()['c'];
if ($n == 0) {
    $st = db()->prepare('INSERT INTO rss_sources (name, url, source_site, language, active, fetch_interval_min) VALUES (?,?,?,?,1,?)');
    foreach ($feeds as $f) $st->execute($f);
}

// Rebuild robots
require_once __DIR__ . '/includes/seo.php';
rebuild_robots();

?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Install - AUTO NEWS</title></head>
<body style="font-family:system-ui;padding:40px;max-width:600px;margin:auto">
  <h2>✅ Instalasi Selesai</h2>
  <p>Database & pengaturan awal berhasil dibuat.</p>
  <p><b>Login Admin:</b> admin / admin123</p>
  <p><b>Keamanan:</b> Hapus file <code>install.php</code> sekarang juga!</p>
  <p><a href="/admin/">Buka Admin Panel &rarr;</a></p>
</body>
</html>
