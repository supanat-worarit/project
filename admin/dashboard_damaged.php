<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

// อัปเดตสถานะอุปกรณ์เมื่อซ่อมเสร็จ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_fixed') {
    $eq_id = (int)$_POST['eq_id'];
    $stmt = $pdo->prepare("UPDATE sport_equipment SET status = 'avaliable' WHERE eq_id = ?");
    $stmt->execute([$eq_id]);
    header("Location: dashboard_damaged.php");
    exit;
}

// ดึงอุปกรณ์ที่ชำรุด หรือซ่อมบำรุง
$search = trim($_GET['search'] ?? '');
$params = [];

$sql = "
    SELECT e.*, c.category_name 
    FROM sport_equipment e
    LEFT JOIN sport_categories c ON e.category_id = c.category_id
    WHERE e.status IN ('damaged', 'maintenance')
";

if (!empty($search)) {
    $sql .= " AND (e.eq_name LIKE ? OR e.eq_code LIKE ? OR c.category_name LIKE ?)";
    $kw = "%{$search}%";
    $params = [$kw, $kw, $kw];
}
$sql .= " ORDER BY e.eq_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$damagedList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อุปกรณ์ที่ชำรุด / ซ่อมบำรุง | ศูนย์กีฬา ม.อ.ตรัง</title>
    <style>
        @media print {
            .sidebar, .filter-card, .btn-print, .btn-back, .action-col { display: none !important; }
            .main-content { padding: 0 !important; background: #fff !important; }
        }
    </style>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 main-content" style="background-color: #f8fafc; min-height: 100vh;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">
                    <i class="bi bi-tools text-danger me-2"></i>3. รายการอุปกรณ์ที่ชำรุด / ส่งซ่อมบำรุง
                </h4>
                <p class="text-muted small mb-0">รายการอุปกรณ์ที่ไม่พร้อมใช้งาน ชำรุดเสียหาย หรืออยู่ระหว่างการซ่อมแซม</p>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm btn-back"><i class="bi bi-arrow-left"></i> กลับหน้าภาพรวม</a>
                <button onclick="window.print()" class="btn btn-outline-dark btn-sm btn-print"><i class="bi bi-printer"></i> พิมพ์รายงาน</button>
            </div>
        </div>

        <!-- กล่องค้นหา -->
        <div class="card p-3 mb-4 filter-card border-0 shadow-sm bg-white">
            <form method="GET" action="dashboard_damaged.php" class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหารหัส เช่น PSU-01-003 หรือชื่ออุปกรณ์..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-md-2">
                        <a href="dashboard_damaged.php" class="btn btn-outline-secondary w-100">ล้างค้นหา</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ตารางแสดงรายการอุปกรณ์ชำรุด -->
        <div class="card p-0 overflow-hidden border-0 shadow-sm bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-danger">อุปกรณ์ที่ไม่พร้อมใช้งานทั้งหมด</h6>
                <span class="badge bg-danger fs-6 px-3 py-1"><?= count($damagedList) ?> ชิ้น</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสอุปกรณ์</th>
                            <th>ชื่ออุปกรณ์</th>
                            <th>ประเภทกีฬา</th>
                            <th>สถานะปัจจุบัน</th>
                            <th class="text-end pe-4 action-col">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($damagedList)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                                    ไม่พบข้อมูลอุปกรณ์ตามคำค้นหา
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($damagedList as $item): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['eq_code'] ?? '-') ?></span></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($item['eq_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($item['category_name'] ?? '-') ?></td>
                            <td>
                                <?php if ($item['status'] === 'damaged'): ?>
                                    <span class="badge bg-danger">ชำรุดเสียหาย</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">ส่งซ่อมบำรุง</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4 action-col">
                                <form action="dashboard_damaged.php" method="POST" class="d-inline" onsubmit="return confirm('ยืนยันว่าซ่อมเสร็จและเปลี่ยนสถานะเป็นพร้อมใช้งานแล้ว?');">
                                    <input type="hidden" name="action" value="mark_fixed">
                                    <input type="hidden" name="eq_id" value="<?= $item['eq_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-check2"></i> เปลี่ยนเป็นพร้อมใช้งาน
                                    </button>
                                </form>
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