<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

$admin_id = $_SESSION['admin_id'] ?? 1;

// ฟังก์ชันหาชื่อคอลัมน์ที่มีอยู่จริงใน transactions ป้องกัน Error
$columnsStmt = $pdo->query("SHOW COLUMNS FROM transactions");
$existingCols = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

$colBorrowBy = in_array('borrow_approved_by', $existingCols) ? 'borrow_approved_by' : (in_array('borrow_approve_by', $existingCols) ? 'borrow_approve_by' : (in_array('borrow_approver_id', $existingCols) ? 'borrow_approver_id' : null));
$colBorrowAt = in_array('borrow_approved_at', $existingCols) ? 'borrow_approved_at' : (in_array('borrow_approve_at', $existingCols) ? 'borrow_approve_at' : null);
$colReturnBy = in_array('return_approved_by', $existingCols) ? 'return_approved_by' : (in_array('return_approve_by', $existingCols) ? 'return_approve_by' : (in_array('return_approver_id', $existingCols) ? 'return_approver_id' : null));
$colReturnAt = in_array('return_approved_at', $existingCols) ? 'return_approved_at' : (in_array('return_approve_at', $existingCols) ? 'return_approve_at' : null);

// ดึงรายชื่อ Admin ทั้งหมดมาทำ Map (user_id => full_name) เพื่อเลี่ยงปัญหา JOIN
$adminMap = [];
$adminList = $pdo->query("SELECT user_id, full_name FROM user")->fetchAll(PDO::FETCH_KEY_PAIR);

// จัดการกด Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $trans_id = (int)$_POST['trans_id'];
    $decision = $_POST['decision'];

    $stmtT = $pdo->prepare("SELECT * FROM transactions WHERE trans_id = ?");
    $stmtT->execute([$trans_id]);
    $trans = $stmtT->fetch(PDO::FETCH_ASSOC);

    if ($trans) {
        $now = date('Y-m-d H:i:s');
        $eq_id = $trans['eq_id'];
        $current_status = $trans['trans_status'] ?? '';

        if ($decision === 'approve') {
            if ($current_status === 'pending_borrow') {
                $sql = "UPDATE transactions SET trans_status = 'borrowed'";
                $params = [];
                if ($colBorrowBy) { $sql .= ", {$colBorrowBy} = ?"; $params[] = $admin_id; }
                if ($colBorrowAt) { $sql .= ", {$colBorrowAt} = ?"; $params[] = $now; }
                $sql .= " WHERE trans_id = ?";
                $params[] = $trans_id;
                
                $pdo->prepare($sql)->execute($params);
                $pdo->prepare("UPDATE sport_equipment SET status = 'borrowed' WHERE eq_id = ?")->execute([$eq_id]);

            } elseif ($current_status === 'pending_return' || $current_status === 'borrowed') {
                $sql = "UPDATE transactions SET trans_status = 'returned', return_time = ?";
                $params = [$now];
                if ($colReturnBy) { $sql .= ", {$colReturnBy} = ?"; $params[] = $admin_id; }
                if ($colReturnAt) { $sql .= ", {$colReturnAt} = ?"; $params[] = $now; }
                $sql .= " WHERE trans_id = ?";
                $params[] = $trans_id;

                $pdo->prepare($sql)->execute($params);
                $pdo->prepare("UPDATE sport_equipment SET status = 'avaliable' WHERE eq_id = ?")->execute([$eq_id]);
            }

        } elseif ($decision === 'reject') {
            $reject_reason = trim($_POST['reject_reason'] ?? '');
            $reject_image = null;

            if (isset($_FILES['reject_image']) && $_FILES['reject_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/rejects/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = pathinfo($_FILES['reject_image']['name'], PATHINFO_EXTENSION);
                $destPath = $uploadDir . 'reject_' . time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['reject_image']['tmp_name'], $destPath)) {
                    $reject_image = $destPath;
                }
            }

            $pdo->prepare("
                UPDATE transactions 
                SET trans_status = 'rejected',
                    reject_reason = ?,
                    reject_image = ?
                WHERE trans_id = ?
            ")->execute([$reject_reason, $reject_image, $trans_id]);
        }
    }
    header("Location: approvals.php");
    exit;
}

// 1. ดึงคำขอที่รอการอนุมัติ (pending_borrow หรือ pending_return)
$stmtPending = $pdo->query("
    SELECT t.*, u.full_name as user_name, u.student_id, e.eq_code, e.eq_name
    FROM transactions t
    LEFT JOIN user u ON t.user_id = u.user_id
    LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
    WHERE t.trans_status IN ('pending_borrow', 'pending_return')
    ORDER BY t.trans_id ASC
");
$pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// 2. ดึงประวัติที่ดำเนินการแล้ว (ไม่ใช้ JOIN แอดมินตรง ๆ ป้องกันชื่อคอลัมน์ชน)
$stmtHistory = $pdo->query("
    SELECT t.*, u.full_name as user_name, u.student_id, e.eq_code, e.eq_name
    FROM transactions t
    LEFT JOIN user u ON t.user_id = u.user_id
    LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
    WHERE t.trans_status IN ('borrowed', 'returned', 'rejected')
    ORDER BY t.trans_id DESC
    LIMIT 20
");
$historyList = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อนุมัติการยืม-คืนอุปกรณ์ | ศูนย์กีฬา ม.อ.ตรัง</title>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4" style="background-color: #f8fafc; min-height: 100vh;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">ระบบอนุมัติการยืม - คืนอุปกรณ์กีฬา</h4>
                <p class="text-muted small mb-0">ตรวจสอบคำขอยืมและคืนอุปกรณ์ พร้อมบันทึกประวัติและหลักฐาน</p>
            </div>
        </div>

        <!-- รายการที่รออนุมัติ -->
        <div class="card card-custom p-0 overflow-hidden mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="bi bi-hourglass-split me-2"></i>คำขอที่รอการอนุมัติ (Pending Requests)
                </h6>
                <span class="badge bg-primary rounded-pill"><?= count($pendingRequests) ?> รายการ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสคำขอ</th>
                            <th>ผู้ยืม/คืน (นักศึกษา)</th>
                            <th>อุปกรณ์</th>
                            <th>ประเภทคำขอ</th>
                            <th>วัน-เวลาที่ส่งคำขอ</th>
                            <th class="text-end pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingRequests)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">ไม่มีรายการคำขอที่รออนุมัติในขณะนี้</td></tr>
                        <?php endif; ?>
                        <?php foreach ($pendingRequests as $p): ?>
                        <tr>
                            <td class="fw-semibold">#TR-<?= str_pad($p['trans_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['user_name'] ?? 'ไม่ระบุชื่อ') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['student_id'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($p['eq_code'] ?? '-') ?></span>
                                <span class="ms-1"><?= htmlspecialchars($p['eq_name'] ?? '-') ?></span>
                            </td>
                            <td>
                                <?php if ($p['trans_status'] === 'pending_borrow'): ?>
                                    <span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-box-arrow-up-right me-1"></i>ขอยืม</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-box-arrow-in-left me-1"></i>ขอคืน</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['borrow_time'] ?? '-') ?></td>
                            <td class="text-end pe-4">
                                <form action="approvals.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันอนุมัติคำขอนี้ใช่หรือไม่?');">
                                    <input type="hidden" name="action" value="approve_action">
                                    <input type="hidden" name="trans_id" value="<?= $p['trans_id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success px-3 fw-medium me-1">
                                        <i class="bi bi-check-lg"></i> อนุมัติ
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger px-3 fw-medium" onclick="openRejectModal('<?= $p['trans_id'] ?>', '<?= htmlspecialchars($p['user_name'] ?? '') ?>', '<?= htmlspecialchars($p['eq_name'] ?? '') ?>')">
                                    <i class="bi bi-x-lg"></i> ไม่อนุมัติ
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ประวัติการดำเนินการล่าสุด -->
        <div class="card card-custom p-0 overflow-hidden border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-clock-history me-2"></i>ประวัติการดำเนินการล่าสุด</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสคำขอ</th>
                            <th>ผู้ยืม/คืน</th>
                            <th>อุปกรณ์</th>
                            <th>สถานะ</th>
                            <th>ผู้อนุมัติ</th>
                            <th>วัน-เวลาที่อนุมัติ</th>
                            <th>หมายเหตุ / รูปหลักฐาน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historyList)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีประวัติการอนุมัติ</td></tr>
                        <?php endif; ?>
                        <?php foreach ($historyList as $h): 
                            $approverId = ($h['trans_status'] === 'returned') ? ($colReturnBy ? ($h[$colReturnBy] ?? null) : null) : ($colBorrowBy ? ($h[$colBorrowBy] ?? null) : null);
                            $approverName = $adminList[$approverId] ?? 'เจ้าหน้าที่';
                            $approvedAt = ($h['trans_status'] === 'returned') ? ($colReturnAt ? ($h[$colReturnAt] ?? '-') : '-') : ($colBorrowAt ? ($h[$colBorrowAt] ?? '-') : '-');
                        ?>
                        <tr>
                            <td>#TR-<?= str_pad($h['trans_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><?= htmlspecialchars($h['user_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($h['eq_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($h['trans_status'] === 'borrowed'): ?>
                                    <span class="badge bg-primary">กำลังยืม</span>
                                <?php elseif ($h['trans_status'] === 'returned'): ?>
                                    <span class="badge bg-success">คืนสำเร็จ</span>
                                <?php elseif ($h['trans_status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">ไม่อนุมัติ</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($h['trans_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-medium text-primary"><?= htmlspecialchars($approverName) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($approvedAt ?? '-') ?></td>
                            <td>
                                <?php if (!empty($h['reject_reason'])): ?>
                                    <div class="small text-danger fw-medium">สาเหตุ: <?= htmlspecialchars($h['reject_reason']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($h['reject_image'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 mt-1" onclick="viewRejectImage('<?= $h['reject_image'] ?>')">
                                        <i class="bi bi-image"></i> รูปหลักฐาน
                                    </button>
                                <?php endif; ?>
                                <?php if (empty($h['reject_reason']) && empty($h['reject_image'])): ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal ปฏิเสธคำขอ -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-dark border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>ระบุเหตุผลที่ไม่อนุมัติ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="approvals.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="reject_action">
                        <input type="hidden" name="trans_id" id="reject_trans_id">
                        <input type="hidden" name="decision" value="reject">

                        <div class="p-2 bg-light rounded border mb-3 small">
                            <div><strong>ผู้ขอ:</strong> <span id="reject_user_name"></span></div>
                            <div><strong>อุปกรณ์:</strong> <span id="reject_eq_name"></span></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">เหตุผลที่ไม่ผ่านการอนุมัติ <span class="text-danger">*</span></label>
                            <textarea name="reject_reason" class="form-control" rows="3" required placeholder="เช่น อุปกรณ์ชำรุดเสียหาย, ส่งคืนไม่ครบ, สภาพอุปกรณ์ไม่สมบูรณ์"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">อัปโหลดรูปภาพหลักฐาน (ถ้ามี)</label>
                            <input type="file" name="reject_image" class="form-control" accept="image/*">
                            <small class="text-muted">เช่น ถ่ายรูปอุปกรณ์ที่ชำรุด หรือชิ้นส่วนสูญหาย</small>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">ยืนยันปฏิเสธ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ดูรูปภาพหลักฐาน -->
    <div class="modal fade" id="viewRejectImgModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold">รูปภาพหลักฐานประกอบ</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 text-center">
                    <img id="previewRejectImg" src="" class="img-fluid rounded shadow-sm" style="max-height: 450px;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openRejectModal(transId, userName, eqName) {
            document.getElementById('reject_trans_id').value = transId;
            document.getElementById('reject_user_name').innerText = userName;
            document.getElementById('reject_eq_name').innerText = eqName;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        function viewRejectImage(src) {
            document.getElementById('previewRejectImg').src = src;
            new bootstrap.Modal(document.getElementById('viewRejectImgModal')).show();
        }
    </script>
</body>
</html>