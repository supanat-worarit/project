<?php
// logout.php - ออกจากระบบ
session_start();

unset($_SESSION['user_id']);
unset($_SESSION['student_id']);
unset($_SESSION['full_name']);
unset($_SESSION['role']);

echo "<script>
    alert('ออกจากระบบเรียบร้อยแล้ว');
    window.location.href = 'register.php';
</script>";
exit;
?>