<?php
// ============================================================
// index.php — Landing Page
// ============================================================
session_start();

// If already logged in, redirect appropriately
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ParkOS — Vehicle Parking Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="landing">
    <div class="landing-content">
        <span class="brand">🚘 ParkOS v1.0</span>
        <h1>Smart <span>Parking</span><br>Management</h1>
        <p class="tagline">Streamlined zone management, real-time slot tracking, and seamless booking — all in one system.</p>

        <div class="landing-btns">
            <a href="auth/login.php" class="btn btn-primary">
                🔑 Login to System
            </a>
            <a href="auth/register.php" class="btn btn-ghost">
                📝 Register as User
            </a>
        </div>

        <div style="margin-top: 48px; display: flex; gap: 32px; justify-content: center; flex-wrap: wrap;">
            <div style="text-align:center;">
                <div style="font-family:var(--mono);font-size:24px;font-weight:700;color:var(--accent);">Multi-Zone</div>
                <div style="font-size:12px;color:var(--text2);margin-top:3px;">Parking Areas</div>
            </div>
            <div style="text-align:center;">
                <div style="font-family:var(--mono);font-size:24px;font-weight:700;color:var(--accent2);">Real-time</div>
                <div style="font-size:12px;color:var(--text2);margin-top:3px;">Slot Tracking</div>
            </div>
            <div style="text-align:center;">
                <div style="font-family:var(--mono);font-size:24px;font-weight:700;color:var(--accent4);">Auto-Assign</div>
                <div style="font-size:12px;color:var(--text2);margin-top:3px;">Smart Booking</div>
            </div>
        </div>


    </div>
</div>

</body>
</html>
