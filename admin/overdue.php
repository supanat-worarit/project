<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

$search = trim($_GET['search'] ?? '');
$params = [];

// เงื่อนไข: ค้างส่ง (ยังไม่คืนและเลยเวลา) หรือ ค้างชำระค่าปรับ (payment_status = unpaid)
// DATEDIFF คำนวณจำนวนวันต่าง
$sql = "
    SELECT t.*, u.full_name, u.student_id, e.eq_code, e.eq_name,
           DATEDIFF(NOW(), t.due_time) AS days_overdue,
           f.price AS fine_amount, f.payment_status
    FROM transactions t
    LEFT JOIN user u ON t.user_id = u.user_id
    LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
    LEFT JOIN equipment_fines f ON t.trans_id = f.trans_id
    WHERE (
        (t.return_time IS NULL AND (t.trans_status = 'overdue' OR (t.due_time IS NOT NULL AND t.due_time < NOW() AND t.trans_status IN ('borrowed', 'pending_return'))))
        OR (f.payment_status = 'unpaid')
    )
";

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR e.eq_name LIKE ? OR e.eq_code LIKE ?)";
    $kw = "%{$search}%";
    $params = [$kw, $kw, $kw, $kw];
}
$sql .= " ORDER BY f.payment_status DESC, t.due_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$overdueList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผู้ค้างส่งและค้างชำระค่าปรับ | ศูนย์กีฬา ม.อ.ตรัง</title>
    <style>@media print { .sidebar, .filter-card, .btn-print, .btn-back { display: none !important; } .main-content { padding: 0 !important; background: #ffffff !important; } }</style>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>
    <div class="flex-grow-1 p-4 main-content" style="background-color: #f8fafc; min-height: 100vh;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>2. รายชื่อผู้ค้างส่งอุปกรณ์ & ค้างชำระค่าปรับ</h4>
                <p class="text-muted small mb-0">แสดงรายการล่าช้า และนักศึกษาที่ยังไม่โอนจ่ายค่าปรับ</p>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm btn-back"><i class="bi bi-arrow-left"></i> กลับหน้าภาพรวม</a>
                <button onclick="window.print()" class="btn btn-outline-dark btn-sm btn-print"><i class="bi bi-printer"></i> พิมพ์รายชื่อ</button>
            </div>
        </div>

        <div class="card card-custom p-3 mb-4 filter-card border-0 shadow-sm bg-white">
            <form method="GET" action="overdue.php" class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหาชื่อ, รหัสนักศึกษา, อุปกรณ์..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">ค้นหา</button></div>
                <?php if (!empty($search)): ?>
                    <div class="col-md-2"><a href="overdue.php" class="btn btn-outline-secondary w-100">ล้างการค้นหา</a></div>
                <?php endif; ?>
            </form>
        </div>

        <div class="card card-custom p-0 overflow-hidden border-0 shadow-sm bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">รายชื่อผู้ที่ยังไม่ส่งคืนอุปกรณ์ / ค้างชำระค่าปรับ</h6>
                <span class="badge bg-danger fs-6 px-3 py-1">มีรายการทั้งหมด <?= count($overdueList) ?> รายการ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>ผู้ทำรายการ</th>
                            <th>รายการอุปกรณ์</th>
                            <th>ข้อมูลเวลา</th>
                            <th>สถานะรายการ</th>
                            <th class="pe-4 text-end">ค่าปรับ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overdueList)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-5">ไม่มีรายการอุปกรณ์ค้างส่งหรือค้างชำระในขณะนี้</td></tr>
                        <?php endif; ?>
                        <?php foreach ($overdueList as $row): 
                            $daysLate = max(1, (int)$row['days_overdue']);
                            $isReturnedUnpaid = ($row['trans_status'] === 'returned' && $row['payment_status'] === 'unpaid');
                            
                            // จัดการแสดงผลวันเวลา
                            if ($isReturnedUnpaid) {
                                $timeDisplay = '<div class="small text-muted">กำหนด: '.date('d/m/Y H:i', strtotime($row['due_time'])).'</div>';
                                $timeDisplay .= '<div class="small text-success fw-semibold mt-1">คืนแล้ว: '.date('d/m/Y H:i', strtotime($row['return_time'])).'</div>';
                            } else {
                                $timeDisplay = '<div class="small text-danger fw-bold">กำหนด: '.date('d/m/Y H:i', strtotime($row['due_time'])).'</div>';
                            }

                            // จัดการแสดงผลสถานะป้ายเตือน
                            if ($isReturnedUnpaid) {
                                $statusBadge = '<span class="badge bg-warning text-dark px-2.5 py-1.5 border border-warning">คืนแล้ว (รอจ่ายค่าปรับ)</span>';
                            } else {
                                $statusBadge = '<span class="badge bg-danger px-2.5 py-1.5">อุปกรณ์ค้างส่ง '.$daysLate.' วัน</span>';
                            }

                            // จัดการแสดงผลจำนวนเงิน
                            if (!empty($row['payment_status']) && $row['payment_status'] === 'unpaid') {
                                $fineDisplay = number_format($row['fine_amount'], 2) . ' ฿';
                                $fineNote = '<div class="badge bg-danger bg-opacity-10 text-danger border border-danger mt-1" style="font-size: 10px;">ค้างชำระ</div>';
                            } else {
                                $estFine = $daysLate * 20; // วันละ 20 บาท
                                $fineDisplay = '~' . number_format($estFine, 2) . ' ฿';
                                $fineNote = '<div class="text-muted small mt-1" style="font-size: 10px;">(ค้างชำระ)</div>';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name'] ?? 'ไม่ระบุชื่อ') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['student_id'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['eq_code'] ?? '-') ?></span>
                                <div class="ms-1 small mt-1"><?= htmlspecialchars($row['eq_name'] ?? '-') ?></div>
                            </td>
                            <td><?= $timeDisplay ?></td>
                            <td><?= $statusBadge ?></td>
                            <td class="pe-4 text-end">
                                <div class="fw-bold text-danger fs-6"><?= $fineDisplay ?></div>
                                <?= $fineNote ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>