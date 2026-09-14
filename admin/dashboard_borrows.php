<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

$search = trim($_GET['search'] ?? '');
$report_type = $_GET['type'] ?? 'all';
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$conditions = [];
$params = [];

// 1. กรองตามช่วงเวลา
if ($report_type === 'daily' && !empty($filter_date)) {
    $conditions[] = "DATE(t.borrow_time) = ?";
    $params[] = $filter_date;
} elseif ($report_type === 'monthly' && !empty($filter_month)) {
    $conditions[] = "DATE_FORMAT(t.borrow_time, '%Y-%m') = ?";
    $params[] = $filter_month;
} elseif ($report_type === 'yearly' && !empty($filter_year)) {
    $conditions[] = "DATE_FORMAT(t.borrow_time, '%Y') = ?";
    $params[] = $filter_year;
} elseif ($report_type === 'custom' && !empty($start_date) && !empty($end_date)) {
    $conditions[] = "DATE(t.borrow_time) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

// 2. กรองตามคำค้นหา: ค้นหาได้ทั้งชื่อนักศึกษา, รหัสนักศึกษา, รหัสอุปกรณ์, หรือชื่ออุปกรณ์
if (!empty($search)) {
    $conditions[] = "(u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ? OR e.eq_name LIKE ? OR e.eq_code LIKE ?)";
    $kw = "%{$search}%";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// ดึงตัวเลขสรุป
$statSql = "
    SELECT 
        COUNT(*) as total_trans,
        SUM(CASE WHEN t.trans_status = 'borrowed' THEN 1 ELSE 0 END) as total_borrowed,
        SUM(CASE WHEN t.trans_status = 'returned' THEN 1 ELSE 0 END) as total_returned,
        SUM(CASE WHEN t.trans_status = 'rejected' THEN 1 ELSE 0 END) as total_rejected,
        SUM(CASE 
            WHEN t.return_time IS NULL AND (
                t.trans_status = 'overdue' 
                OR (t.due_time IS NOT NULL AND t.due_time < NOW() AND t.trans_status IN ('borrowed', 'pending_return'))
            ) THEN 1 
            ELSE 0 
        END) as total_overdue
    FROM transactions t
    LEFT JOIN user u ON t.user_id = u.user_id
    LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
    $whereClause
";
$stmtStat = $pdo->prepare($statSql);
$stmtStat->execute($params);
$summary = $stmtStat->fetch(PDO::FETCH_ASSOC);

// ดึงรายการประวัติ
$listSql = "
    SELECT t.*, u.full_name, u.student_id, e.eq_code, e.eq_name
    FROM transactions t
    LEFT JOIN user u ON t.user_id = u.user_id
    LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
    $whereClause
    ORDER BY t.trans_id DESC
";
$stmtList = $pdo->prepare($listSql);
$stmtList->execute($params);
$reportData = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการยืมทั้งหมด & สรุปรายงาน | ศูนย์กีฬา ม.อ.ตรัง</title>
    <style>
        @media print {
            .sidebar, .filter-card, .btn-print, .btn-back { display: none !important; }
            .main-content { padding: 0 !important; background: #fff !important; }
        }
        .card-summary { border-radius: 12px; border: none; }
    </style>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 main-content" style="background-color: #f8fafc; min-height: 100vh;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-journal-text text-primary me-2"></i>1. รายการยืมอุปกรณ์ทั้งหมด & สรุปรายงาน
                </h4>
                <p class="text-muted small mb-0">ค้นหาประวัติการยืม-คืนตามบุคคล อุปกรณ์ หรือกรองตามช่วงเวลา วัน/เดือน/ปี</p>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm btn-back"><i class="bi bi-arrow-left"></i> กลับหน้าภาพรวม</a>
                <button onclick="window.print()" class="btn btn-outline-dark btn-sm btn-print"><i class="bi bi-printer"></i> พิมพ์รายงาน</button>
            </div>
        </div>

        <!-- กล่องค้นหา & ตัวกรอง -->
        <div class="card p-3 mb-4 filter-card border-0 shadow-sm bg-white">
            <form method="GET" action="dashboard_borrows.php" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-primary">
                        <i class="bi bi-search me-1"></i>ค้นหาชื่อผู้ยืม / รหัส / รหัสหรือชื่ออุปกรณ์
                    </label>
                    <input type="text" name="search" class="form-control" placeholder="เช่น ชื่อนักศึกษา, 6650110000, PSU-01-001 หรือ วอลเลย์" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">ช่วงเวลา</label>
                    <select name="type" id="reportType" class="form-select" onchange="toggleFilterInputs()">
                        <option value="all" <?= $report_type === 'all' ? 'selected' : '' ?>>ประวัติทั้งหมด (All)</option>
                        <option value="daily" <?= $report_type === 'daily' ? 'selected' : '' ?>>รายวัน (Daily)</option>
                        <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>รายเดือน (Monthly)</option>
                        <option value="yearly" <?= $report_type === 'yearly' ? 'selected' : '' ?>>รายปี (Yearly)</option>
                        <option value="custom" <?= $report_type === 'custom' ? 'selected' : '' ?>>กำหนดช่วงวัน (Custom)</option>
                    </select>
                </div>
                <div class="col-md-3 filter-input" id="dailyInput" style="<?= $report_type === 'daily' ? '' : 'display: none;' ?>">
                    <label class="form-label small fw-semibold">เลือกวันที่</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="col-md-3 filter-input" id="monthlyInput" style="<?= $report_type === 'monthly' ? '' : 'display: none;' ?>">
                    <label class="form-label small fw-semibold">เลือกเดือน</label>
                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filter_month) ?>">
                </div>
                <div class="col-md-3 filter-input" id="yearlyInput" style="<?= $report_type === 'yearly' ? '' : 'display: none;' ?>">
                    <label class="form-label small fw-semibold">เลือกปี (ค.ศ.)</label>
                    <input type="number" name="year" class="form-control" min="2020" max="2035" value="<?= htmlspecialchars($filter_year) ?>">
                </div>
                <div class="col-md-3 filter-input" id="customInput" style="<?= $report_type === 'custom' ? '' : 'display: none;' ?>">
                    <label class="form-label small fw-semibold">ตั้งแต่ - ถึง</label>
                    <div class="input-group">
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                    <?php if (!empty($search) || $report_type !== 'all'): ?>
                        <a href="dashboard_borrows.php" class="btn btn-outline-secondary" title="ล้างค่า"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- การ์ดตัวเลขสรุป -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-summary bg-primary text-white p-3 shadow-sm">
                    <div class="small opacity-75">ทำรายการทั้งหมด</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_trans'] ?? 0) ?> ครั้ง</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-summary bg-success text-white p-3 shadow-sm">
                    <div class="small opacity-75">คืนเรียบร้อยแล้ว</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_returned'] ?? 0) ?> รายการ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-summary bg-warning text-dark p-3 shadow-sm">
                    <div class="small fw-semibold opacity-75">ค้างส่ง / เกินกำหนด</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_overdue'] ?? 0) ?> รายการ</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-summary bg-danger text-white p-3 shadow-sm">
                    <div class="small opacity-75">ไม่อนุมัติ / ถูกปฏิเสธ</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_rejected'] ?? 0) ?> รายการ</div>
                </div>
            </div>
        </div>

        <!-- ตารางข้อมูล -->
        <div class="card p-0 overflow-hidden border-0 shadow-sm bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    รายการยืม-คืนอุปกรณ์
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary ms-2 fw-normal">คำค้นหา: <?= htmlspecialchars($search) ?></span>
                    <?php endif; ?>
                </h6>
                <span class="small text-muted">พบทั้งสิ้น <?= count($reportData) ?> รายการ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสคำขอ</th>
                            <th>ผู้ยืม (นักศึกษา)</th>
                            <th>อุปกรณ์</th>
                            <th>วัน-เวลาที่ยืม</th>
                            <th>กำหนดส่งคืน</th>
                            <th>วัน-เวลาที่คืน</th>
                            <th>สถานะรายการ</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-5">ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</td></tr>
                        <?php endif; ?>
                        <?php foreach ($reportData as $row): 
                            $isOverdue = (!empty($row['due_time']) && strtotime($row['due_time']) < time() && empty($row['return_time']) && in_array($row['trans_status'], ['borrowed', 'overdue']));
                        ?>
                        <tr>
                            <td class="fw-semibold">#TR-<?= str_pad($row['trans_id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['full_name'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['student_id'] ?? '-') ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['eq_code'] ?? '-') ?></span>
                                <span class="ms-1"><?= htmlspecialchars($row['eq_name'] ?? '-') ?></span>
                            </td>
                            <td class="small"><?= htmlspecialchars($row['borrow_time'] ?? '-') ?></td>
                            <td class="small <?= $isOverdue ? 'text-danger fw-bold' : 'text-muted' ?>">
                                <?= htmlspecialchars($row['due_time'] ?? '-') ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($row['return_time'] ?? '-') ?></td>
                            <td>
                                <?php if ($row['trans_status'] === 'returned'): ?>
                                    <span class="badge bg-success">คืนแล้ว</span>
                                <?php elseif ($row['trans_status'] === 'borrowed'): ?>
                                    <span class="badge bg-primary">กำลังยืม</span>
                                <?php elseif ($row['trans_status'] === 'overdue' || $isOverdue): ?>
                                    <span class="badge bg-warning text-dark">ค้างส่ง (เกินกำหนด)</span>
                                <?php elseif ($row['trans_status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">ไม่อนุมัติ</span>
                                <?php elseif ($row['trans_status'] === 'pending_borrow'): ?>
                                    <span class="badge bg-info text-dark">รออนุมัติยืม</span>
                                <?php elseif ($row['trans_status'] === 'pending_return'): ?>
                                    <span class="badge bg-info text-dark">รออนุมัติคืน</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($row['trans_status'] ?? '-') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-danger"><?= htmlspecialchars($row['reject_reason'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleFilterInputs() {
            const type = document.getElementById('reportType').value;
            document.querySelectorAll('.filter-input').forEach(el => el.style.display = 'none');
            if (type === 'daily') document.getElementById('dailyInput').style.display = 'block';
            else if (type === 'monthly') document.getElementById('monthlyInput').style.display = 'block';
            else if (type === 'yearly') document.getElementById('yearlyInput').style.display = 'block';
            else if (type === 'custom') document.getElementById('customInput').style.display = 'block';
        }
    </script>
</body>
</html>