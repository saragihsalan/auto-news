<?php
/**
 * AUTO NEWS - Core Config (TEMPLATE)
 *
 * Copy this file to `config.php` and fill in your own values.
 * `config.php` is git-ignored so your credentials never reach the repo.
 *
 *   cp includes/config.example.php includes/config.php
 */

define('APP_NAME', 'AUTO NEWS');
define('APP_VERSION', '1.0.0');

// ---- Database credentials (edit/import via installer) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'auto_news');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Base URL (auto-detect, or set manually e.g. https://news.example.com) ----
if (!defined('BASE_URL')) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443;
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', ($https ? 'https' : 'http') . '://' . $host);
}

// ---- Timezone & language defaults ----
define('DEFAULT_LANG', 'id');
define('TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set(TIMEZONE);

// ---- Session security ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Error display (turn off in production) ----
define('DEBUG', false);
if (DEBUG) { error_reporting(E_ALL); ini_set('display_errors', '1'); }
else { error_reporting(0); ini_set('display_errors', '0'); }

// ---- Root path for includes ----
define('ROOT_PATH', dirname(__DIR__));

// ---- Autoload helpers ----
require_once dirname(__FILE__) . '/functions.php';

// ---- DB connection (PDO singleton) ----
function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Check credentials in includes/config.php');
        }
    }
    return $pdo;
}