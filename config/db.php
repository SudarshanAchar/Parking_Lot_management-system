<?php

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_USER', 'postgres');      // Change to your PostgreSQL username
define('DB_PASS', 'Neil@876');              // Set your PostgreSQL password here
define('DB_NAME', 'parking_app');

// Create PostgreSQL connection
try {
    $dsn  = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $conn = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:20px;background:#fee;color:#900;border:1px solid #f00;margin:20px;border-radius:8px;">
        <strong>Database Connection Failed:</strong> ' . htmlspecialchars($e->getMessage()) . '
        <p>Make sure PostgreSQL is running and database <code>parking_app</code> exists.</p>
        <p>Run: <code>psql -U postgres -f parking_app.sql</code> to set up the database.</p>
    </div>');
}
























































/*
Sanitize user input (PDO uses prepared statements, but we still trim)
 */
function sanitize($conn, $input) {
    return trim($input);
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Flash message system
 */
function setFlash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/**
 * Check if user is logged in
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect('../auth/login.php');
    }
}

/**
 * Check if admin is logged in
 */
function requireAdmin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        redirect('../auth/login.php');
    }
}

/**
 * Format currency (INR)
 */
function formatMoney($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Calculate parking duration in hours
 */
function calcDuration($start, $end) {
    $s = strtotime($start);
    $e = $end ? strtotime($end) : time();
    return max(1, ceil(($e - $s) / 3600)); // Minimum 1 hour
}

/**
 * Calculate parking fee based on zone price_per_hour.
 * Falls back to legacy slot-type rates if no zone price is provided.
 *
 * @param int    $hours          Duration in hours
 * @param float  $price_per_hour Price from Zone table (preferred)
 * @param string $slotType       Slot type fallback (Car/Bike/Truck)
 */
function calcFee($hours, $price_per_hour = null, $slotType = 'Car') {
    if ($price_per_hour !== null && $price_per_hour > 0) {
        return $hours * $price_per_hour;
    }
    // Legacy fallback rates (used if zone price not available)
    $rates = ['Car' => 30, 'Bike' => 15, 'Truck' => 60];
    $rate  = $rates[$slotType] ?? 30;
    return $hours * $rate;
}
?>
