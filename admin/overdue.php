<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

$search = trim($_GET['search'] ?? '');
$params = [];

// เงื่อนไข: ดึงรายการที่ค้างส่ง (trans_status เป็น overdue หรือ borrowed ที่เกินกำหนด due_time และยังไม่ส่งคืน)
$sql = "
    SELECT t.*, u.full_name, u.student_id, e.eq_code, e.eq_name,
           DATEDIFF(NOW(), t.due_time) AS days_overdue
    FROM transactions t
    LEFT JOIN user u ON t.user_id = u.user_id
    LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
    WHERE t.return_time IS NULL 
      AND (
          t.trans_status = 'overdue' 
          OR (t.due_time IS NOT NULL AND t.due_time < NOW() AND t.trans_status IN ('borrowed', 'pending_return'))
      )
";

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR e.eq_name LIKE ? OR e.eq_code LIKE ?)";
    $kw = "%{$search}%";
    $params = [$kw, $kw, $kw, $kw];
}
$sql .= " ORDER BY t.due_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$overdueList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผู้ค้างส่งอุปกรณ์กีฬา | ศูนย์กีฬา ม.อ.ตรัง</title>
    <style>
        @media print {
            .sidebar, .filter-card, .btn-print, .btn-back { 
                display: none !important; 
            }
            .main-content { 
                padding: 0 !important; 
                background: #ffffff !important; 
            }
        }
    </style>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 main-content" style="background-color: #f8fafc; min-height: 100vh;">
        <!-- Header ส่วนหัว -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>2. รายชื่อผู้ค้างส่งอุปกรณ์กีฬา
                </h4>
                <p class="text-muted small mb-0">รายการอุปกรณ์ที่เลยกำหนดส่งคืนและยังไม่ได้นำส่งคืนเข้าสู่ระบบ</p>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm btn-back">
                    <i class="bi bi-arrow-left"></i> กลับหน้าภาพรวม
                </a>
                <button onclick="window.print()" class="btn btn-outline-dark btn-sm btn-print">
                    <i class="bi bi-printer"></i> พิมพ์รายชื่อ
                </button>
            </div>
        </div>

        <!-- กล่องค้นหา -->
        <div class="card card-custom p-3 mb-4 filter-card border-0 shadow-sm bg-white">
            <form method="GET" action="overdue.php" class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหาชื่อ, รหัสนักศึกษา, อุปกรณ์..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-md-2">
                        <a href="overdue.php" class="btn btn-outline-secondary w-100">ล้างการค้นหา</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ตารางแสดงรายชื่อผู้ค้างส่ง -->
        <div class="card card-custom p-0 overflow-hidden border-0 shadow-sm bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">รายชื่อผู้ที่ยังไม่ส่งคืนอุปกรณ์</h6>
                <span class="badge bg-danger fs-6 px-3 py-1">ค้างส่งทั้งหมด <?= count($overdueList) ?> รายการ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสคำขอ</th>
                            <th>ผู้ยืม (นักศึกษา)</th>
                            <th>อุปกรณ์ที่ค้างส่ง</th>
                            <th>วันที่ยืม</th>
                            <th>กำหนดคืน</th>
                            <th class="pe-4">สถานะการล่าช้า</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overdueList)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                    ไม่มีรายการอุปกรณ์ค้างส่งในขณะนี้
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($overdueList as $row): ?>
                        <tr>
                            <td class="fw-semibold">#TR-<?= str_pad($row['trans_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name'] ?? 'ไม่ระบุชื่อ') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['student_id'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['eq_code'] ?? '-') ?></span>
                                <span class="ms-1"><?= htmlspecialchars($row['eq_name'] ?? '-') ?></span>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($row['borrow_time'] ?? '-') ?></td>
                            <td class="small text-danger fw-semibold"><?= htmlspecialchars($row['due_time'] ?? '-') ?></td>
                            <td class="pe-4">
                                <span class="badge bg-danger px-2.5 py-1.5">
                                    เกินกำหนด <?= max(1, (int)$row['days_overdue']) ?> วัน
                                </span>
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