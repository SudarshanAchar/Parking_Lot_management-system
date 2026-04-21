<?php
// ============================================================
// admin/_layout.php — Admin Sidebar Layout Include
// Usage: include '_layout.php'; at top of admin pages
// Variables expected: $page_title, $active_nav
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Admin') ?> — ParkOS Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="wrapper">
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">🏢</div>
        <h1>ParkOS</h1>
        <p>Admin Panel</p>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <a href="dashboard.php" class="<?= ($active_nav==='dashboard')?'active':'' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>
        <div class="nav-section-label">Management</div>
        <a href="manage_zones.php" class="<?= ($active_nav==='zones')?'active':'' ?>">
            <span class="nav-icon">🗺️</span> Parking Zones
        </a>
        <a href="manage_users.php" class="<?= ($active_nav==='users')?'active':'' ?>">
            <span class="nav-icon">👥</span> Users
        </a>
        <a href="view_sessions.php" class="<?= ($active_nav==='sessions')?'active':'' ?>">
            <span class="nav-icon">🚗</span> Parking Sessions
        </a>
        <a href="view_payments.php" class="<?= ($active_nav==='payments')?'active':'' ?>">
            <span class="nav-icon">💰</span> Payments
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-badge">
            <div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?></div>
            <div>
                <div style="font-size:13px;color:var(--text);font-weight:600;"><?= htmlspecialchars($_SESSION['name'] ?? '') ?></div>
                <div style="font-size:11px;color:var(--accent);">Administrator</div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center;">
            🚪 Logout
        </a>
    </div>
</aside>
<!-- Main -->
<main class="main-content">
