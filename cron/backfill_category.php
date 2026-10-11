<?php
/**
 * AUTO NEWS - Backfill Category (one-time / repeatable)
 *
 * Assigns a topic `category` to existing published articles that don't have one yet
 * (i.e. articles published before the category feature was added).
 *
 * Strategy: offline keyword classifier over a fixed Indonesian news taxonomy.
 * Instant and free — no AI calls needed. Articles that already have a category
 * are left untouched.
 *
 * Usage (cPanel cron / CLI):  php /home/USER/public_html/cron/backfill_category.php
 *   php cron/backfill_category.php --dry-run      # preview only, no writes
 *   php cron/backfill_category.php --limit=200    # only process N articles
 *   php cron/backfill_category.php --empty-only   # only fill empty categories
 *
 * Or via web (protected by cron_secret):  https://site.com/cron/backfill_category.php?secret=YOUR_SECRET
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/seo.php';

// ---- Optional web access guard (mirrors cron/run.php) ----
$secret = setting('cron_secret', '');
$adminTrigger = isset($_SERVER['CRON_ADMIN_TRIGGER']) && $_SERVER['CRON_ADMIN_TRIGGER'] === '1';
if (php_sapi_name() !== 'cli' && !$adminTrigger && $secret && (($_GET['secret'] ?? '') !== $secret)) {
    http_response_code(403);
    die('Forbidden');
}

$dryRun = in_array('--dry-run', $_SERVER['argv'] ?? [], true);
$emptyOnly = in_array('--empty-only', $_SERVER['argv'] ?? [], true);
$limit = 0;
foreach (($_SERVER['argv'] ?? []) as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int)$m[1];
}

// ---- Indonesian news topic taxonomy ----
$taxonomy = [
    'Politik' => ['presiden','pemilu','parlemen','dpr','partai','menteri','pemerintah','koalisi','oposisi','kpu','pemilihan','legislatif','gubernur','pilkada','kabinet','kementerian','politik','capres','cawapres','legislasi','undang-undang','kebijakan','dewan','pemerintahan','wakil rakyat','senayan','istana','kemendagri'],
    'Ekonomi' => ['ekonomi','rupiah','pasar','saham','inflasi','bank','krisis','bisnis','investasi','ekspor','impor','pajak','anggaran','apbn','uang','harga','laba','profit','perdagangan','keuangan','industri','pdb','pertumbuhan','ojk','bursa','kurs','dolar','bbm','sukuk','obligasi','utang','dana','kredit','valas','gdp','resesi'],
    'Olahraga' => ['bola','sepak','liga','timnas','piala','gol','pertandingan','juara','pemain','pelatih','bulu tangkis','badminton','basket','voli','renang','atlet','olimpiade','asian games','sea games','klub','laga','turnamen','sprint','olahraga','marcellino','greysia','minton','volleyball','sepak bola','pssi','fifa','liga 1','kejuaraan'],
    'Teknologi' => ['teknologi','aplikasi','digital','internet','software','hardware','ai','kecerdasan buatan','robot','startup','smartphone','gadget','gawai','cyber','keamanan siber','komputer','data','platform','online','media sosial','medsos','komputasi','telekomunikasi','5g','jaringan','tiktok','sosmed','siber','kripto','blockchain','teknologi informasi','e-commerce','bank digital','fintech'],
    'Hiburan' => ['film','musik','artis','selebriti','hiburan','konser','tayangan','sinetron','drakor','drama','lagu','album','penyanyi','aktor','aktris','gosip','fashion','hollywood','bioskop','box office','series','netflix','reality','kreator','vidio','streaming','boyband','girlband','idol','konten'],
    'Kesehatan' => ['kesehatan','rumah sakit','vaksin','pasien','dokter','obat','penyakit','virus','gizi','covid','epidemi','wabah','klinik','imunisasi','nutrisi','medis','stunting','diabetes','demam','infeksi','rumah sakit','rsud','puskesmas','bpjs','epidemiolog','icu','kesehatan jiwa','obat-obatan'],
    'Internasional' => ['dunia','internasional','asean','pbb','asia','amerika','eropa','timur tengah','perang','rusia','ukraina','china','amerika serikat','jepang','korea','india','global','luar negeri','kedutaan','bantuan internasional','nato','palestina','israel','gaza','iran','turki','australia','canada','inggris','prancis','jerman','ktt','forum internasional'],
    'Nasional' => ['indonesia','nasional','nusantara','jakarta','jabar','jawa','sumatera','sulawesi','kalimantan','papua','daerah','provinsi','kota','desa','bencana','gempa','banjir','longsor','kejadian','kecelakaan','operasi','kepolisian','penangkapan','tni','polri','polda','mabes','bandung','surabaya','medan','makassar','warga','masyarakat'],
    'Pendidikan' => ['pendidikan','sekolah','universitas','kampus','siswa','mahasiswa','guru','dosen','belajar','ujian','akademik','beasiswa','kurikulum','fakultas','riset','penelitian','lulusan','sma','smk','sdn','perguruan tinggi','snbp','snbt','pppk','nisn','sekolah dasar','sekolah menengah'],
    'Wisata' => ['wisata','pariwisata','travel','liburan','hotel','destinasi','tiket','bandara','penerbangan','tur','tempat wisata','objek wisata','kunjungan','parawisata','libur','akhir pekan','weekend','kruise','wisatawan'],
    'Hukum' => ['hukum','pengadilan','hakim','kejaksaan','kasus','tersangka','terdakwa','penjara','pidana','korupsi','dugaan','sengketa','advokat','kpk','kejahatan','kriminal','penipuan','pencurian','narkoba','vonis','sidang','putusan','penyidik','polisi','laporan polisi','kejagung','barang bukti'],
    'Lingkungan' => ['lingkungan','hutan','iklim','cuaca','polusi','perubahan iklim','satwa','konservasi','emisi','alam','cuaca ekstrem','pemanasan global','sampah','plastik','spesies','hutan hujan','la nina','el nino','badai','angin kencang','banjir bandang','kekeringan','mangrove','terumbu'],
];

// --- Build keyword index once ---
$kwHits = [];
foreach ($taxonomy as $cat => $kws) {
    foreach ($kws as $kw) {
        $kwHits[$kw] = $cat;
    }
}

/** Assign a topic category to an article based on title + excerpt keywords. */
function classify_category($title, $excerpt, $taxonomy, $kwHits) {
    $text = mb_strtolower($title . ' ' . $excerpt);
    $scores = array_fill_keys(array_keys($taxonomy), 0);
    // Simple keyword count (mb_substr positions). Prefer title hits weighted 3x.
    $titleText = mb_strtolower($title);
    foreach ($kwHits as $kw => $cat) {
        $score = 0;
        if (mb_stripos($titleText, $kw) !== false) $score += 3;
        if (mb_stripos($text, $kw) !== false) $score += 1;
        if ($score) $scores[$cat] += $score;
    }
    arsort($scores);
    $top = array_key_first($scores);
    return ($scores[$top] > 0) ? $top : 'Nasional';
}

// ---- Query articles needing a category ----
$where = "status='published' AND (category IS NULL OR category = '')";
if ($limit > 0) $limitClause = " LIMIT " . (int)$limit;
else $limitClause = '';

$st = db()->prepare("SELECT id, title, excerpt, category FROM articles WHERE " . $where . " ORDER BY published_at DESC" . $limitClause);
$st->execute();
$rows = $st->fetchAll();

if ($dryRun) echo "[dry-run] would process " . count($rows) . " article(s)\n";
else echo "Processing " . count($rows) . " article(s)...\n";

$updated = 0;
foreach ($rows as $r) {
    $cat = classify_category($r['title'], (string)$r['excerpt'], $taxonomy, $kwHits);
    if (!$cat) continue;
    if ($dryRun) {
        echo "  #{$r['id']} -> {$cat} (from: " . mb_substr($r['title'], 0, 50) . ")\n";
        $updated++;
        continue;
    }
    $u = db()->prepare("UPDATE articles SET category = ? WHERE id = ? AND (category IS NULL OR category = '')");
    $u->execute([$cat, $r['id']]);
    $updated += $u->rowCount();
}

if ($dryRun) {
    echo "[dry-run] " . $updated . " categorized (nothing persisted)\n";
} else {
    echo "Done. $updated article(s) categorized.\n";
}

// Refresh sitemap + cache so navigation reflects new category menu
if (!$dryRun) {
    rebuild_sitemap();
    clearcache();
    echo "Sitemap + cache rebuilt.\n";
}