<?php
session_start();

unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);
unset($_SESSION['admin_student_id']);

header("Location: login_ldap.php");
exit;
?>