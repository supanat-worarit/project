<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_ldap.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = $_SESSION['admin_name'] ?? 'เจ้าหน้าที่ศูนย์กีฬา';

// ตรวจสอบว่ากำลังเปิดหน้าใดหน้าหนึ่งในกลุ่ม Dashboard หรือไม่
$isDashboardGroup = in_array($currentPage, [
    'dashboard.php', 
    'dashboard_borrows.php', 
    'overdue.php', 
    'dashboard_damaged.php'
]);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

<style>
    body {
        font-family: 'Prompt', sans-serif;
        background-color: #f4f7fa;
    }
    .sidebar {
        width: 270px;
        min-height: 100vh;
        background-color: #002244;
        display: flex;
        flex-direction: column;
    }
    .sidebar .nav-link {
        color: #d1d5db;
        border-radius: 8px;
        margin: 3px 14px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        transition: all 0.2s;
        text-decoration: none;
    }
    .sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.08);
        color: #ffffff;
    }
    .sidebar .nav-link.active-main {
        background-color: #0d6efd;
        color: #ffffff;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.35);
    }
    .sub-menu {
        list-style: none;
        padding-left: 36px;
        margin: 4px 14px 6px 14px;
    }
    .sub-menu .nav-link {
        margin: 2px 0;
        padding: 7px 12px;
        font-size: 13px;
        color: #9ca3af;
    }
    .sub-menu .nav-link:hover {
        color: #ffffff;
        background-color: rgba(255, 255, 255, 0.05);
    }
    .sub-menu .nav-link.active-sub {
        color: #38bdf8;
        font-weight: 600;
        background-color: rgba(56, 189, 248, 0.1);
    }
    .brand-box {
        padding: 24px 16px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .admin-avatar {
        width: 54px;
        height: 54px;
        background: rgba(13, 110, 253, 0.15);
        color: #38bdf8;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        margin-bottom: 10px;
    }
</style>

<div class="sidebar flex-shrink-0 text-white">
    <div class="brand-box">
        <div class="admin-avatar">
            <i class="bi bi-shield-check"></i>
        </div>
        <h6 class="mb-0 fw-bold text-white"><?= htmlspecialchars($adminName) ?></h6>
        <small class="text-white-50" style="font-size: 12px;">เจ้าหน้าที่ศูนย์กีฬา ม.อ.ตรัง</small>
    </div>
    
    <ul class="nav nav-pills flex-column mb-auto mt-3">
        <!-- Dashboard เมนูหลักพร้อม Dropdown เมนูย่อย 3 หน้า -->
        <li class="nav-item">
            <a href="#dashboardSubmenu" data-bs-toggle="collapse" class="nav-link justify-content-between <?= $isDashboardGroup ? 'active-main' : ''; ?>" aria-expanded="<?= $isDashboardGroup ? 'true' : 'false'; ?>">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard ภาพรวม</span>
                </div>
                <i class="bi bi-chevron-down small"></i>
            </a>
            <div class="collapse <?= $isDashboardGroup ? 'show' : ''; ?>" id="dashboardSubmenu">
                <ul class="sub-menu">
                    <li>
                        <a href="dashboard.php" class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active-sub' : ''; ?>">
                            <i class="bi bi-pie-chart me-1"></i> ภาพรวม & สถิติ
                        </a>
                    </li>
                    <li>
                        <a href="dashboard_borrows.php" class="nav-link <?= ($currentPage == 'dashboard_borrows.php') ? 'active-sub' : ''; ?>">
                            <i class="bi bi-journal-text me-1 text-info"></i> 1. รายการยืมทั้งหมด
                        </a>
                    </li>
                    <li>
                        <a href="overdue.php" class="nav-link <?= ($currentPage == 'overdue.php') ? 'active-sub' : ''; ?>">
                            <i class="bi bi-exclamation-triangle me-1 text-warning"></i> 2. ผู้ค้างส่งอุปกรณ์
                        </a>
                    </li>
                    <li>
                        <a href="dashboard_damaged.php" class="nav-link <?= ($currentPage == 'dashboard_damaged.php') ? 'active-sub' : ''; ?>">
                            <i class="bi bi-tools me-1 text-danger"></i> 3. อุปกรณ์ที่ชำรุด
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="nav-item">
            <a href="approvals.php" class="nav-link <?= ($currentPage == 'approvals.php') ? 'active-main' : ''; ?>">
                <i class="bi bi-check2-circle"></i> รายการรออนุมัติ (ยืม-คืน)
            </a>
        </li>
        <li class="nav-item">
            <a href="categories.php" class="nav-link <?= (in_array($currentPage, ['categories.php', 'equipment.php'])) ? 'active-main' : ''; ?>">
                <i class="bi bi-boxes"></i> จัดการประเภท & อุปกรณ์
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link <?= ($currentPage == 'users.php') ? 'active-main' : ''; ?>">
                <i class="bi bi-people"></i> จัดการสิทธิ์ & ข้อมูลนักศึกษา
            </a>
        </li>
    </ul>

    <div class="p-3 border-top border-white border-opacity-10">
        <button type="button" class="btn btn-outline-light btn-sm w-100 d-flex align-items-center justify-content-center gap-2" onclick="openLogoutModal()">
            <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
        </button>
    </div>
</div>

<!-- Logout Modal -->
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-4 border-0 shadow" style="border-radius: 16px;">
            <div class="mb-2">
                <i class="bi bi-question-circle text-primary" style="font-size: 2.8rem;"></i>
            </div>
            <h6 class="fw-bold mb-2 text-dark">ยืนยันการออกจากระบบ?</h6>
            <p class="text-muted small mb-4">คุณต้องการออกจากระบบจัดการศูนย์กีฬา ใช่หรือไม่</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="logout.php" class="btn btn-primary btn-sm px-3 fw-medium">ยืนยัน</a>
                <button type="button" class="btn btn-light btn-sm px-3 fw-medium" data-bs-dismiss="modal">ยกเลิก</button>
            </div>
        </div>
    </div>
</div>

<script>
function openLogoutModal() {
    new bootstrap.Modal(document.getElementById('logoutConfirmModal')).show();
}
</script>