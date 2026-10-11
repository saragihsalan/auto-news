<?php
/** AUTO NEWS - Run pipeline manually (web trigger) */
require_once __DIR__ . '/auth.php';

$output = '';
if (isset($_POST['go'])) {
    // Capture cron output (flag to bypass secret - already admin-authorized)
    ob_start();
    $_SERVER['CRON_ADMIN_TRIGGER'] = '1';
    require dirname(__DIR__) . '/cron/run.php';
    $output = ob_get_clean();
}

require_once __DIR__ . '/layout.php';
admin_header('Jalankan Pipeline');
?>

<div class="card">
  <h2>▶️ Pipeline Cara Manual</h2>
  <p>Jalankan RSS fetch + AI rewrite + publish sekarang. Sama seperti cron, tapi manual.</p>
  <form method="post"><button name="go" value="1">🚀 Jalankan Sekarang</button></form>
</div>

<?php if ($output): ?>
<div class="card">
  <h2>Output</h2>
  <pre class="pre"><?= e($output) ?></pre>
</div>
<?php endif; ?>

<?php admin_footer();