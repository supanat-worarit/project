<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

// 1. เพิ่มประเภทอุปกรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $cat_name = trim($_POST['category_name']);

    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO sport_categories (category_name) VALUES (?)");
        $stmt->execute([$cat_name]);
        header("Location: categories.php");
        exit;
    }
}

// 2. แก้ไขประเภทอุปกรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $cat_id = (int)$_POST['category_id'];
    $new_cat_name = trim($_POST['category_name']);

    if (!empty($new_cat_name)) {
        $stmt = $pdo->prepare("UPDATE sport_categories SET category_name = ? WHERE category_id = ?");
        $stmt->execute([$new_cat_name, $cat_id]);

        $stmtEq = $pdo->prepare("UPDATE sport_equipment SET eq_name = ? WHERE category_id = ?");
        $stmtEq->execute([$new_cat_name, $cat_id]);

        header("Location: categories.php");
        exit;
    }
}

// 3. ลบประเภทอุปกรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $cat_id = (int)$_POST['category_id'];
    
    // 1. เช็คว่ามีอุปกรณ์ในหมวดหมู่นี้ กำลังถูกยืม หรือ ค้างส่ง หรือไม่
    $stmtCheckTrans = $pdo->prepare("
        SELECT COUNT(*) FROM transactions t
        JOIN sport_equipment e ON t.eq_id = e.eq_id
        WHERE e.category_id = ? AND t.trans_status IN ('borrowed', 'overdue')
    ");
    $stmtCheckTrans->execute([$cat_id]);
    $activeTrans = $stmtCheckTrans->fetchColumn();

    // 2. เช็คว่ามีอุปกรณ์ในหมวดหมู่นี้ ที่ชำรุดอยู่หรือไม่
    $stmtCheckDamaged = $pdo->prepare("SELECT COUNT(*) FROM sport_equipment WHERE category_id = ? AND status = 'damaged'");
    $stmtCheckDamaged->execute([$cat_id]);
    $damagedCount = $stmtCheckDamaged->fetchColumn();

    // 3. เช็คว่าเคยมีประวัติในระบบไหม (ถ้ามี ลบไม่ได้เพราะติด Foreign Key)
    $stmtCheckHistory = $pdo->prepare("
        SELECT COUNT(*) FROM transactions t
        JOIN sport_equipment e ON t.eq_id = e.eq_id
        WHERE e.category_id = ?
    ");
    $stmtCheckHistory->execute([$cat_id]);
    $historyCount = $stmtCheckHistory->fetchColumn();

    // ตรวจสอบเงื่อนไข
    if ($activeTrans > 0) {
        $_SESSION['error_msg'] = "ไม่สามารถลบได้! มีอุปกรณ์ในหมวดหมู่นี้กำลังถูกยืมหรือค้างส่ง";
    } elseif ($damagedCount > 0) {
        $_SESSION['error_msg'] = "ไม่สามารถลบได้! มีอุปกรณ์ในหมวดหมู่นี้อยู่ในสถานะชำรุด";
    } elseif ($historyCount > 0) {
        $_SESSION['error_msg'] = "ไม่สามารถลบหมวดหมู่นี้ได้! เนื่องจากมีอุปกรณ์ที่มีประวัติการทำรายการในระบบแล้ว";
    } else {
        // ถ้าผ่านทุกเงื่อนไข ให้ลบข้อมูลได้
        try {
            $pdo->beginTransaction();
            $stmtImgs = $pdo->prepare("SELECT image_path FROM image_equipment WHERE eq_id IN (SELECT eq_id FROM sport_equipment WHERE category_id = ?)");
            $stmtImgs->execute([$cat_id]);
            $imgs = $stmtImgs->fetchAll();
            foreach ($imgs as $img) {
                if (!empty($img['image_path']) && file_exists($img['image_path'])) unlink($img['image_path']);
            }

            $pdo->prepare("DELETE FROM image_equipment WHERE eq_id IN (SELECT eq_id FROM sport_equipment WHERE category_id = ?)")->execute([$cat_id]);
            $pdo->prepare("DELETE FROM sport_equipment WHERE category_id = ?")->execute([$cat_id]);
            $pdo->prepare("DELETE FROM sport_categories WHERE category_id = ?")->execute([$cat_id]);
            $pdo->commit();
            $_SESSION['success_msg'] = "ลบหมวดหมู่อุปกรณ์เรียบร้อยแล้ว";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
    header("Location: categories.php");
    exit;
}

// ดึงรายการประเภทอุปกรณ์
$stmt = $pdo->query("
    SELECT 
        c.category_id,
        c.category_name,
        COUNT(e.eq_id) as total_qty,
        SUM(CASE WHEN e.status = 'avaliable' THEN 1 ELSE 0 END) as available_qty
    FROM sport_categories c
    LEFT JOIN sport_equipment e ON c.category_id = e.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_id ASC
");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการรายการสต็อกอุปกรณ์กีฬา</title>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4">

        <!-- เพิ่มกล่องแจ้งเตือน Alert -->
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-octagon-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- สิ้นสุดส่วนแจ้งเตือน -->

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">จัดการรายการสต็อกอุปกรณ์กีฬา</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-lg"></i> เพิ่มประเภทอุปกรณ์ใหม่
            </button>
        </div>

        <div class="card card-custom p-0 overflow-hidden">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อประเภทอุปกรณ์</th>
                        <th>จำนวนคงเหลือ / ทั้งหมด</th>
                        <th class="text-end pe-4">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($categories)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">ไม่พบประเภทอุปกรณ์</td></tr>
                    <?php endif; ?>
                    <?php foreach($categories as $row): 
                        $code_prefix = 'PSU-' . str_pad($row['category_id'], 2, '0', STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($code_prefix) ?></td>
                        <td>
                            <a href="equipment.php?category_id=<?= $row['category_id'] ?>" class="text-decoration-none fw-semibold text-dark">
                                <?= htmlspecialchars($row['category_name']) ?>
                            </a>
                        </td>
                        <td><?= (int)$row['available_qty'] ?> / <?= (int)$row['total_qty'] ?></td>
                        <td class="text-end pe-4">
                            <a href="equipment.php?category_id=<?= $row['category_id'] ?>" class="btn btn-sm btn-outline-info me-1">
                                <i class="bi bi-eye"></i> ดูอุปกรณ์
                            </a>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditCatModal('<?= $row['category_id'] ?>', '<?= htmlspecialchars(addslashes($row['category_name'])) ?>')">
                                <i class="bi bi-pencil"></i> แก้ไข
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="openDeleteCatModal('<?= $row['category_id'] ?>', '<?= htmlspecialchars(addslashes($row['category_name'])) ?>')">
                                <i class="bi bi-trash"></i> ลบ
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal เพิ่มประเภทอุปกรณ์ -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold w-100 text-center mb-0">เพิ่มประเภทอุปกรณ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="categories.php" method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อประเภทอุปกรณ์</label>
                        <input type="text" name="category_name" class="form-control" required placeholder="เช่น ลูกเปตอง">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">เพิ่ม</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขประเภทอุปกรณ์ -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold w-100 text-center mb-0">แก้ไขประเภทอุปกรณ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="categories.php" method="POST">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_cat_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่อประเภทอุปกรณ์</label>
                        <input type="text" name="category_name" id="edit_cat_name" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">บันทึกการแก้ไข</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ลบประเภทอุปกรณ์ -->
    <div class="modal fade" id="deleteCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom text-center">
                <h5 class="fw-bold mb-3">ลบประเภทอุปกรณ์</h5>
                <p class="mb-4">คุณต้องการลบประเภท <strong id="del_cat_name"></strong> และอุปกรณ์ทั้งหมดใช่หรือไม่</p>
                <form action="categories.php" method="POST">
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" id="delete_cat_id">
                    <div class="d-flex justify-content-center gap-3">
                        <button type="submit" class="btn btn-danger px-4 fw-bold">ยืนยัน</button>
                        <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditCatModal(id, name) {
            document.getElementById('edit_cat_id').value = id;
            document.getElementById('edit_cat_name').value = name;
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        }

        function openDeleteCatModal(id, name) {
            document.getElementById('delete_cat_id').value = id;
            document.getElementById('del_cat_name').innerText = name;
            new bootstrap.Modal(document.getElementById('deleteCategoryModal')).show();
        }
    </script>
</body>
</html>