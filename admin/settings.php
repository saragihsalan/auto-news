<?php
/** AUTO NEWS - Admin Settings */
require_once __DIR__ . '/auth.php';

// ---- Save settings ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $allowed = [
        'site_name','site_tagline','site_lang',
        'ai_endpoint','ai_api_key','ai_model','ai_temperature',
        'batch_limit','cron_secret',
    ];
    foreach ($allowed as $k) {
        if (isset($_POST[$k])) set_setting($k, trim($_POST[$k]));
    }
    require_once __DIR__ . '/../includes/seo.php';
    rebuild_robots();
    $saved = true;
}

require_once __DIR__ . '/layout.php';
admin_header('Pengaturan');
?>

<?php if (!empty($saved)): ?><div class="flash">✅ Pengaturan disimpan dan robots.txt diperbarui.</div><?php endif; ?>

<form method="post" class="form">
  <h2>🏷️ Info Situs</h2>
  <label>Nama Situs</label>
  <input name="site_name" value="<?= e(setting('site_name','AUTO NEWS')) ?>">

  <label>Tagline / Deskripsi</label>
  <input name="site_tagline" value="<?= e(setting('site_tagline','')) ?>">

  <label>Bahasa Utama</label>
  <select name="site_lang">
    <option value="id" <?= setting('site_lang','id')==='id'?'selected':'' ?>>Indonesia</option>
    <option value="en" <?= setting('site_lang','id')==='en'?'selected':'' ?>>English</option>
  </select>

  <h2>🤖 AI Provider (9Router / OpenAI-compatible)</h2>
  <label>Endpoint Base URL</label>
  <input name="ai_endpoint" placeholder="http://localhost:20128" value="<?= e(setting('ai_endpoint','')) ?>">
  <small>Contoh: http://your-server:20128 &nbsp; atau &nbsp; https://api.9router.com</small>

  <label>API Key (opsional)</label>
  <input name="ai_api_key" type="password" placeholder="biarkan kosong untuk memakai yang ada" value="<?= e(setting('ai_api_key','')) ?>">

  <label>Model</label>
  <input name="ai_model" placeholder="openai/gpt-4o-mini" value="<?= e(setting('ai_model','')) ?>">
  <small>Hubungkan ke 9Router untuk melihat daftar model. Contoh: openai/gpt-4o-mini, google/gemini-flash, dll.</small>

  <label>Temperature (0 - 1)</label>
  <input name="ai_temperature" type="number" step="0.1" min="0" max="1" value="<?= e(setting('ai_temperature','0.7')) ?>">

  <h2>⚙️ Pipeline</h2>
  <label>Jumlah Artikel Maks per Cron (batch_limit)</label>
  <input name="batch_limit" type="number" min="1" max="50" value="<?= e(setting('batch_limit','5')) ?>">

  <label>Secret Cron (proteksi akses web)</label>
  <input name="cron_secret" value="<?= e(setting('cron_secret','')) ?>">

  <button type="submit" name="save" value="1">💾 Simpan Semua</button>
</form>

<?php admin_footer();