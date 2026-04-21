<?php
// ============================================================
// user/_layout.php — User Sidebar Layout Include
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Dashboard') ?> — ParkOS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="wrapper">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">🚗</div>
        <h1>ParkOS</h1>
        <p>My Parking</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="dashboard.php" class="<?= ($active_nav==='dashboard')?'active':'' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <div class="nav-section-label">Parking</div>
        <a href="book.php" class="<?= ($active_nav==='book')?'active':'' ?>">
            <span class="nav-icon">🅿️</span> Book Parking
        </a>
        <a href="history.php" class="<?= ($active_nav==='history')?'active':'' ?>">
            <span class="nav-icon">📋</span> My Sessions
        </a>
        <a href="vehicles.php" class="<?= ($active_nav==='vehicles')?'active':'' ?>">
            <span class="nav-icon">🚗</span> My Vehicles
        </a>
        <a href="payments.php" class="<?= ($active_nav==='payments')?'active':'' ?>">
            <span class="nav-icon">💳</span> Payments
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-badge">
            <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?></div>
            <div>
                <div style="font-size:13px;color:var(--text);font-weight:600;"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></div>
                <div style="font-size:11px;color:var(--text2);">User Account</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">
            🚪 Logout
        </a>
    </div>
</aside>
<main class="main-content">
