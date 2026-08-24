<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

// ลบบัญชีผู้ใช้งาน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $user_id = (int)$_POST['user_id'];
    
    // 1. ตรวจสอบว่ายังมีรายการค้างส่งหรือไม่ (กำลังยืม หรือ เกินกำหนด)
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND trans_status IN ('borrowed', 'overdue')");
    $stmtCheck->execute([$user_id]);
    $pendingCount = $stmtCheck->fetchColumn();
    
    if ($pendingCount > 0) {
        // ถ้ายืมอยู่ ห้ามลบ
        $_SESSION['error_msg'] = "ไม่สามารถลบบัญชีได้! นักศึกษาคนนี้ยังมีอุปกรณ์ที่ยังไม่ได้ส่งคืน";
    } else {
        // ถ้าคืนหมดแล้ว ให้ทำการลบประวัติเก่าและลบบัญชี
        try {
            $pdo->beginTransaction();
            
            // ลบข้อมูลค่าปรับที่เชื่อมกับประวัติของ user นี้ (ถ้ามี)
            $pdo->prepare("DELETE FROM equipment_fines WHERE trans_id IN (SELECT trans_id FROM transactions WHERE user_id = ?)")->execute([$user_id]);
            
            // ลบประวัติการยืม-คืนทั้งหมดของ user นี้ (เคลียร์ Foreign Key)
            $pdo->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$user_id]);
            
            // สุดท้าย ลบบัญชี user 
            $pdo->prepare("DELETE FROM user WHERE user_id = ?")->execute([$user_id]);
            
            $pdo->commit();
            $_SESSION['success_msg'] = "ลบบัญชีนักศึกษาและล้างประวัติเรียบร้อยแล้ว";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage();
        }
    }
    
    header("Location: users.php");
    exit;
}

// เตรียมรับข้อความแจ้งเตือนมาแสดงผล
$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

// ดึงรายชื่อนักศึกษา (role = 'user')
$stmt = $pdo->query("SELECT * FROM user WHERE role = 'user' ORDER BY student_id ASC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสิทธิ์บัญชีผู้ใช้งานนักศึกษา</title>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">
        <h5 class="fw-bold mb-4">จัดการสิทธิ์บัญชีผู้ใช้งานนักศึกษา</h5>

        <!-- เพิ่มกล่องแจ้งเตือน Alert -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-octagon-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- สิ้นสุดส่วนแจ้งเตือน -->

        <div class="card card-custom p-0 overflow-hidden">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>สถานะการศึกษา</th>
                        <th class="text-end pe-4">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($users)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">ไม่พบข้อมูลนักศึกษา</td></tr>
                    <?php endif; ?>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['student_id']) ?></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td>
                            <?php if($u['status'] === 'active'): ?>
                                <span class="badge bg-success">กำลังศึกษา</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">สำเร็จการศึกษา</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-danger text-white" onclick="openDeleteUserModal('<?= $u['user_id'] ?>', '<?= $u['student_id'] ?>')">
                                <i class="bi bi-trash"></i> ลบบัญชี
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal ยืนยันการลบบัญชีผู้ใช้ -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom text-center">
                <h5 class="fw-bold mb-3">ลบบัญชีผู้ใช้</h5>
                <p class="mb-4">คุณต้องการลบบัญชีผู้ใช้ <strong id="del_student_id"></strong> ใช่หรือไม่</p>
                <form action="users.php" method="POST">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <div class="d-flex justify-content-center gap-3">
                        <button type="submit" class="btn btn-danger px-4 fw-bold">ยืนยัน</button>
                        <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openDeleteUserModal(userId, studentId) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('del_student_id').innerText = studentId;
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>
</body>
</html>