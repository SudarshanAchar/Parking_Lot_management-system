<?php
// ============================================================
// auth/login.php — Login Page
// ============================================================
session_start();
require_once '../config/db.php';

// Already logged in?
if (isset($_SESSION['user_id'])) {
    redirect($_SESSION['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $hashed = md5($password); // MD5 for simplicity (as per project spec)
        $stmt   = $conn->prepare("SELECT user_id, name, email, role FROM Users WHERE email=:email AND password=:password LIMIT 1");
        $stmt->execute([':email' => $email, ':password' => $hashed]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            setFlash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect($user['role'] === 'admin' ? '../admin/dashboard.php' : '../user/dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — ParkOS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="auth-page">
    <!-- Left Panel -->
    <div class="auth-left">
        <div class="big-icon">🏢</div>
        <h1>ParkOS<br>Management</h1>
        <p>Secure, efficient parking management for modern facilities.</p>
    </div>

    <!-- Right Panel -->
    <div class="auth-right">
        <h2>Sign In</h2>
        <p class="auth-sub">Access your parking dashboard</p>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="you@example.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autocomplete="email">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••"
                       required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:4px;">
                🔑 Login
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
        <div class="auth-footer" style="margin-top:8px;">
            <a href="../index.php" style="color:var(--text2);">← Back to Home</a>
        </div>
    </div>
</div>

</body>
</html>
