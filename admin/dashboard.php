<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

// รับค่าตัวกรอง รายเดือน / รายปี จากมุมขวาบน
$filter_mode = $_GET['mode'] ?? 'monthly';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_year = $_GET['year'] ?? date('Y');

// กำหนดเงื่อนไข SQL ตามโหมดที่เลือก
if ($filter_mode === 'yearly') {
    $timeSql = "DATE_FORMAT(t.borrow_time, '%Y') = ?";
    $timeParam = $selected_year;
    $displayPeriodText = "ประจำปี " . ($selected_year + 543);
    $cardTitlePeriod = "1. รายการยืมทั้งหมดปีนี้";
} else {
    $timeSql = "DATE_FORMAT(t.borrow_time, '%Y-%m') = ?";
    $timeParam = $selected_month;
    $timeObj = strtotime($selected_month . "-01");
    $displayPeriodText = "ประจำเดือน " . date('m/', $timeObj) . (date('Y', $timeObj) + 543);
    $cardTitlePeriod = "1. รายการยืมทั้งหมดเดือนนี้";
}

// 1. สถิติการยืมทั้งหมดตามช่วงเวลาที่เลือก
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE {$timeSql}");
$stmtTotal->execute([$timeParam]);
$totalPeriodBorrows = $stmtTotal->fetchColumn();

// 2. จำนวนคนที่ค้างส่ง / รายการค้างส่ง
$stmtOverdue = $pdo->query("
    SELECT 
        COUNT(DISTINCT user_id) as total_users_overdue,
        COUNT(trans_id) as total_items_overdue
    FROM transactions 
    WHERE return_time IS NULL 
      AND (
          trans_status = 'overdue' 
          OR (due_time IS NOT NULL AND due_time < NOW() AND trans_status IN ('borrowed', 'pending_return'))
      )
");
$overdueData = $stmtOverdue->fetch(PDO::FETCH_ASSOC);
$totalOverdueUsers = $overdueData['total_users_overdue'] ?? 0;
$totalOverdueItems = $overdueData['total_items_overdue'] ?? 0;

// 3. ยอดรวมค่าปรับที่ได้รับแล้ว (SlipOK Verified) ตามช่วงเวลา
$stmtFines = $pdo->prepare("
    SELECT SUM(ef.price) 
    FROM equipment_fines ef
    JOIN transactions t ON ef.trans_id = t.trans_id
    WHERE ef.payment_status = 'paid' AND {$timeSql}
");
$stmtFines->execute([$timeParam]);
$totalFines = $stmtFines->fetchColumn() ?? 0;

// 4. จำนวนอุปกรณ์ที่ชำรุด / ซ่อมบำรุง
$stmtDamaged = $pdo->query("SELECT COUNT(*) FROM sport_equipment WHERE status IN ('damaged', 'maintenance')");
$totalDamaged = $stmtDamaged->fetchColumn();

// 5. สถิติอุปกรณ์ที่ถูกยืมบ่อย Top 5 ตามช่วงเวลา
$stmtTopEq = $pdo->prepare("
    SELECT e.eq_name, COUNT(t.trans_id) as borrow_count 
    FROM transactions t
    JOIN sport_equipment e ON t.eq_id = e.eq_id
    WHERE {$timeSql}
    GROUP BY t.eq_id, e.eq_name
    ORDER BY borrow_count DESC
    LIMIT 5
");
$stmtTopEq->execute([$timeParam]);
$topEquipment = $stmtTopEq->fetchAll(PDO::FETCH_ASSOC);

// 6. ดึงข้อมูลทำกราฟ (ข้อมูลจริง) แยกตามชั้นปีนักศึกษา
$current_academic_year = date('Y') + 543; 
$stmtChart = $pdo->prepare("
    SELECT 
        c.category_name,
        u.enrollment_year,
        COUNT(t.trans_id) as borrow_count
    FROM transactions t
    JOIN user u ON t.user_id = u.user_id
    JOIN sport_equipment e ON t.eq_id = e.eq_id
    JOIN sport_categories c ON e.category_id = c.category_id
    WHERE {$timeSql} AND u.enrollment_year IS NOT NULL
    GROUP BY c.category_id, u.enrollment_year
");
$stmtChart->execute([$timeParam]);
$chartDataRaw = $stmtChart->fetchAll(PDO::FETCH_ASSOC);

$categories = [];
$year1 = []; $year2 = []; $year3 = []; $year4 = [];

foreach ($chartDataRaw as $row) {
    $cat = $row['category_name'];
    if (!in_array($cat, $categories)) $categories[] = $cat;
    
    $student_year = $current_academic_year - (int)$row['enrollment_year'] + 1;
    
    if ($student_year == 1) $year1[$cat] = ($year1[$cat] ?? 0) + $row['borrow_count'];
    elseif ($student_year == 2) $year2[$cat] = ($year2[$cat] ?? 0) + $row['borrow_count'];
    elseif ($student_year == 3) $year3[$cat] = ($year3[$cat] ?? 0) + $row['borrow_count'];
    else $year4[$cat] = ($year4[$cat] ?? 0) + $row['borrow_count'];
}

$y1Data = []; $y2Data = []; $y3Data = []; $y4Data = [];
foreach ($categories as $cat) {
    $y1Data[] = $year1[$cat] ?? 0;
    $y2Data[] = $year2[$cat] ?? 0;
    $y3Data[] = $year3[$cat] ?? 0;
    $y4Data[] = $year4[$cat] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ภาพรวมศูนย์กีฬา ม.อ.ตรัง</title>
    <style>
        .stat-card {
            border-radius: 14px; color: #ffffff; padding: 22px 24px; border: none;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06); transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: block; text-decoration: none;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12); color: #ffffff; }
        .stat-blue { background: linear-gradient(135deg, #0d6efd, #0056b3); }
        .stat-yellow { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-green { background: linear-gradient(135deg, #10b981, #047857); }
        .stat-red { background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .card-custom { border-radius: 14px; background: #ffffff; border: none; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04); }
        .rank-circle { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
    </style>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4" style="background-color: #f8fafc; min-height: 100vh;">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold mb-1 text-dark">ภาพรวมและสถิติการยืม-คืน <?= $displayPeriodText ?></h4>
                <p class="text-muted small mb-0">ศูนย์กีฬา มหาวิทยาลัยสงขลานครินทร์ วิทยาเขตตรัง</p>
            </div>

            <form method="GET" action="dashboard.php" class="bg-white p-2 rounded-3 border shadow-sm d-flex align-items-center gap-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0"><i class="bi bi-calendar3 text-primary"></i></span>
                    <select name="mode" id="filterMode" class="form-select border-0 bg-light fw-medium" onchange="toggleFilterMode()">
                        <option value="monthly" <?= $filter_mode === 'monthly' ? 'selected' : '' ?>>รายเดือน</option>
                        <option value="yearly" <?= $filter_mode === 'yearly' ? 'selected' : '' ?>>รายปี</option>
                    </select>
                </div>
                <div id="monthSelector" style="<?= $filter_mode === 'monthly' ? '' : 'display: none;' ?>">
                    <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($selected_month) ?>" onchange="this.form.submit()">
                </div>
                <div id="yearSelector" style="<?= $filter_mode === 'yearly' ? '' : 'display: none;' ?>">
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php 
                        $currY = (int)date('Y');
                        for ($y = $currY; $y >= $currY - 5; $y--): 
                        ?>
                            <option value="<?= $y ?>" <?= $selected_year == $y ? 'selected' : '' ?>>ปี <?= $y + 543 ?> (<?= $y ?>)</option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Metric Stat Cards เปลี่ยนเป็น 4 คอลัมน์ -->
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <a href="dashboard_borrows.php?type=<?= $filter_mode ?>&<?= $filter_mode === 'monthly' ? 'month='.$selected_month : 'year='.$selected_year ?>" class="stat-card stat-blue">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small fw-light opacity-75"><?= $cardTitlePeriod ?></div>
                        <i class="bi bi-arrow-right-circle fs-5 opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mt-2">
                        <span class="fs-1 fw-bold"><?= number_format($totalPeriodBorrows) ?></span>
                        <span class="fs-6">รายการ</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="overdue.php" class="stat-card stat-yellow">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small fw-light opacity-75">2. ผู้ค้างส่ง / เกินกำหนด</div>
                        <i class="bi bi-arrow-right-circle fs-5 opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mt-2">
                        <span class="fs-1 fw-bold"><?= number_format($totalOverdueUsers) ?></span>
                        <span class="fs-6">ราย</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="dashboard_damaged.php" class="stat-card stat-red">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small fw-light opacity-75">3. อุปกรณ์ชำรุด/ส่งซ่อม</div>
                        <i class="bi bi-tools fs-5 opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mt-2">
                        <span class="fs-1 fw-bold"><?= number_format($totalDamaged) ?></span>
                        <span class="fs-6">ชิ้น</span>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="#" class="stat-card stat-green">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small fw-light opacity-75">4. รายได้ค่าปรับ (SlipOK)</div>
                        <i class="bi bi-cash-coin fs-5 opacity-75"></i>
                    </div>
                    <div class="d-flex align-items-baseline gap-2 mt-2">
                        <span class="fs-1 fw-bold"><?= number_format($totalFines, 0) ?></span>
                        <span class="fs-6">บาท</span>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-5">
                <div class="card card-custom p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-graph-up-arrow me-2"></i>อุปกรณ์ที่ถูกยืมบ่อย (Top 5)</h6>
                        <span class="badge bg-light text-muted border"><?= $displayPeriodText ?></span>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <?php if (empty($topEquipment)): ?>
                            <li class="text-muted small py-4 text-center">ไม่มีข้อมูลการยืมในช่วงเวลานี้</li>
                        <?php else: ?>
                            <?php foreach ($topEquipment as $idx => $item): ?>
                                <li class="d-flex justify-content-between align-items-center py-2.5 <?= ($idx < count($topEquipment) - 1) ? 'border-bottom' : ''; ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="rank-circle bg-light text-primary border"><?= $idx + 1 ?></span>
                                        <span class="fw-medium text-dark"><?= htmlspecialchars($item['eq_name']) ?></span>
                                    </div>
                                    <span class="badge bg-primary text-white rounded-pill px-3 py-1.5 fw-semibold">
                                        <?= $item['borrow_count'] ?> ครั้ง
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card card-custom p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>สถิติการยืมแยกตามชั้นปีนักศึกษา</h6>
                            <small class="text-muted">เปรียบเทียบตามหมวดหมู่อุปกรณ์กีฬา</small>
                        </div>
                    </div>
                    <div style="height: 280px;">
                        <?php if(empty($categories)): ?>
                            <div class="h-100 d-flex align-items-center justify-content-center text-muted">ไม่พบข้อมูลการยืม</div>
                        <?php else: ?>
                            <canvas id="yearComparisonChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function toggleFilterMode() {
            const mode = document.getElementById('filterMode').value;
            const monthBox = document.getElementById('monthSelector');
            const yearBox = document.getElementById('yearSelector');
            if (mode === 'yearly') { monthBox.style.display = 'none'; yearBox.style.display = 'block'; } 
            else { monthBox.style.display = 'block'; yearBox.style.display = 'none'; }
        }

        <?php if(!empty($categories)): ?>
        const ctxBar = document.getElementById('yearComparisonChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($categories) ?>,
                datasets: [
                    { label: 'ปี 1', data: <?= json_encode($y1Data) ?>, backgroundColor: '#00b4d8' },
                    { label: 'ปี 2', data: <?= json_encode($y2Data) ?>, backgroundColor: '#10b981' },
                    { label: 'ปี 3', data: <?= json_encode($y3Data) ?>, backgroundColor: '#f59e0b' },
                    { label: 'ปี 4+', data: <?= json_encode($y4Data) ?>, backgroundColor: '#a855f7' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { boxWidth: 14, font: { family: 'Prompt', size: 12 } } } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>