<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบว่ามีสถานะการล็อกอินของแอดมินหรือไม่
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $_SESSION['login_error'] = "กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน";
    header("Location: login_ldap.php");
    exit;
}