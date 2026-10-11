<?php
/** AUTO NEWS - Articles manager */
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (isset($_POST['status'])) {
        db()->prepare("UPDATE articles SET status = ? WHERE id = ?")->execute([$_POST['status'], $id]);
    } elseif (isset($_POST['feature'])) {
        db()->prepare('UPDATE articles SET is_featured = IF(is_featured=1,0,1) WHERE id = ?')->execute([$id]);
    } elseif (isset($_POST['delete'])) {
        db()->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
    }
    redirect('articles.php');
}

$statusFilter = $_GET['s'] ?? '';
$q = "SELECT * FROM articles" . ($statusFilter ? " WHERE status = '" . db()->quote($statusFilter) . "'" : '') . " ORDER BY created_at DESC LIMIT 100";
$rows = db()->query($q)->fetchAll();

require_once __DIR__ . '/layout.php';
admin_header('Artikel');
?>

<div class="card">
  Filter:
  <a href="articles.php">Semua</a>
  <a href="articles.php?s=draft">Draft</a>
  <a href="articles.php?s=published">Published</a>
  <a href="articles.php?s=trash">Trash</a>
</div>

<table class="table">
  <thead><tr><th>Title</th><th>Status</th><th>Featured</th><th>Blog Views</th><th>Waktu</th><th>Aksi</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $a): ?>
    <tr>
      <td><a href="../<?= e($a['slug']) ?>"><?= e(mb_substr($a['title'],0,70)) ?></a></td>
      <td>
        <form method="post" style="margin:0">
          <input type="hidden" name="id" value="<?= $a['id'] ?>">
          <select name="status" onchange="this.form.submit()">
            <option value="draft"<?= $a['status']==='draft'?' selected':'' ?>>Draft</option>
            <option value="published"<?= $a['status']==='published'?' selected':'' ?>>Published</option>
            <option value="trash"<?= $a['status']==='trash'?' selected':'' ?>>Trash</option>
          </select>
        </form>
      </td>
      <td><form method="post" style="margin:0"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button name="feature" value="1" class="btn-star"><?= $a['is_featured']?'★':'☆' ?></button></form></td>
      <td><?= number_format((int)$a['views']) ?></td>
      <td class="small"><?= $a['created_at'] ?></td>
      <td><form method="post" style="margin:0" onsubmit="return confirm('Hapus permanen?')"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button name="delete" value="1" class="btn-del">🗑</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php admin_footer();