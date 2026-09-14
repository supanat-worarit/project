<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

// 1. จัดการเพิ่มประเภทอุปกรณ์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $category_name = trim($_POST['category_name'] ?? '');

    if (!empty($category_name)) {
        $stmt = $pdo->prepare("INSERT INTO sport_categories (category_name) VALUES (?)");
        $stmt->execute([$category_name]);
    }
    header("Location: categories.php");
    exit;
}

// 2. จัดการแก้ไขประเภทอุปกรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $category_id = (int)$_POST['category_id'];
    $category_name = trim($_POST['category_name'] ?? '');

    if (!empty($category_name)) {
        $stmt = $pdo->prepare("UPDATE sport_categories SET category_name = ? WHERE category_id = ?");
        $stmt->execute([$category_name, $category_id]);
    }
    header("Location: categories.php");
    exit;
}

// 3. จัดการลบประเภทอุปกรณ์
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    // ตรวจสอบว่ามีอุปกรณ์ย่อยอยู่ในประเภทนี้หรือไม่
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sport_equipment WHERE category_id = ?");
    $checkStmt->execute([$category_id]);
    $hasEquipment = $checkStmt->fetchColumn();

    if ($hasEquipment == 0) {
        $stmt = $pdo->prepare("DELETE FROM sport_categories WHERE category_id = ?");
        $stmt->execute([$category_id]);
    } else {
        echo "<script>alert('ไม่สามารถลบประเภทนี้ได้ เนื่องจากยังมีรายการอุปกรณ์อยู่ในระบบ'); window.location.href='categories.php';</script>";
        exit;
    }
    header("Location: categories.php");
    exit;
}

// รับค่าคำค้นหา (รหัส หรือ ชื่อประเภท)
$search = trim($_GET['search'] ?? '');
$params = [];

// ตรวจสอบว่ามีคอลัมน์ category_code หรือไม่
$colsStmt = $pdo->query("SHOW COLUMNS FROM sport_categories");
$existingCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
$hasCatCodeCol = in_array('category_code', $existingCols);

$sql = "
    SELECT c.*, 
           COUNT(e.eq_id) as total_items,
           SUM(CASE WHEN e.status = 'avaliable' OR e.status = 'available' THEN 1 ELSE 0 END) as available_items
    FROM sport_categories c
    LEFT JOIN sport_equipment e ON c.category_id = e.category_id
";

if (!empty($search)) {
    // ตัดเอาเฉพาะตัวเลขกรณีค้นหา PSU-01 หรือใส่มาแค่ 1
    $searchNum = preg_replace('/[^0-9]/', '', $search);
    
    if ($hasCatCodeCol) {
        $sql .= " WHERE c.category_name LIKE ? OR c.category_code LIKE ?";
        $kw = "%{$search}%";
        $params = [$kw, $kw];
    } else {
        $sql .= " WHERE c.category_name LIKE ? OR c.category_id = ?";
        $kw = "%{$search}%";
        $params = [$kw, (int)$searchNum];
    }
}

$sql .= " GROUP BY c.category_id ORDER BY c.category_id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการรายการสต็อกอุปกรณ์กีฬา | ศูนย์กีฬา ม.อ.ตรัง</title>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4" style="background-color: #f8fafc; min-height: 100vh;">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">จัดการรายการสต็อกอุปกรณ์กีฬา</h4>
                <p class="text-muted small mb-0">ตรวจสอบจำนวนสต็อกคงเหลือ จัดการหมวดหมู่ และเพิ่มรายการอุปกรณ์</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-lg me-1"></i> เพิ่มประเภทอุปกรณ์ใหม่
            </button>
        </div>

        <!-- กล่องค้นหา -->
        <div class="card card-custom p-3 mb-4 border-0 shadow-sm bg-white">
            <form method="GET" action="categories.php" class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหารหัส เช่น PSU-01 หรือชื่ออุปกรณ์ เช่น ลูกวอลเลย์..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> ค้นหา
                    </button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-md-2">
                        <a href="categories.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1"></i> ล้างการค้นหา
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ตารางแสดงรายการประเภทอุปกรณ์ -->
        <div class="card card-custom p-0 overflow-hidden border-0 shadow-sm bg-white">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th style="width: 15%;">รหัส</th>
                            <th style="width: 45%;">ชื่อประเภทอุปกรณ์</th>
                            <th style="width: 20%;">จำนวนคงเหลือ / ทั้งหมด</th>
                            <th style="width: 20%;" class="text-end pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                                    ไม่พบประเภทอุปกรณ์ตามเงื่อนไขที่ค้นหา
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($categories as $cat): 
                            // แสดงรหัส PSU-01, PSU-02 จาก category_code หรือจำลองจาก category_id
                            $catCodeDisplay = !empty($cat['category_code']) ? $cat['category_code'] : ('PSU-' . str_pad($cat['category_id'], 2, '0', STR_PAD_LEFT));
                        ?>
                        <tr>
                            <td class="fw-semibold text-dark">
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($catCodeDisplay) ?></span>
                            </td>
                            <td class="fw-medium text-dark">
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </td>
                            <td>
                                <span class="fw-bold <?= ($cat['available_items'] > 0) ? 'text-success' : 'text-danger' ?>">
                                    <?= (int)$cat['available_items'] ?>
                                </span>
                                <span class="text-muted">/ <?= (int)$cat['total_items'] ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="equipment.php?category_id=<?= $cat['category_id'] ?>" class="btn btn-sm btn-outline-info me-1">
                                    <i class="bi bi-eye"></i> ดูอุปกรณ์
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick='openEditModal(<?= json_encode($cat) ?>, "<?= $catCodeDisplay ?>")'>
                                    <i class="bi bi-pencil"></i> แก้ไข
                                </button>
                                <a href="categories.php?delete=<?= $cat['category_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('ยืนยันลบประเภทนี้? (ต้องไม่มีอุปกรณ์ค้างอยู่)');">
                                    <i class="bi bi-trash"></i> ลบ
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่มประเภทอุปกรณ์ใหม่ -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="fw-bold mb-0">เพิ่มประเภทอุปกรณ์ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="categories.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="add_category">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">ชื่อประเภทอุปกรณ์ <span class="text-danger">*</span></label>
                            <input type="text" name="category_name" class="form-control" required placeholder="เช่น ลูกบาสเกตบอล Molten BG3800">
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขประเภทอุปกรณ์ -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="fw-bold mb-0">แก้ไขประเภทอุปกรณ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="categories.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">รหัสประเภท</label>
                            <input type="text" id="display_cat_code" class="form-control bg-light" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">ชื่อประเภทอุปกรณ์ <span class="text-danger">*</span></label>
                            <input type="text" name="category_name" id="edit_category_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));

        function openEditModal(cat, catCode) {
            document.getElementById('edit_category_id').value = cat.category_id;
            document.getElementById('display_cat_code').value = catCode;
            document.getElementById('edit_category_name').value = cat.category_name;
            editModal.show();
        }
    </script>
</body>
</html>