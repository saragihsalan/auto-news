<?php
/**
 * AUTO NEWS - Admin Auth guard + login handling
 * Include at top of every admin page AFTER config.php.
 */
require_once dirname(__DIR__) . '/includes/config.php';

// ---- Handle login POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM admins WHERE username = ? LIMIT 1');
    $st->execute([$u]);
    $admin = $st->fetch();

    if ($admin && password_verify($p, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_user'] = $admin['username'];
        $_SESSION['admin_role'] = $admin['role'];
        redirect($_GET['next'] ?? 'index.php');
    } else {
        $loginError = 'Username atau password salah.';
    }
}

// ---- Logout ----
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ---- Guard ----
if (!is_admin()) {
    // Show login form
    $loginError = $loginError ?? '';
    ?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="login-body">
  <div class="login-box">
    <h1>🔐 Admin Login</h1>
    <?php if ($loginError): ?><div class="err"><?= e($loginError) ?></div><?php endif; ?>
    <form method="post">
      <label>Username</label>
      <input type="text" name="username" required autofocus>
      <label>Password</label>
      <input type="password" name="password" required>
      <input type="hidden" name="login" value="1">
      <button type="submit">Masuk</button>
    </form>
  </div>
</body>
</html>
<?php
    exit;
}
