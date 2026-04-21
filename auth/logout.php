<?php
// ============================================================
// auth/logout.php — Logout Handler
// ============================================================
session_start();
session_destroy();
header("Location: ../auth/login.php");
exit();
?>
