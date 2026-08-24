<?php
session_start();

// หากมีเซสชันของ Admin อยู่แล้ว ให้ไปยังหน้า Dashboard ทันที
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error_msg = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบเจ้าหน้าที่ | PSU Passport Authentication</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #002244;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            padding: 35px 30px;
        }
        .brand-icon {
            width: 70px;
            height: 70px;
            background-color: #e8f2ff;
            color: #002d62;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 15px auto;
        }
        .form-control {
            border-radius: 8px;
            padding: 10px 14px;
        }
        .btn-login {
            background-color: #002d62;
            border-color: #002d62;
            border-radius: 8px;
            padding: 10px;
            font-weight: 500;
        }
        .btn-login:hover {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .toggle-password {
            cursor: pointer;
            border-top-right-radius: 8px !important;
            border-bottom-right-radius: 8px !important;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand-icon">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h5 class="fw-bold mb-1 text-dark">ระบบจัดการศูนย์กีฬา</h5>
        <p class="text-muted small mb-0">เจ้าหน้าที่ศูนย์กีฬา ม.อ.ตรัง</p>
        <span class="badge bg-light text-primary border mt-2">PSU Passport Sign-In</span>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <div><?= htmlspecialchars($error_msg) ?></div>
        </div>
    <?php endif; ?>

    <form action="auth_ldap.php" method="POST">
        <div class="mb-3">
            <label class="form-label small fw-semibold">บัญชีผู้ใช้ PSU Passport</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control" placeholder="เช่น 66xxxxxxxx หรือ psu account" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold">รหัสผ่าน (Password)</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="passwordInput" class="form-control" placeholder="xxxxxxx" required>
                <button type="button" class="btn btn-outline-secondary toggle-password bg-light text-muted" id="togglePasswordBtn" onclick="togglePassword()">
                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login w-100 mb-2">
            <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบแอดมิน
        </button>
    </form>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('togglePasswordIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('bi-eye');
            toggleIcon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('bi-eye-slash');
            toggleIcon.classList.add('bi-eye');
        }
    }
</script>

</body>
</html>