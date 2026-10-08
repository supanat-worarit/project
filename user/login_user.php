<?php
// user/login_user.php
session_start();
require_once '../config/settings.php';

if (isset($_SESSION['user_id'])) {
    header("Location: liff_app.php");
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
    <title>เข้าสู่ระบบนักศึกษา | PSU Passport</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>

    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f4f7fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 420px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); padding: 35px 30px; margin: 20px; }
        .brand-icon { width: 70px; height: 70px; background-color: #e8f2ff; color: #0022BA; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 15px auto; }
        .form-control { border-radius: 8px; padding: 10px 14px; }
        .btn-login { background-color: #0022BA; border-color: #0022BA; border-radius: 8px; padding: 10px; font-weight: 500; }
        .btn-login:hover { background-color: #001b94; border-color: #001b94; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand-icon">
            <i class="bi bi-person-bounding-box"></i>
        </div>
        <h5 class="fw-bold mb-1 text-dark">ระบบยืม-คืนอุปกรณ์กีฬา</h5>
        <p class="text-muted small mb-0">สำหรับนักศึกษา ม.อ.ตรัง</p>
        <span class="badge bg-light text-primary border mt-2">PSU Passport Sign-In</span>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger py-2 px-3 small d-flex align-items-center mb-3">
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <div><?= htmlspecialchars($error_msg) ?></div>
        </div>
    <?php endif; ?>

    <form action="auth_user.php" method="POST">
        <input type="hidden" name="line_user_id" id="line_user_id">
        
        <div class="mb-3">
            <label class="form-label small fw-semibold">รหัสนักศึกษา (PSU Passport)</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                <input type="text" name="username" class="form-control" placeholder="รหัสนักศึกษา หรือ username" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-semibold">รหัสผ่าน (Password)</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                <input type="password" name="password" id="passwordInput" class="form-control" placeholder="รหัสผ่าน PSU Passport" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login w-100 mb-2">
            เข้าสู่ระบบนักศึกษา
        </button>
    </form>
</div>

<script>
    async function initLiff() {
        try {
            await liff.init({ liffId: "<?= LIFF_ID ?>" });
            if (liff.isLoggedIn()) {
                const profile = await liff.getProfile();
                document.getElementById('line_user_id').value = profile.userId;
            } else {
                liff.login();
            }
        } catch (err) {
            console.error("LIFF Init Error: ", err);
        }
    }
    initLiff();
</script>

</body>
</html>