<?php
/**
 * AUTO NEWS - Helper Functions
 */

// ---- Clean output / escape ----
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ---- Slugify (supports unicode => transliterate) ----
function slugify($text) {
    $text = trim($text);
    // Convert common common trademark characters
    $text = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    $text = trim($text, '-');
    if ($text === '') $text = 'berita-' . time();
    return $text;
}

// ---- TRUNCATE for excerpt ----
function truncate($str, $len = 160, $suffix = '') {
    $str = trim(strip_tags($str));
    if (mb_strlen($str) <= $len) return $str;
    $tr = mb_substr($str, 0, $len);
    $last = mb_strrpos($tr, ' ');
    if ($last !== false) $tr = mb_substr($tr, 0, $last);
    return $tr . ($suffix !== '' ? $suffix : '');
}

// ---- Strip tags but keep paragraphs ----
function plain_text($html) {
    $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
    $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $text);
    $text = preg_replace('#<br\s*/?>#i', "\n", $text);
    $text = preg_replace('#</p>#i', "\n\n", $text);
    $text = trim(strip_tags($text));
    return preg_replace("/\n{3,}/", "\n\n", $text);
}

// ---- Parse XML attributes / cdata helper ----
function xml_cdata($node) {
    return $node ? trim((string)$node) : '';
}

// ---- Format date (relative for display) ----
function time_ago($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'baru saja';
    if ($diff < 3600) return floor($diff/60) . ' menit lalu';
    if ($diff < 86400) return floor($diff/3600) . ' jam lalu';
    if ($diff < 2592000) return floor($diff/86400) . ' hari lalu';
    return date('d M Y', $ts);
}

// ---- Resolve relative image URL vs site base ----
function resolve_url($u, $base) {
    if (!$u) return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    if ($u[0] === '/') return rtrim($base, '/') . $u;
    return $base . '/' . ltrim($u, '/');
}

// ---- Simple cURL helper ----
function http_get($url, $timeout = 20) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; AutoNewsBot/1.0)',
        CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml, */*'],
        CURLOPT_ENCODING       => '',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body;
}

// ---- HTTP POST JSON helper (for AI calls) ----
function http_post_json($url, $payload, $timeout = 120) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $body = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $body, 'http' => $info['http_code']];
}

// ---- Pretty-print JSON (for schema blocks) ----
function json_pretty($data) {
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// ---- Leaf: cleaning marker removal (removes trailing " leaked") ----
function clean_text($text) {
    return trim($text);
}

// ---- Redirect helper ----
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ---- Is admin logged in ----
function is_admin() {
    return !empty($_SESSION['admin_id']);
}

// ---- Log to scrape_logs ----
function log_event($type, $source_id = null, $article_id = null, $message = '') {
    try {
        $stmt = db()->prepare('INSERT INTO scrape_logs (type, source_id, article_id, message) VALUES (?,?,?,?)');
        $stmt->execute([$type, $source_id, $article_id, $message]);
    } catch (Exception $e) { /* ignore */ }
}

// ---- Get settings array in one go ----
function all_settings() {
    $out = [];
    try {
        foreach (db()->query('SELECT skey, svalue FROM settings') as $r) $out[$r['skey']] = $r['svalue'];
    } catch (Exception $e) {}
    return $out;
}
