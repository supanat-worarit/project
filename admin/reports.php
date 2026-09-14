<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

// รับค่าค้นหาและตัวกรองเวลา
$search = trim($_GET['search'] ?? '');
$report_type = $_GET['type'] ?? 'monthly';
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$conditions = [];
$params = [];

// 1. เงื่อนไขช่วงเวลา
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

// 2. เงื่อนไขค้นหาชื่อบุคคล หรือ รหัสนักศึกษา (ใช้ positional ? แยกตัว เพื่อป้องกันข้อผิดพลาด HY093)
if (!empty($search)) {
    $conditions[] = "(u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $kw = "%{$search}%";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// ดึงตัวเลขสรุปภาพรวม
$statSql = "
    SELECT 
        COUNT(*) as total_trans,
        SUM(CASE WHEN t.trans_status = 'borrowed' THEN 1 ELSE 0 END) as total_borrowed,
        SUM(CASE WHEN t.trans_status = 'returned' THEN 1 ELSE 0 END) as total_returned,
        SUM(CASE WHEN t.trans_status = 'rejected' THEN 1 ELSE 0 END) as total_rejected,
        SUM(CASE WHEN t.due_time < NOW() AND t.return_time IS NULL AND (t.trans_status = 'borrowed' OR t.trans_status = 'pending_return') THEN 1 ELSE 0 END) as total_overdue
    FROM transactions t
    LEFT JOIN user u ON t.user_id = u.user_id
    $whereClause
";
$stmtStat = $pdo->prepare($statSql);
$stmtStat->execute($params);
$summary = $stmtStat->fetch(PDO::FETCH_ASSOC);

// ดึงรายการตารางประวัติ
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

// หากมีการค้นหาระบุบุคคล จะดึงสถิติความถี่อุปกรณ์ที่คนนี้ยืม
$userEqStats = [];
if (!empty($search)) {
    $eqStatSql = "
        SELECT e.eq_name, COUNT(t.trans_id) as count_borrow
        FROM transactions t
        LEFT JOIN user u ON t.user_id = u.user_id
        LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
        $whereClause " . (!empty($whereClause) ? "AND" : "WHERE") . " (t.trans_status = 'borrowed' OR t.trans_status = 'returned')
        GROUP BY t.eq_id, e.eq_name
        ORDER BY count_borrow DESC
    ";
    $stmtEq = $pdo->prepare($eqStatSql);
    $stmtEq->execute($params);
    $userEqStats = $stmtEq->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายงานการยืม-คืน | ศูนย์กีฬา ม.อ.ตรัง</title>
    <style>
        @media print {
            .sidebar, .filter-card, .btn-print { display: none !important; }
            .main-content { padding: 0 !important; background: #fff !important; }
        }
        .card-summary {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 main-content" style="background-color: #f8fafc; min-height: 100vh;">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>สรุปรายงาน วัน/เดือน/ปี & ประวัติรายบุคคล
                </h4>
                <p class="text-muted small mb-0">ค้นหาประวัติการยืม-คืน ตรวจสอบรายการค้างส่ง และการไม่อนุมัติของนักศึกษา</p>
            </div>
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm btn-print d-flex align-items-center gap-1">
                <i class="bi bi-printer"></i> พิมพ์รายงาน
            </button>
        </div>

        <!-- กล่องค้นหาและตัวกรองเวลา -->
        <div class="card p-3 mb-4 filter-card border-0 shadow-sm bg-white">
            <form method="GET" action="reports.php" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-primary">
                        <i class="bi bi-person-search me-1"></i>ค้นหาชื่อ / รหัสนักศึกษา / อีเมล
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="เช่น ศุภณัฏฐ์ หรือ 6650110025" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">รูปแบบเวลา</label>
                    <select name="type" id="reportType" class="form-select" onchange="toggleFilterInputs()">
                        <option value="all" <?= $report_type === 'all' ? 'selected' : '' ?>>ประวัติทั้งหมด (All Time)</option>
                        <option value="daily" <?= $report_type === 'daily' ? 'selected' : '' ?>>รายวัน (Daily)</option>
                        <option value="monthly" <?= $report_type === 'monthly' ? 'selected' : '' ?>>รายเดือน (Monthly)</option>
                        <option value="yearly" <?= $report_type === 'yearly' ? 'selected' : '' ?>>รายปี (Yearly)</option>
                        <option value="custom" <?= $report_type === 'custom' ? 'selected' : '' ?>>กำหนดช่วงวันที่ (Custom)</option>
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
                    <label class="form-label small fw-semibold">ตั้งแต่ - ถึง วันที่</label>
                    <div class="input-group">
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-filter"></i> ค้นหา
                    </button>
                    <?php if (!empty($search) || $report_type !== 'monthly'): ?>
                        <a href="reports.php" class="btn btn-outline-secondary" title="ล้างค่า"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- กล่องสถิติพิเศษเฉพาะบุคคล -->
        <?php if (!empty($search)): ?>
            <div class="card p-3 mb-4 border-0 shadow-sm border-start border-4 border-primary bg-white">
                <h6 class="fw-bold mb-2 text-dark">
                    <i class="bi bi-person-check-fill text-primary me-2"></i>ผลการวิเคราะห์พฤติกรรมของ: <span class="text-primary"><?= htmlspecialchars($search) ?></span>
                </h6>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <span class="fw-bold small text-secondary d-block mb-2"><i class="bi bi-pie-chart me-1"></i> ความถี่การยืมอุปกรณ์แต่ละรายการ:</span>
                            <?php if (empty($userEqStats)): ?>
                                <span class="text-muted small">ไม่พบประวัติการยืมอุปกรณ์ในช่วงเวลานี้</span>
                            <?php else: ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($userEqStats as $eq): ?>
                                        <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light-subtle small">
                                            <span><?= htmlspecialchars($eq['eq_name']) ?></span>
                                            <span class="badge bg-primary-subtle text-primary fw-bold"><?= $eq['count_borrow'] ?> ครั้ง</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 h-100 border">
                            <span class="fw-bold small text-secondary d-block mb-2"><i class="bi bi-shield-exclamation me-1"></i> วินัยการยืมและการส่งคืน:</span>
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between align-items-center p-2 rounded <?= ($summary['total_overdue'] > 0) ? 'bg-warning-subtle text-warning-emphasis fw-bold' : 'bg-white text-muted' ?>">
                                    <span><i class="bi bi-clock-history me-1"></i> รายการที่กำลังค้างส่ง (เกินกำหนด):</span>
                                    <span><?= (int)$summary['total_overdue'] ?> รายการ</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded <?= ($summary['total_rejected'] > 0) ? 'bg-danger-subtle text-danger-emphasis fw-bold' : 'bg-white text-muted' ?>">
                                    <span><i class="bi bi-x-circle me-1"></i> รายการที่ถูกไม่อนุมัติ (Reject):</span>
                                    <span><?= (int)$summary['total_rejected'] ?> ครั้ง</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ตัวเลขสรุปภาพรวม -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card card-summary bg-primary text-white p-3">
                    <div class="small opacity-75">ทำรายการทั้งหมด</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_trans'] ?? 0) ?> <span class="fs-6 fw-normal">ครั้ง</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-summary bg-success text-white p-3">
                    <div class="small opacity-75">คืนเรียบร้อยแล้ว</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_returned'] ?? 0) ?> <span class="fs-6 fw-normal">รายการ</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-summary bg-warning text-dark p-3">
                    <div class="small fw-semibold opacity-75">ค้างส่ง / ส่งล่าช้า</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_overdue'] ?? 0) ?> <span class="fs-6 fw-normal">รายการ</span></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-summary bg-danger text-white p-3">
                    <div class="small opacity-75">ไม่อนุมัติ / ถูกยกเลิก</div>
                    <div class="fs-3 fw-bold mt-1"><?= number_format($summary['total_rejected'] ?? 0) ?> <span class="fs-6 fw-normal">รายการ</span></div>
                </div>
            </div>
        </div>

        <!-- ตารางข้อมูลประวัติ -->
        <div class="card p-0 overflow-hidden border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark">
                    ข้อมูลรายการยืม-คืน 
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary ms-1">ผู้ใช้: <?= htmlspecialchars($search) ?></span>
                    <?php endif; ?>
                </h6>
                <span class="small text-muted">พบข้อมูลทั้งหมด <?= count($reportData) ?> รายการ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัส</th>
                            <th>ผู้ยืม (นักศึกษา)</th>
                            <th>อุปกรณ์</th>
                            <th>วัน-เวลาที่ยืม</th>
                            <th>กำหนดส่งคืน</th>
                            <th>วัน-เวลาที่คืนจริง</th>
                            <th>สถานะ</th>
                            <th>หมายเหตุ / สาเหตุที่ไม่อนุมัติ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox text-secondary fs-1 d-block mb-2"></i>
                                    ไม่พบข้อมูลรายการยืม-คืนตามเงื่อนไขที่ระบุ
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($reportData as $row): 
                            $isOverdue = (!empty($row['due_time']) && strtotime($row['due_time']) < time() && empty($row['return_time']) && $row['trans_status'] === 'borrowed');
                        ?>
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
                            <td class="small"><?= htmlspecialchars($row['borrow_time'] ?? '-') ?></td>
                            <td class="small <?= $isOverdue ? 'text-danger fw-bold' : 'text-muted' ?>">
                                <?= htmlspecialchars($row['due_time'] ?? '-') ?>
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger ms-1">เกินกำหนด</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= htmlspecialchars($row['return_time'] ?? '-') ?></td>
                            <td>
                                <?php if ($row['trans_status'] === 'returned'): ?>
                                    <span class="badge bg-success">คืนแล้ว</span>
                                <?php elseif ($row['trans_status'] === 'borrowed'): ?>
                                    <span class="badge bg-primary">กำลังยืม</span>
                                <?php elseif ($row['trans_status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">ไม่อนุมัติ</span>
                                <?php elseif ($row['trans_status'] === 'pending_borrow'): ?>
                                    <span class="badge bg-warning text-dark">รออนุมัติยืม</span>
                                <?php elseif ($row['trans_status'] === 'pending_return'): ?>
                                    <span class="badge bg-info text-dark">รออนุมัติคืน</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($row['trans_status'] ?? '-') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['reject_reason'])): ?>
                                    <span class="small text-danger"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($row['reject_reason']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
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