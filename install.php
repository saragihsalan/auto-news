<?php
/**
 * AUTO NEWS - Installer (Full Wizard)
 *
 * This file is a wizard: it lets you enter / verify database credentials
 * in the browser WITHOUT editing includes/config.php manually.
 *
 * How it works:
 *   1. On first open, it reads includes/config.php and tries to CONNECT
 *      using a self-contained PDO connection (so it never dies like db()
 *      does when config.php has bad credentials).
 *   2. If config.php already contains valid credentials -> it runs the
 *      import directly and shows success.
 *   3. Otherwise it shows a form asking for DB_HOST / DB_NAME / DB_USER /
 *      DB_PASS. On submit, it tests the connection, writes the credentials
 *      into includes/config.php, then imports the schema + seeds defaults.
 *
 * SECURITY: This file writes credentials to includes/config.php ONLY on the
 * server. includes/config.php is gitignored — never commit real credentials.
 *
 * After successful install: DELETE THIS FILE.
 */

// ---------------------------------------------------------------
// No require of includes/config.php here on purpose. We define the
// minimal constants we need (ROOT_PATH, BASE_URL) ourselves so the
// wizard can fully control the DB connection and never crash.
// ---------------------------------------------------------------
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__);
if (!defined('BASE_URL')) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', ($https ? 'https' : 'http') . '://' . $host);
}

// ---- Self-contained DB connect (never dies; returns PDO or null) ----
function install_connect($host, $name, $user, $pass) {
    try {
        return new PDO(
            'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        return null;
    }
}

// ---- Read DB credentials currently stored inside includes/config.php ----
function install_read_config_creds($configPath) {
    $defaults = ['DB_HOST' => '', 'DB_NAME' => '', 'DB_USER' => '', 'DB_PASS' => ''];
    if (!is_file($configPath)) return $defaults;
    $src = (string)@file_get_contents($configPath);
    foreach (array_keys($defaults) as $const) {
        if (preg_match("/define\s*\(\s*'" . preg_quote($const, '/') . "'\s*,\s*'([^']*)'\s*\)/", $src, $m)) {
            $defaults[$const] = $m[1];
        } elseif (preg_match("/define\s*\(\s*'" . preg_quote($const, '/') . "'\s*,\s*\"([^\"]*)\"\s*\)/", $src, $m)) {
            $defaults[$const] = $m[1];
        }
    }
    return $defaults;
}

// ---- Write DB credentials into includes/config.php (in-place replace) ----
function install_write_config_creds($configPath, $creds) {
    if (!is_file($configPath)) {
        // config.php is gitignored, so a fresh clone won't have it.
        // Fall back to the example template we ship in the repo.
        $sample = null;
        foreach (['config.example.php', 'config.sample.php'] as $tpl) {
            $candidate = dirname($configPath) . '/' . $tpl;
            if (is_file($candidate)) { $sample = $candidate; break; }
        }
        if ($sample === null) {
            return 'includes/config.php tidak ditemukan dan tidak ada template config di folder includes. Unggah includes/ dari repo terlebih dahulu.';
        }
        if (!@copy($sample, $configPath)) {
            return 'Tidak dapat membuat ' . $configPath . ' dari template. Periksa izin folder includes (harus writable).';
        }
    }
    $src = (string)@file_get_contents($configPath);
    $map = [
        'DB_HOST' => $creds['DB_HOST'],
        'DB_NAME' => $creds['DB_NAME'],
        'DB_USER' => $creds['DB_USER'],
        'DB_PASS' => $creds['DB_PASS'],
    ];
    foreach ($map as $const => $val) {
        $val = addslashes($val);
        $pattern = "/define\s*\(\s*'" . preg_quote($const, '/') . "'\s*,\s*'([^']*)'\s*\)/";
        $replacement = "define('{$const}', '{$val}')";
        $new = preg_replace($pattern, $replacement, $src, 1);
        if ($new === null || $new === $src) {
            $new = preg_replace(
                "/define\s*\(\s*'" . preg_quote($const, '/') . "'\s*,\s*\"([^\"]*)\"\s*\)/",
                "define('{$const}', '{$val}')",
                $src,
                1
            );
        }
        $src = ($new !== null) ? $new : $src;
    }
    if (@file_put_contents($configPath, $src) === false) {
        return 'Gagal menulis ' . $configPath . '. Periksa izin file (harus writable).';
    }
    return null; // success
}

// ---- Import database.sql contents ----
function install_import_schema(PDO $pdo, $sqlFile) {
    $sql = (string)@file_get_contents($sqlFile);
    if ($sql === '') return 'database.sql tidak ditemukan atau kosong.';
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/^\s*\/\* .*? \*\/\s*$/ms', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if ($stmt === '' || stripos($stmt, 'SET NAMES') !== false) continue;
        try { $pdo->exec($stmt); }
        catch (Exception $e) { /* table may exist */ }
    }
    return null;
}

// ---------------------------------------------------------------
// Routes
// ---------------------------------------------------------------
$configPath = __DIR__ . '/includes/config.php';
$sqlFile    = __DIR__ . '/database.sql';
$error      = null;
$done       = false;
$current    = install_read_config_creds($configPath);

$submitted = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($submitted) {
    $creds = [
        'DB_HOST' => trim($_POST['db_host'] ?? ''),
        'DB_NAME' => trim($_POST['db_name'] ?? ''),
        'DB_USER' => trim($_POST['db_user'] ?? ''),
        'DB_PASS' => (string)($_POST['db_pass'] ?? ''),
    ];
    $current = $creds;

    if ($creds['DB_HOST'] === '' || $creds['DB_NAME'] === '' || $creds['DB_USER'] === '') {
        $error = 'DB Host, Nama Database, dan DB User wajib diisi.';
    } else {
        $pdo = install_connect($creds['DB_HOST'], $creds['DB_NAME'], $creds['DB_USER'], $creds['DB_PASS']);
        if (!$pdo) {
            $error = 'Koneksi database GAGAL. Periksa kembali host/name/user/pass (di cPanel biasanya: DB_HOST=localhost, DB_NAME=uXXXXXXX_auto_news, DB_USER=uXXXXXXX_admin).';
        } else {
            $err = install_write_config_creds($configPath, $creds);
            if ($err !== null) {
                $error = $err;
            } else {
                $err2 = install_import_schema($pdo, $sqlFile);
                if ($err2 !== null) {
                    $error = $err2;
                } else {
                    // Seed defaults
                    $count = 0;
                    try { $count = (int)$pdo->query('SELECT COUNT(*) c FROM admins')->fetch()['c']; } catch (Exception $e) {}
                    if ($count == 0) {
                        $pass = password_hash('admin123', PASSWORD_DEFAULT);
                        $pdo->prepare('INSERT INTO admins (username, password, role) VALUES (?,?,?)')->execute(['admin', $pass, 'admin']);
                    }
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
                    $ins = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (:k, :v) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
                    foreach ($defaults as $k => $v) { try { $ins->execute([':k' => $k, ':v' => $v]); } catch (Exception $e) {} }

                    $feeds = [
                        ['CNN Indonesia', 'https://www.cnnindonesia.com/rss/1', 'cnnindonesia', 'id', 30],
                        ['Detik News',    'https://www.detik.com/rss/index.xml', 'detik', 'id', 30],
                        ['Kompas',        'https://www.kompas.com/rss', 'kompas', 'id', 30],
                        ['Antara',        'https://www.antaranews.com/rss', 'antara', 'id', 30],
                    ];
                    $n = 0;
                    try { $n = (int)$pdo->query('SELECT COUNT(*) c FROM rss_sources')->fetch()['c']; } catch (Exception $e) {}
                    if ($n == 0) {
                        $st = $pdo->prepare('INSERT INTO rss_sources (name, url, source_site, language, active, fetch_interval_min) VALUES (?,?,?,?,1,?)');
                        foreach ($feeds as $f) { try { $st->execute($f); } catch (Exception $e) {} }
                    }

                    // Now that config has valid creds, we can require it + rebuild robots
                    try {
                        require_once __DIR__ . '/includes/config.php';
                        require_once __DIR__ . '/includes/seo.php';
                        if (function_exists('rebuild_robots')) rebuild_robots();
                    } catch (Exception $e) { /* non-fatal */ }

                    $done = true;
                }
            }
        }
    }
} elseif (is_file($configPath)) {
    // No POST: test existing config.php credentials
    if ($current['DB_HOST'] !== '' && $current['DB_NAME'] !== '' && $current['DB_USER'] !== '') {
        $pdo = install_connect($current['DB_HOST'], $current['DB_NAME'], $current['DB_USER'], $current['DB_PASS']);
        if ($pdo) {
            $err2 = install_import_schema($pdo, $sqlFile);
            if ($err2 === null) {
                try { $count = (int)$pdo->query('SELECT COUNT(*) c FROM admins')->fetch()['c']; } catch (Exception $e) {}
                if ($count == 0) {
                    $pass = password_hash('admin123', PASSWORD_DEFAULT);
                    $pdo->prepare('INSERT INTO admins (username, password, role) VALUES (?,?,?)')->execute(['admin', $pass, 'admin']);
                }
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
                $ins = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (:k, :v) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
                foreach ($defaults as $k => $v) { try { $ins->execute([':k' => $k, ':v' => $v]); } catch (Exception $e) {} }

                $feeds = [
                    ['CNN Indonesia', 'https://www.cnnindonesia.com/rss/1', 'cnnindonesia', 'id', 30],
                    ['Detik News',    'https://www.detik.com/rss/index.xml', 'detik', 'id', 30],
                    ['Kompas',        'https://www.kompas.com/rss', 'kompas', 'id', 30],
                    ['Antara',        'https://www.antaranews.com/rss', 'antara', 'id', 30],
                ];
                $n = 0;
                try { $n = (int)$pdo->query('SELECT COUNT(*) c FROM rss_sources')->fetch()['c']; } catch (Exception $e) {}
                if ($n == 0) {
                    $st = $pdo->prepare('INSERT INTO rss_sources (name, url, source_site, language, active, fetch_interval_min) VALUES (?,?,?,?,1,?)');
                    foreach ($feeds as $f) { try { $st->execute($f); } catch (Exception $e) {} }
                }
                try {
                    require_once __DIR__ . '/includes/config.php';
                    require_once __DIR__ . '/includes/seo.php';
                    if (function_exists('rebuild_robots')) rebuild_robots();
                } catch (Exception $e) { /* non-fatal */ }
                $done = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $done ? 'Instalasi Selesai' : 'Installer - AUTO NEWS' ?></title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding: 40px 16px; max-width: 620px; margin: auto; color: #1f2937; background: #f9fafb; }
    .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; box-shadow: 0 4px 16px rgba(0,0,0,.05); }
    h1 { font-size: 22px; margin: 0 0 6px; }
    h2 { font-size: 18px; margin: 0 0 14px; }
    p.sub { color: #6b7280; margin: 0 0 20px; font-size: 14px; }
    label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 4px; }
    input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
    input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
    button { margin-top: 20px; width: 100%; padding: 12px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
    button:hover { background: #1d4ed8; }
    .hint { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin: 14px 0; color: #1e40af; }
    .err { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin: 14px 0; color: #b91c1c; }
    .ok { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin: 14px 0; color: #047857; }
    code { background: #f3f4f6; padding: 2px 5px; border-radius: 5px; font-size: 12px; }
    .creds { background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 8px; padding: 8px 14px; margin: 12px 0; }
    a.link { color: #2563eb; font-weight: 600; text-decoration: none; }
    a.link:hover { text-decoration: underline; }
    footer { text-align: center; margin-top: 20px; color: #9ca3af; font-size: 12px; }
  </style>
</head>
<body>
<?php if ($done): ?>
  <div class="card">
    <h1>✅ Instalasi Selesai</h1>
    <p class="sub">Database & pengaturan awal berhasil dibuat.</p>
    <div class="ok">Koneksi database berhasil dan skema (<code>database.sql</code>) sudah diimport.</div>
    <div class="creds">
      <b>Login Admin:</b><br>
      Username: <code>admin</code><br>
      Password: <code>admin123</code><br>
      <small>(Segera ganti password setelah login!)</small>
    </div>
    <p><b>Keamanan:</b> <strong style="color:#b91c1c">Hapus file <code>install.php</code> sekarang juga!</strong></p>
    <div class="hint">
      Kemudian atur <code>AI Endpoint</code> & API key di <b>Admin Panel → Settings</b>
      agar artikel bisa otomatis ditulis oleh AI.
    </div>
    <a class="link" href="/admin/">Buka Admin Panel &rarr;</a>
  </div>
<?php else: ?>
  <div class="card">
    <h1>🛠️ Installer AUTO NEWS</h1>
    <p class="sub">Isi kredensial database kamu di bawah ini. Kredensial akan disimpan langsung ke <code>includes/config.php</code> di server.</p>

    <?php if ($error): ?><div class="err">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!$error && $current['DB_HOST'] !== ''): ?><div class="hint">ℹ️ <code>includes/config.php</code> sudah berisi kredensial, tapi koneksi gagal. Perbaiki dan submit ulang.</div><?php endif; ?>

    <form method="post" autocomplete="off">
      <label for="db_host">DB Host</label>
      <input id="db_host" name="db_host" value="<?= htmlspecialchars($current['DB_HOST'] ?: 'localhost') ?>" placeholder="localhost" required>

      <label for="db_name">Nama Database</label>
      <input id="db_name" name="db_name" value="<?= htmlspecialchars($current['DB_NAME']) ?>" placeholder="uXXXXXXX_auto_news" required>

      <label for="db_user">DB User</label>
      <input id="db_user" name="db_user" value="<?= htmlspecialchars($current['DB_USER']) ?>" placeholder="uXXXXXXX_admin" required>

      <label for="db_pass">DB Password</label>
      <input id="db_pass" name="db_pass" type="password" value="" placeholder="••••••••" autocomplete="new-password">

      <button type="submit">Pasang Sekarang &rarr;</button>
    </form>

    <div class="hint">
      💡 <b>Di cPanel (Hostinger):</b> buka <code>MySQL Databases</code> lalu salin nama DB yang biasanya berformat <code>uXXXXXXX_auto_news</code>, user <code>uXXXXXXX_admin</code>, dan set password di sana. Host biasanya <code>localhost</code>.
    </div>
  </div>
<?php endif; ?>
<footer>AUTO NEWS &middot; Set and Forget AI News Rewriter &middot; v<?= (defined('APP_VERSION') ? APP_VERSION : '1.0.0') ?></footer>
</body>
</html>