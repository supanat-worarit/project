<?php
// admin/approvals.php
require_once '../config/auth_check.php';
require_once '../config/db.php';
require_once '../config/settings.php'; // นำเข้า LINE_ACCESS_TOKEN เพื่อใช้แจ้งเตือน

$admin_id = $_SESSION['admin_id'] ?? 1;

// ฟังก์ชันส่งข้อความ LINE แจ้งเตือนกลับไปยัง User
function sendLineMessage($to, $text) {
    if (empty(LINE_ACCESS_TOKEN)) {
        return; // ข้ามการส่งถ้ายังไม่ได้ตั้งค่า Token
    }
    $post_data = json_encode(['to' => $to, 'messages' => [['type' => 'text', 'text' => $text]]]);
    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json', 
        'Authorization: Bearer ' . LINE_ACCESS_TOKEN
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ==========================================
// ส่วนจัดการการกด อนุมัติ (Approve) / ไม่อนุมัติ (Reject)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $req_id = (int)$_POST['req_id'];
    $decision = $_POST['decision']; // 'approve' หรือ 'reject'

    try {
        $pdo->beginTransaction();
        
        // ค้นหาข้อมูลคำขอเพื่อนำมาประมวลผล (ใช้ FOR UPDATE ล็อก Row ไว้ป้องกันการอนุมัติซ้อนทับกัน)
        $stmtR = $pdo->prepare("
            SELECT a.*, u.line_user_id, e.eq_name, e.eq_code, t.due_time 
            FROM approval_requests a 
            JOIN user u ON a.user_id = u.user_id 
            JOIN sport_equipment e ON a.eq_id = e.eq_id 
            LEFT JOIN transactions t ON a.trans_id = t.trans_id
            WHERE a.req_id = ? FOR UPDATE
        ");
        $stmtR->execute([$req_id]);
        $req = $stmtR->fetch(PDO::FETCH_ASSOC);

        if ($req && $req['status'] === 'pending') {
            $lineId = $req['line_user_id'];
            $eqText = $req['eq_name'] . " (" . $req['eq_code'] . ")";
            $now = date('Y-m-d H:i:s');

            if ($decision === 'approve') {
                if ($req['req_type'] === 'borrow') {
                    // 1. กรณีอนุมัติการยืม
                    $borrowTime = $req['borrow_date'] . ' ' . date('H:i:s');
                    $dueTime = $req['due_date'] . ' 23:59:59';
                    
                    // สร้าง Transaction การยืมของจริง
                    $pdo->prepare("INSERT INTO transactions (eq_id, user_id, borrow_time, due_time, borrow_image, trans_status) VALUES (?, ?, ?, ?, ?, 'borrowed')")
                        ->execute([$req['eq_id'], $req['user_id'], $borrowTime, $dueTime, $req['evidence_image']]);
                    
                    // เปลี่ยนสถานะอุปกรณ์เป็นถูกยืม
                    $pdo->prepare("UPDATE sport_equipment SET status = 'borrowed' WHERE eq_id = ?")->execute([$req['eq_id']]);
                    
                    // แจ้งเตือน LINE
                    if($lineId) sendLineMessage($lineId, "✅ อนุมัติการยืมอุปกรณ์\nรายการ: {$eqText}\nกำหนดส่งคืน: " . date('d/m/Y', strtotime($req['due_date'])));

                } else {
                    // 2. กรณีอนุมัติการคืน
                    $isOverdue = strtotime($now) > strtotime($req['due_time']);
                    $newStatus = $isOverdue ? 'overdue' : 'returned';

                    // อัปเดตข้อมูลการคืนใน Transaction เดิม
                    $pdo->prepare("UPDATE transactions SET return_time = ?, return_image = ?, trans_status = ? WHERE trans_id = ?")
                        ->execute([$now, $req['evidence_image'], $newStatus, $req['trans_id']]);
                    
                    // เปลี่ยนสถานะอุปกรณ์กลับเป็นพร้อมใช้งาน
                    $pdo->prepare("UPDATE sport_equipment SET status = 'avaliable' WHERE eq_id = ?")->execute([$req['eq_id']]);

                    $msg = "✅ อนุมัติการคืนอุปกรณ์\nรายการ: {$eqText} สำเร็จเรียบร้อย";
                    
                    if ($isOverdue) {
                        // สร้างบิลค่าปรับหากคืนเกินกำหนด (วันละ 20 บาท)
                        $daysOver = max(1, ceil((strtotime($now) - strtotime($req['due_time'])) / 86400));
                        $fineAmount = $daysOver * 20.00;
                        $pdo->prepare("INSERT INTO equipment_fines (trans_id, price, payment_status, slipok_status) VALUES (?, ?, 'unpaid', 'pending')")->execute([$req['trans_id'], $fineAmount]);
                        $msg .= "\n\n⚠ พบรายการคืนเกินกำหนด!\nกรุณาชำระค่าปรับ\nจำนวน {$fineAmount} บาท \nข้อมูลการชำระเงิน\nธนาคาร: ไทยพาณิชย์\nเลขบัญชี: 8662438582\nชื่อบัญชี: นาย ศุภณัฐ วรฤทธิ์\nโดยการส่งรูปสลิปโอนเงินเข้ามาในแชทนี้";
                    }
                    // แจ้งเตือน LINE
                    if($lineId) sendLineMessage($lineId, $msg);
                }
                
                // อัปเดตสถานะคำขอเป็น Approved
                $pdo->prepare("UPDATE approval_requests SET status = 'approved', approve_by = ?, approve_datetime = NOW() WHERE req_id = ?")->execute([$admin_id, $req_id]);

            } elseif ($decision === 'reject') {
                // 3. กรณีปฏิเสธคำขอ
                $reason = trim($_POST['reject_reason'] ?? '');
                
                if ($req['req_type'] === 'borrow') {
                    // ถ้ายืมไม่ผ่าน ปลดล็อกอุปกรณ์ให้กลับมาว่างเหมือนเดิม
                    $pdo->prepare("UPDATE sport_equipment SET status = 'avaliable' WHERE eq_id = ?")->execute([$req['eq_id']]);
                } else {
                    // ถ้าคืนไม่ผ่าน (เช่น ของพัง) ให้ transaction กลับไปเป็นสถานะเดิม (กำลังยืม หรือ ค้างส่ง)
                    $isOverdue = time() > strtotime($req['due_time']);
                    $revertStatus = $isOverdue ? 'overdue' : 'borrowed';
                    $pdo->prepare("UPDATE transactions SET trans_status = ? WHERE trans_id = ?")->execute([$revertStatus, $req['trans_id']]);
                }

                // อัปเดตสถานะคำขอเป็น Rejected พร้อมใส่เหตุผล
                $pdo->prepare("UPDATE approval_requests SET status = 'rejected', reject_reason = ?, approve_by = ?, approve_datetime = NOW() WHERE req_id = ?")->execute([$reason, $admin_id, $req_id]);
                
                $reqTypeName = $req['req_type'] === 'borrow' ? 'ยืม' : 'คืน';
                if($lineId) sendLineMessage($lineId, "❌ คำขอ{$reqTypeName}อุปกรณ์ถูกปฏิเสธ\nรายการ: {$eqText}\nเหตุผล: {$reason}");
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: approvals.php");
    exit;
}

// ==========================================
// ดึงข้อมูลสำหรับแสดงผลบนตาราง
// ==========================================

// 1. ดึงคำขอที่รอการอนุมัติ (Pending)
$stmtPending = $pdo->query("
    SELECT a.*, u.full_name as user_name, u.student_id, e.eq_code, e.eq_name
    FROM approval_requests a
    JOIN user u ON a.user_id = u.user_id
    JOIN sport_equipment e ON a.eq_id = e.eq_id
    WHERE a.status = 'pending'
    ORDER BY a.req_datetime ASC
");
$pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// 2. ดึงประวัติคำขอที่ดำเนินการแล้ว (Approved / Rejected) 20 รายการล่าสุด
$adminList = $pdo->query("SELECT user_id, full_name FROM user")->fetchAll(PDO::FETCH_KEY_PAIR);
$stmtHistory = $pdo->query("
    SELECT a.*, u.full_name as user_name, u.student_id, e.eq_code, e.eq_name
    FROM approval_requests a
    JOIN user u ON a.user_id = u.user_id
    JOIN sport_equipment e ON a.eq_id = e.eq_id
    WHERE a.status IN ('approved', 'rejected')
    ORDER BY a.approve_datetime DESC 
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
                <p class="text-muted small mb-0">ตรวจสอบความถูกต้องของรูปถ่ายหลักฐาน ก่อนทำการอนุมัติ</p>
            </div>
        </div>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- ตารางที่ 1: รายการที่รออนุมัติ (Pending) -->
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
                            <th>ผู้ทำรายการ</th>
                            <th>รหัสและชื่ออุปกรณ์</th>
                            <th>ประเภทคำขอ</th>
                            <th>วัน-เวลาที่ส่งคำขอ</th>
                            <th class="text-end pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingRequests)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">ไม่มีรายการคำขอที่รออนุมัติในขณะนี้</td></tr>
                        <?php endif; ?>
                        <?php foreach ($pendingRequests as $p): ?>
                        <tr>
                            <td class="fw-semibold">#REQ-<?= str_pad($p['req_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($p['user_name'] ?? 'ไม่ระบุชื่อ') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($p['student_id'] ?? '-') ?></small>
                            </td>
                            <td>
                                <!-- แสดงรหัสอุปกรณ์และชื่อ -->
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['eq_code'] ?? '-') ?></span>
                                <div class="small mt-1"><?= htmlspecialchars($p['eq_name'] ?? '-') ?></div>
                            </td>
                            <td>
                                <?php if ($p['req_type'] === 'borrow'): ?>
                                    <span class="badge bg-info text-dark px-2 py-1"><i class="bi bi-box-arrow-up-right me-1"></i>ขอยืม</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-box-arrow-in-left me-1"></i>ขอคืน</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($p['req_datetime'] ?? '-') ?></td>
                            <td class="text-end pe-4">
                                <!-- ปุ่มดูรูปภาพหลักฐาน -->
                                <button type="button" class="btn btn-sm btn-outline-info px-2 fw-medium me-1" onclick="viewEvidenceImage('<?= htmlspecialchars($p['evidence_image']) ?>')">
                                    <i class="bi bi-image"></i> รูปหลักฐาน
                                </button>
                                
                                <!-- ปุ่มอนุมัติ -->
                                <form action="approvals.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันอนุมัติคำขอนี้ใช่หรือไม่?');">
                                    <input type="hidden" name="action" value="approve_action">
                                    <input type="hidden" name="req_id" value="<?= $p['req_id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="button" class="btn btn-sm btn-success px-2 fw-medium me-1" onclick="openApproveModal('<?= $p['req_id'] ?>', '<?= htmlspecialchars($p['user_name'] ?? '') ?>', '<?= htmlspecialchars($p['eq_code'] . ' - ' . $p['eq_name']) ?>')">
                                        <i class="bi bi-check-lg"></i> อนุมัติ
                                    </button>
                                </form>

                                <!-- ปุ่มปฏิเสธ (เรียก Modal) -->
                                <button type="button" class="btn btn-sm btn-outline-danger px-2 fw-medium" onclick="openRejectModal('<?= $p['req_id'] ?>', '<?= htmlspecialchars($p['user_name'] ?? '') ?>', '<?= htmlspecialchars($p['eq_code'] . ' - ' . $p['eq_name']) ?>')">
                                    <i class="bi bi-x-lg"></i> ปฏิเสธ
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ตารางที่ 2: ประวัติการดำเนินการล่าสุด (History) -->
        <div class="card card-custom p-0 overflow-hidden border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-secondary"><i class="bi bi-clock-history me-2"></i>ประวัติการอนุมัติ 20 รายการล่าสุด</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสคำขอ</th>
                            <th>ผู้ทำรายการ</th>
                            <th>รหัสและชื่ออุปกรณ์</th>
                            <th>ประเภทคำขอ</th>
                            <th>สถานะ</th>
                            <th>ผู้อนุมัติ</th>
                            <th>รูปหลักฐาน / หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historyList)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีประวัติการอนุมัติ</td></tr>
                        <?php endif; ?>
                        <?php foreach ($historyList as $h): 
                            $approverName = $adminList[$h['approve_by']] ?? 'เจ้าหน้าที่';
                        ?>
                        <tr>
                            <td class="text-muted small">#REQ-<?= str_pad($h['req_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td class="small fw-semibold"><?= htmlspecialchars($h['user_name'] ?? '-') ?></td>
                            <td>
                                <!-- แสดงรหัสอุปกรณ์และชื่อ -->
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($h['eq_code'] ?? '-') ?></span>
                                <div class="small mt-1"><?= htmlspecialchars($h['eq_name'] ?? '-') ?></div>
                            </td>
                            <td><?= $h['req_type'] === 'borrow' ? 'ขอยืม' : 'ขอคืน' ?></td>
                            <td>
                                <?php if ($h['status'] === 'approved'): ?>
                                    <span class="badge bg-success">อนุมัติแล้ว</span>
                                <?php elseif ($h['status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">ไม่อนุมัติ</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-primary"><?= htmlspecialchars($approverName) ?><br><small class="text-muted"><?= htmlspecialchars($h['approve_datetime'] ?? '-') ?></small></td>
                            <td>
                                <!-- ปุ่มดูรูปภาพหลักฐานในประวัติ -->
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1" onclick="viewEvidenceImage('<?= htmlspecialchars($h['evidence_image']) ?>')">
                                    <i class="bi bi-image"></i> รูปหลักฐาน
                                </button>
                                
                                <?php if ($h['status'] === 'rejected' && !empty($h['reject_reason'])): ?>
                                    <div class="small text-danger mt-1">เหตุผล: <?= htmlspecialchars($h['reject_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================== MODALS ============================== -->

    <!-- 1. Modal แสดงรูปภาพหลักฐาน (Evidence Image) -->
    <div class="modal fade" id="viewEvidenceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h6 class="modal-title fw-bold text-dark"><i class="bi bi-image me-2"></i>รูปภาพหลักฐานการทำรายการ</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3 text-center bg-light">
                    <!-- รูปจะถูกเปลี่ยน src ผ่าน Javascript โดยอ้างอิง path ไปที่โฟลเดอร์ user/uploads/ -->
                    <img id="previewEvidenceImg" src="" class="img-fluid rounded shadow-sm" style="max-height: 500px; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Modal ระบุเหตุผลการปฏิเสธคำขอ (Reject Request) -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-dark border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>ระบุเหตุผลที่ไม่อนุมัติ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="approvals.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="reject_action">
                        <input type="hidden" name="req_id" id="reject_req_id">
                        <input type="hidden" name="decision" value="reject">

                        <div class="p-3 bg-light rounded border mb-3 small">
                            <div class="mb-1"><strong>ผู้ขอทำรายการ:</strong> <span id="reject_user_name" class="text-primary"></span></div>
                            <div><strong>อุปกรณ์:</strong> <span id="reject_eq_name" class="text-dark"></span></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">เหตุผลที่ไม่ผ่านการอนุมัติ <span class="text-danger">*</span></label>
                            <textarea name="reject_reason" class="form-control" rows="3" required placeholder="เช่น ถ่ายรูปบัตรนักศึกษาไม่ชัดเจน, สภาพอุปกรณ์ชำรุด, ถ่ายรูปผิดอุปกรณ์"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold">ยืนยันการปฏิเสธ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. Modal ยืนยันการอนุมัติคำขอ (Approve Request) -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-dark border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="fw-bold mb-0 text-success"><i class="bi bi-check-circle-fill me-2"></i>ยืนยันการอนุมัติ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="approvals.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="approve_action">
                        <input type="hidden" name="req_id" id="approve_req_id">
                        <input type="hidden" name="decision" value="approve">

                        <div class="p-3 bg-light rounded border mb-3 small">
                            <div class="mb-1"><strong>ผู้ขอทำรายการ:</strong> <span id="approve_user_name" class="text-primary"></span></div>
                            <div><strong>อุปกรณ์:</strong> <span id="approve_eq_name" class="text-dark"></span></div>
                        </div>

                        <p class="mb-0 text-center fw-medium fs-6 mt-4">คุณต้องการอนุมัติรายการนี้ใช่หรือไม่?</p>
                        <p class="text-muted small text-center mt-1">ระบบจะบันทึกข้อมูลและส่งแจ้งเตือนไปยังผู้ใช้</p>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold">ยืนยันการอนุมัติ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันเปิด Modal สำหรับแสดงรูปภาพหลักฐาน
        function viewEvidenceImage(imageName) {
            // อ้างอิง Path รูปไปที่โฟลเดอร์ฝั่ง User ที่ใช้อัปโหลด
            const basePath = '../user/uploads/';
            document.getElementById('previewEvidenceImg').src = basePath + imageName;
            new bootstrap.Modal(document.getElementById('viewEvidenceModal')).show();
        }

        // ฟังก์ชันเปิด Modal สำหรับกรอกเหตุผลปฏิเสธคำขอ
        function openRejectModal(reqId, userName, eqDetails) {
            document.getElementById('reject_req_id').value = reqId;
            document.getElementById('reject_user_name').innerText = userName;
            document.getElementById('reject_eq_name').innerText = eqDetails;
            new bootstrap.Modal(document.getElementById('rejectModal')).show();
        }

        // ฟังก์ชันเปิด Modal สำหรับยืนยันการอนุมัติ
        function openApproveModal(reqId, userName, eqDetails) {
            document.getElementById('approve_req_id').value = reqId;
            document.getElementById('approve_user_name').innerText = userName;
            document.getElementById('approve_eq_name').innerText = eqDetails;
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        }
    </script>
</body>
</html>