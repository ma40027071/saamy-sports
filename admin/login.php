<?php
session_start();
require_once '../config/db.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: products.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admins WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid admin credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Samy Sports</title>
    <style>
        :root {
            --bg: #0f1115; --card: #181b22; --text: #f3f4f6;
            --muted: #9ca3af; --accent: #22c55e; --border: #262b36;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
        .login-box { width: 100%; max-width: 380px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 24px; }
        h1 { font-size: 1.35rem; color: var(--accent); margin-bottom: 6px; }
        p { font-size: 0.88rem; color: var(--muted); margin-bottom: 18px; }
        label { display: block; font-size: 0.88rem; font-weight: 600; margin-bottom: 5px; }
        input { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); margin-bottom: 14px; }
        .btn { width: 100%; background: var(--accent); color: #07130b; border: none; padding: 11px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .error { background: #3f1d1d; color: #fca5a5; padding: 10px; border-radius: 8px; margin-bottom: 14px; font-size: 0.88rem; }
    </style>
</head>
<body>
<form method="POST" class="login-box">
    <h1>Samy Sports Admin</h1>
    <p>Sign in to manage products & stock</p>

    <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button type="submit" class="btn">Login</button>
</form>
</body>
</html>