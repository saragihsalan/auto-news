<?php
/** AUTO NEWS - RSS Sources manager */
require_once __DIR__ . '/auth.php';

// ---- Add / update / delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        db()->prepare('INSERT INTO rss_sources (name, url, source_site, language, active, fetch_interval_min) VALUES (?,?,?,?,?,?)')
            ->execute([$_POST['name'], trim($_POST['url']), trim($_POST['source_site']), $_POST['language'] ?? 'id', 1, (int)($_POST['fetch_interval_min'] ?? 30)]);
    } elseif (isset($_POST['delete'])) {
        db()->prepare('DELETE FROM rss_sources WHERE id = ?')->execute([(int)$_POST['id']]);
    } elseif (isset($_POST['toggle'])) {
        db()->prepare('UPDATE rss_sources SET active = IF(active=1,0,1) WHERE id = ?')->execute([(int)$_POST['id']]);
    }
    redirect('sources.php');
}

$a = $_GET['a'] ?? '';
require_once __DIR__ . '/layout.php';
admin_header('RSS Sources');
?>

<div class="card">
  <h2>➕ Tambah Sumber RSS</h2>
  <form method="post" class="form form-inline">
    <input name="name" placeholder="Nama (cth: CNN Indonesia)" required>
    <input name="url" placeholder="URL feed.xml" required>
    <input name="source_site" placeholder="Portal (cth: cnnindonesia)">
    <select name="language">
      <option value="id">ID</option>
      <option value="en">EN</option>
    </select>
    <input name="fetch_interval_min" type="number" value="30" title="Menit antar fetch">
    <button name="add" value="1" type="submit">Tambah</button>
  </form>
</div>

<table class="table">
  <thead><tr><th>Nama</th><th>URL</th><th>Portal</th><th>Bahasa</th><th>Interval</th><th>Last Fetch</th><th>Aktif</th><th></th></tr></thead>
  <tbody>
  <?php foreach (db()->query('SELECT * FROM rss_sources ORDER BY id DESC') as $s): ?>
    <tr>
      <td><?= e($s['name']) ?></td>
      <td class="small"><?= e(mb_substr($s['url'],0,45)) ?></td>
      <td><?= e($s['source_site']) ?></td>
      <td><?= e($s['language']) ?></td>
      <td><?= (int)$s['fetch_interval_min'] ?>m</td>
      <td class="small"><?= time_ago($s['last_fetched_at']) ?></td>
      <td><form method="post" style="margin:0"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button name="toggle" value="1" class="btn-<?= $s['active']?'on':'off' ?>"><?= $s['active']?'Aktif':'Mati' ?></button></form></td>
      <td><form method="post" style="margin:0" onsubmit="return confirm('Hapus sumber?')"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button name="delete" value="1" class="btn-del">🗑</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php admin_footer();