<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์ ถ้าไม่ได้ล็อกอินให้เด้งไปหน้า login_ldap.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_ldap.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_name'] ?? 'เจ้าหน้าที่ศูนย์กีฬา';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
    body {
        font-family: 'Prompt', sans-serif;
        background-color: #f4f6f9;
    }
    .sidebar {
        width: 250px;
        min-height: 100vh;
        background-color: #002d62;
    }
    .sidebar .nav-link {
        color: #d1d5db;
        border-radius: 8px;
        margin: 4px 12px;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
    }
    .sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }
    .sidebar .nav-link.active {
        background-color: #0d6efd;
        color: #ffffff;
        font-weight: 500;
    }
    .card-custom {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }
    .modal-box-custom {
        background: #d4d4d4;
        border: 2px solid #0056b3;
        border-radius: 4px;
        padding: 30px;
    }
</style>

<div class="sidebar d-flex flex-column flex-shrink-0 text-white">
    <div class="p-4 text-center border-bottom border-secondary border-opacity-25">
        <i class="bi bi-shield-check text-info" style="font-size: 2.5rem;"></i>
        <h6 class="mt-2 mb-0 fw-bold"><?= htmlspecialchars($adminName) ?></h6>
        <small class="text-white-50">ม.อ.ตรัง (Admin)</small>
    </div>
    
    <ul class="nav nav-pills flex-column mb-auto mt-3">
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="categories.php" class="nav-link <?= (in_array($currentPage, ['categories.php', 'equipment.php'])) ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i> จัดการสต็อกอุปกรณ์
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?= ($currentPage == 'users.php') ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> จัดการสิทธิ์ผู้ใช้
            </a>
        </li>
    </ul>

    <div class="p-3 border-top border-secondary border-opacity-25">
        <button type="button" class="btn btn-outline-light btn-sm w-100 d-flex align-items-center justify-content-center gap-2" onclick="openLogoutModal()">
            <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
        </button>
    </div>
</div>

<!-- Modal ยืนยันการออกจากระบบ -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-box-custom text-center text-dark">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="mb-3">
                <i class="bi bi-exclamation-circle text-primary" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold mb-2">ยืนยันการออกจากระบบ</h5>
            <p class="text-muted mb-4">คุณต้องการออกจากระบบจัดการศูนย์กีฬา ใช่หรือไม่?</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="logout.php" class="btn btn-primary px-4 fw-bold">ยืนยัน</a>
                <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
            </div>
        </div>
    </div>
</div>

<script>
function openLogoutModal() {
    const modalEl = document.getElementById('logoutConfirmModal');
    if (typeof bootstrap !== 'undefined') {
        const myModal = new bootstrap.Modal(modalEl);
        myModal.show();
    } else {
        if (confirm('คุณต้องการออกจากระบบจัดการศูนย์กีฬา ใช่หรือไม่?')) {
            window.location.href = 'logout.php';
        }
    }
}
</script>