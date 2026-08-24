<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

// 1. รายการยืมทั้งหมดเดือนนี้
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE MONTH(borrow_time) = MONTH(CURRENT_DATE()) AND YEAR(borrow_time) = YEAR(CURRENT_DATE())");
$total_borrow_month = $stmt->fetchColumn();

// 2. รายชื่อผู้ค้างส่งอุปกรณ์
$stmt = $pdo->query("
    SELECT t.*, u.full_name, e.eq_name, DATEDIFF(NOW(), t.due_time) as overdue_days 
    FROM transactions t
    JOIN user u ON t.user_id = u.user_id
    JOIN sport_equipment e ON t.eq_id = e.eq_id
    WHERE t.trans_status = 'overdue' AND t.due_time < NOW()
");
$overdue_list = $stmt->fetchAll();
$total_overdue = count($overdue_list);

// 3. อุปกรณ์แจ้งชำรุด
$stmt = $pdo->query("SELECT COUNT(*) FROM sport_equipment WHERE status = 'damaged'");
$damaged_count = $stmt->fetchColumn();

// 4. สถิติตารางอุปกรณ์ที่ถูกยืมบ่อย Top 3
$stmt = $pdo->query("
    SELECT c.category_name, COUNT(t.trans_id) as total_borrow
    FROM transactions t
    JOIN sport_equipment e ON t.eq_id = e.eq_id
    JOIN sport_categories c ON e.category_id = c.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY total_borrow DESC
    LIMIT 3
");
$top_borrowed = $stmt->fetchAll();

// 5. สถิติตามชั้นปี 1-4 (คำนวณจาก enrollment_year)
$current_year_th = (int)date("Y") + 543;
$stmt = $pdo->query("
    SELECT 
        c.category_name,
        SUM(CASE WHEN ($current_year_th - u.enrollment_year + 1) = 1 THEN 1 ELSE 0 END) AS y1,
        SUM(CASE WHEN ($current_year_th - u.enrollment_year + 1) = 2 THEN 1 ELSE 0 END) AS y2,
        SUM(CASE WHEN ($current_year_th - u.enrollment_year + 1) = 3 THEN 1 ELSE 0 END) AS y3,
        SUM(CASE WHEN ($current_year_th - u.enrollment_year + 1) >= 4 THEN 1 ELSE 0 END) AS y4
    FROM transactions t
    JOIN user u ON t.user_id = u.user_id
    JOIN sport_equipment e ON t.eq_id = e.eq_id
    JOIN sport_categories c ON e.category_id = c.category_id
    GROUP BY c.category_id, c.category_name
");
$year_stats = $stmt->fetchAll();

$categories_labels = [];
$y1_data = []; $y2_data = []; $y3_data = []; $y4_data = [];

foreach($year_stats as $row) {
    $categories_labels[] = $row['category_name'];
    $y1_data[] = (int)$row['y1'];
    $y2_data[] = (int)$row['y2'];
    $y3_data[] = (int)$row['y3'];
    $y4_data[] = (int)$row['y4'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ภาพรวมและสถิติการยืม-คืนประจำเดือน</title>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 overflow-auto" style="height: 100vh;">
        <h5 class="fw-bold mb-4">ภาพรวมและสถิติการยืม-คืนประจำเดือน</h5>

        <!-- Top Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card card-custom p-3 bg-primary text-white">
                    <small>รายการยืมทั้งหมดเดือนนี้</small>
                    <h2 class="fw-bold mt-2"><?= $total_borrow_month ?> <span class="fs-5">รายการ</span></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom p-3 bg-warning text-dark">
                    <small>รายชื่อผู้ค้างส่งอุปกรณ์</small>
                    <h2 class="fw-bold mt-2"><?= $total_overdue ?> <span class="fs-5">ราย</span></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-custom p-3 bg-danger text-white">
                    <small>อุปกรณ์แจ้งชำรุด</small>
                    <h2 class="fw-bold mt-2"><?= $damaged_count ?> <span class="fs-5">ชิ้น</span></h2>
                </div>
            </div>
        </div>

        <!-- Middle Section -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card card-custom p-3 h-100">
                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-graph-up"></i> สถิติตารางอุปกรณ์ที่ถูกยืมบ่อย</h6>
                    <ul class="list-group list-group-flush">
                        <?php if(empty($top_borrowed)): ?>
                            <li class="list-group-item text-muted">ยังไม่มีประวัติการยืม</li>
                        <?php endif; ?>
                        <?php foreach($top_borrowed as $index => $top): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?= ($index + 1) . ". " . htmlspecialchars($top['category_name']) ?></span>
                            <span class="badge bg-primary rounded-pill"><?= $top['total_borrow'] ?> ครั้ง</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-custom p-3 h-100">
                    <h6 class="fw-bold text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> รายชื่อผู้ค้างส่งคืน</h6>
                    <table class="table table-sm">
                        <thead class="text-muted">
                            <tr>
                                <th>ชื่อ-สกุล</th>
                                <th>อุปกรณ์</th>
                                <th>เกินกำหนด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($overdue_list)): ?>
                                <tr><td colspan="3" class="text-center text-muted">ไม่มีผู้ค้างส่ง</td></tr>
                            <?php endif; ?>
                            <?php foreach($overdue_list as $od): ?>
                            <tr>
                                <td><?= htmlspecialchars($od['full_name']) ?></td>
                                <td><?= htmlspecialchars($od['eq_name']) ?></td>
                                <td><span class="badge bg-danger"><?= $od['overdue_days'] ?> วัน</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="card card-custom p-4">
            <h6 class="fw-bold text-primary mb-3">สถิติการยืมอุปกรณ์แยกตามชั้นปีการศึกษา 1-4 (ประจำปี)</h6>
            <div style="height: 300px;">
                <canvas id="borrowChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('borrowChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($categories_labels) ?>,
                datasets: [
                    { label: 'ปี 1', data: <?= json_encode($y1_data) ?>, backgroundColor: '#00c0ef' },
                    { label: 'ปี 2', data: <?= json_encode($y2_data) ?>, backgroundColor: '#00a65a' },
                    { label: 'ปี 3', data: <?= json_encode($y3_data) ?>, backgroundColor: '#f39c12' },
                    { label: 'ปี 4', data: <?= json_encode($y4_data) ?>, backgroundColor: '#b57bed' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { drawBorder: false } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>