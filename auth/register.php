<?php
// ============================================================
// auth/register.php — User Registration
// ============================================================
session_start();
require_once '../config/db.php';

if (isset($_SESSION['user_id'])) {
    redirect('../user/dashboard.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm'] ?? '');

    // Basic validation
    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^\d{10}$/', $phone)) {
        $error = 'Phone must be exactly 10 digits.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email/phone already exists
        $check = $conn->prepare("SELECT user_id FROM Users WHERE email=:email OR phone=:phone LIMIT 1");
        $check->execute([':email' => $email, ':phone' => $phone]);
        if ($check->fetch()) {
            $error = 'Email or phone number already registered.';
        } else {
            $hashed = md5($password);
            $stmt   = $conn->prepare("INSERT INTO Users (name, phone, email, password, role)
                                      VALUES (:name, :phone, :email, :password, 'user')");
            if ($stmt->execute([':name' => $name, ':phone' => $phone, ':email' => $email, ':password' => $hashed])) {
                $success = 'Registration successful! You can now log in.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — ParkOS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-left">
        <div class="big-icon">🚗</div>
        <h1>Join<br>ParkOS</h1>
        <p>Create your account to start booking parking slots instantly.</p>
    </div>

    <div class="auth-right">
        <h2>Create Account</h2>
        <p class="auth-sub">Register as a parking user</p>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
                <a href="login.php" style="margin-left:8px;color:var(--accent2);font-weight:600;">Login now →</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="John Doe"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       required maxlength="100">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" placeholder="9876543210"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                           pattern="\d{10}" required maxlength="10">
                    <p class="form-hint">10 digits only</p>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required maxlength="100">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Min 6 chars" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" placeholder="Re-enter" required minlength="6">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                📝 Create Account
            </button>
        </form>

        <div class="auth-footer">
            Already registered? <a href="login.php">Login here</a>
        </div>
        <div class="auth-footer" style="margin-top:8px;">
            <a href="../index.php" style="color:var(--text2);">← Back to Home</a>
        </div>
    </div>
</div>

</body>
</html>
