<?php
// user/logout.php
session_start();

// ล้างค่า Session ทั้งหมด
unset($_SESSION['user_id']);
unset($_SESSION['student_id']);
unset($_SESSION['full_name']);
unset($_SESSION['role']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ออกจากระบบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; margin: 0; }
        .swal2-popup { width: 85% !important; max-width: 320px !important; border-radius: 16px !important; padding: 1.5em 1em 1.2em !important; }
        .swal2-title { font-size: 20px !important; margin-bottom: 5px !important; }
        .swal2-html-container { font-size: 14px !important; color: #4b5563 !important; }
        .swal2-confirm { font-size: 15px !important; font-weight: 500 !important; width: 100% !important; border-radius: 8px !important; padding: 10px !important; margin-top: 15px !important; }
        .swal2-icon { transform: scale(0.8); margin: 0 auto 10px auto !important; }
    </style>
</head>
<body>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: 'ออกจากระบบเรียบร้อยแล้ว',
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#0022BA',
            allowOutsideClick: false
        }).then(() => {
            window.location.href = 'login_user.php';
        });
    </script>
</body>
</html>
