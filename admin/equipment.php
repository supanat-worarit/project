<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$error_msg = $_SESSION['error_msg'] ?? '';
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['error_msg'], $_SESSION['success_msg']);

// ดึงข้อมูลประเภทกีฬา
$cat_stmt = $pdo->prepare("SELECT * FROM sport_categories WHERE category_id = ?");
$cat_stmt->execute([$category_id]);
$current_category = $cat_stmt->fetch();
$category_name = $current_category ? $current_category['category_name'] : '';

// สร้าง Prefix รหัสอุปกรณ์ เช่น หมวดหมู่ ID 3 จะได้ "PSU-03-"
$eq_prefix = '';
if ($current_category) {
    $eq_prefix = 'PSU-' . str_pad($current_category['category_id'], 2, '0', STR_PAD_LEFT) . '-';
}

// 1. เพิ่มอุปกรณ์ใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_equipment') {
    $eq_name = trim($_POST['eq_name']);
    $eq_code = trim($_POST['eq_code']);
    $status = $_POST['status'];
    $post_cat_id = (int)$_POST['category_id'];

    // เช็ครหัสซ้ำ
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM sport_equipment WHERE eq_code = ?");
    $check_stmt->execute([$eq_code]);
    if ($check_stmt->fetchColumn() > 0) {
        $error_msg = "เลขรหัสครุภัณฑ์ '$eq_code' มีอยู่ในระบบแล้ว กรุณาใช้รหัสอื่น";
    } else {
        $stmt = $pdo->prepare("INSERT INTO sport_equipment (category_id, eq_code, eq_name, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$post_cat_id, $eq_code, $eq_name, $status]);
        $new_eq_id = $pdo->lastInsertId();

        // เช็ครูป
        if (isset($_FILES['equipment_imgs']) && !empty($_FILES['equipment_imgs']['name'][0])) {
            $uploadDir = 'uploads/equipments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $stmtImg = $pdo->prepare("INSERT INTO image_equipment (image_path, eq_id) VALUES (?, ?)");
            $totalFiles = count($_FILES['equipment_imgs']['name']);

            // ตรวจสอบไฟล์รูป & ป้องกันชื่อไฟล์ซ้ำ
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['equipment_imgs']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['equipment_imgs']['tmp_name'][$i];
                    $fileName = time() . '_' . $i . '_' . $_FILES['equipment_imgs']['name'][$i];
                    $destPath = $uploadDir . $fileName;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $stmtImg->execute([$destPath, $new_eq_id]);
                    }
                }
            }
        }
        header("Location: equipment.php?category_id=" . $post_cat_id);
        exit;
    }
}

// 2. แก้ไขอุปกรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_equipment') {
    $eq_id = (int)$_POST['eq_id'];
    $eq_code = trim($_POST['eq_code']);
    $status = $_POST['status'];
    $post_cat_id = (int)$_POST['category_id'];

    // เช็ครหัสซ้ำ
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM sport_equipment WHERE eq_code = ? AND eq_id != ?");
    $check_stmt->execute([$eq_code, $eq_id]);
    if ($check_stmt->fetchColumn() > 0) {
        $error_msg = "เลขรหัสครุภัณฑ์ '$eq_code' ซ้ำกับอุปกรณ์ชิ้นอื่นในระบบ กรุณาใช้รหัสอื่น";
    } else {
        $stmt = $pdo->prepare("UPDATE sport_equipment SET eq_code = ?, status = ? WHERE eq_id = ?");
        $stmt->execute([$eq_code, $status, $eq_id]);

        // เช็ครูป
        if (isset($_FILES['equipment_imgs']) && !empty($_FILES['equipment_imgs']['name'][0])) {
            $uploadDir = 'uploads/equipments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $stmtImg = $pdo->prepare("INSERT INTO image_equipment (image_path, eq_id) VALUES (?, ?)");
            $totalFiles = count($_FILES['equipment_imgs']['name']);

            // ตรวจสอบไฟล์รูป & ป้องกันชื่อไฟล์ซ้ำ
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['equipment_imgs']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['equipment_imgs']['tmp_name'][$i];
                    $fileName = time() . '_' . $i . '_' . $_FILES['equipment_imgs']['name'][$i];
                    $destPath = $uploadDir . $fileName;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $stmtImg->execute([$destPath, $eq_id]);
                    }
                }
            }
        }
        header("Location: equipment.php?category_id=" . $post_cat_id);
        exit;
    }
}

// 3. ลบอุปกรณ์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_equipment') {
    $eq_id = (int)$_POST['eq_id'];
    $post_cat_id = (int)$_POST['category_id'];

    // 1. เช็คสถานะปัจจุบันของอุปกรณ์
    $stmtCheckEq = $pdo->prepare("SELECT status FROM sport_equipment WHERE eq_id = ?");
    $stmtCheckEq->execute([$eq_id]);
    $eqStatus = $stmtCheckEq->fetchColumn();

    // 2. เช็คว่ามีคนกำลังยืมหรือค้างส่งหรือไม่
    $stmtCheckTrans = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE eq_id = ? AND trans_status IN ('borrowed', 'overdue')");
    $stmtCheckTrans->execute([$eq_id]);
    $activeTrans = $stmtCheckTrans->fetchColumn();

    // 3. เช็คว่ามีประวัติเก่าๆ ไหม
    $stmtCheckHistory = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE eq_id = ?");
    $stmtCheckHistory->execute([$eq_id]);
    $historyCount = $stmtCheckHistory->fetchColumn();

    if ($activeTrans > 0) {
        $_SESSION['error_msg'] = "ไม่สามารถลบได้! อุปกรณ์ชิ้นนี้กำลังถูกยืม หรือค้างส่งอยู่";
    } elseif ($eqStatus === 'damaged') {
        $_SESSION['error_msg'] = "ไม่สามารถลบได้! อุปกรณ์ชิ้นนี้อยู่ในสถานะชำรุด (กรุณาซ่อมแซมก่อน)";
    } elseif ($historyCount > 0) {
        $_SESSION['error_msg'] = "ไม่สามารถลบได้! อุปกรณ์นี้มีประวัติในระบบแล้ว (แนะนำให้แก้สถานะเป็น 'ไม่พร้อมใช้งาน' แทน)";
    } else {
        try {
            $pdo->beginTransaction();
            $stmtImgs = $pdo->prepare("SELECT image_path FROM image_equipment WHERE eq_id = ?");
            $stmtImgs->execute([$eq_id]);
            $imgs = $stmtImgs->fetchAll();
            foreach ($imgs as $img) {
                if (!empty($img['image_path']) && file_exists($img['image_path'])) unlink($img['image_path']);
            }

            $pdo->prepare("DELETE FROM image_equipment WHERE eq_id = ?")->execute([$eq_id]);
            $pdo->prepare("DELETE FROM sport_equipment WHERE eq_id = ?")->execute([$eq_id]);
            $pdo->commit();
            $_SESSION['success_msg'] = "ลบอุปกรณ์เรียบร้อยแล้ว";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_msg'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
    header("Location: equipment.php?category_id=" . $post_cat_id);
    exit;
}

// 4. ลบเฉพาะรูปภาพผ่าน Form Modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_single_image') {
    $del_img_id = (int)$_POST['delete_image_id'];
    $post_cat_id = (int)$_POST['category_id'];

    $stmtImg = $pdo->prepare("SELECT image_path FROM image_equipment WHERE image_id = ?");
    $stmtImg->execute([$del_img_id]);
    $img = $stmtImg->fetch();

    if ($img) {
        if (!empty($img['image_path']) && file_exists($img['image_path'])) {
            unlink($img['image_path']);
        }
        $pdo->prepare("DELETE FROM image_equipment WHERE image_id = ?")->execute([$del_img_id]);
    }

    header("Location: equipment.php?category_id=" . $post_cat_id);
    exit;
}

// ดึงรายการอุปกรณ์
$query = "
    SELECT e.*, 
        GROUP_CONCAT(CONCAT(img.image_id, ':::', img.image_path) SEPARATOR '||') as all_images,
        COUNT(img.image_id) as img_count
    FROM sport_equipment e
    LEFT JOIN image_equipment img ON e.eq_id = img.eq_id
";
if ($category_id > 0) {
    $stmt = $pdo->prepare($query . " WHERE e.category_id = ? GROUP BY e.eq_id ORDER BY e.eq_id ASC");
    $stmt->execute([$category_id]);
} else {
    $stmt = $pdo->query($query . " GROUP BY e.eq_id ORDER BY e.eq_id ASC");
}
$equipments = $stmt->fetchAll();
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
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-octagon-fill me-2"></i><?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="fw-bold mb-1">จัดการรายการสต็อกอุปกรณ์กีฬา</h5>
                <?php if(!empty($category_name)): ?>
                    <span class="badge bg-primary text-white">หมวดหมู่: <?= htmlspecialchars($category_name) ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a href="categories.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> กลับหน้ารวม</a>
                <?php if($category_id > 0): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-lg"></i> เพิ่มอุปกรณ์ใหม่
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-custom p-0 overflow-hidden">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่ออุปกรณ์</th>
                        <th>รูปภาพ</th>
                        <th>สถานะ</th>
                        <th class="text-end pe-4">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($equipments)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">ไม่พบรายการอุปกรณ์</td></tr>
                    <?php endif; ?>
                    <?php foreach($equipments as $eq): 
                        $img_list = [];
                        if(!empty($eq['all_images'])) {
                            $pairs = explode('||', $eq['all_images']);
                            foreach($pairs as $p) {
                                $parts = explode(':::', $p);
                                if(count($parts) === 2) {
                                    $img_list[] = ['id' => $parts[0], 'path' => $parts[1]];
                                }
                            }
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($eq['eq_code']) ?></td>
                        <td><?= htmlspecialchars($eq['eq_name']) ?></td>
                        <td>
                            <?php if(!empty($img_list)): ?>
                                <button class="btn btn-sm btn-primary" onclick='viewMultipleImages(<?= json_encode($img_list) ?>, "<?= htmlspecialchars($eq['eq_code']) ?>")'>
                                    <i class="bi bi-images"></i> ดูรูปภาพ (<?= count($img_list) ?>)
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>ไม่มีรูป</button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if($eq['status'] == 'avaliable') {
                                echo '<span class="badge bg-success">พร้อมใช้งาน</span>';
                            } elseif($eq['status'] == 'borrowed') {
                                echo '<span class="badge bg-warning text-dark">กำลังใช้งานอยู่</span>';
                            } else {
                                echo '<span class="badge bg-danger">ไม่พร้อมใช้งาน</span>';
                            }
                            ?>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-primary me-1" onclick='openEditModal(<?= json_encode($eq) ?>)'>
                                <i class="bi bi-pencil"></i> แก้ไข
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="openDeleteModal('<?= $eq['eq_id'] ?>', '<?= $eq['eq_code'] ?>')">
                                <i class="bi bi-trash"></i> ลบ
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal เพิ่มรายการอุปกรณ์ -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold w-100 text-center mb-0">เพิ่มรายการอุปกรณ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipment.php?category_id=<?= $category_id ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_equipment">
                    <input type="hidden" name="category_id" value="<?= $category_id ?>">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ชื่ออุปกรณ์</label>
                        <input type="text" name="eq_name" class="form-control" value="<?= htmlspecialchars($category_name) ?>" required readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลขครุภัณฑ์ / รหัส</label>
                        <input type="text" name="eq_code" class="form-control" value="<?= htmlspecialchars($eq_prefix) ?>" required placeholder="เช่น <?= htmlspecialchars($eq_prefix) ?>001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">สถานะอุปกรณ์</label>
                        <select name="status" class="form-select">
                            <option value="avaliable">พร้อมใช้งาน</option>
                            <option value="damaged">ไม่พร้อมใช้งาน</option>
                            <option value="borrowed">กำลังใช้งานอยู่</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">รูปอุปกรณ์ (เลือกได้หลายรูป)</label>
                        <input type="file" name="equipment_imgs[]" class="form-control" accept="image/*" multiple>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">บันทึก</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขรายการอุปกรณ์ -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold w-100 text-center mb-0">แก้ไขรายการอุปกรณ์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipment.php?category_id=<?= $category_id ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_equipment">
                    <input type="hidden" name="category_id" value="<?= $category_id ?>">
                    <input type="hidden" name="eq_id" id="edit_eq_id">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">เลขครุภัณฑ์ / รหัส</label>
                        <input type="text" name="eq_code" id="edit_eq_code" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">สถานะอุปกรณ์</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="avaliable">พร้อมใช้งาน</option>
                            <option value="damaged">ไม่พร้อมใช้งาน</option>
                            <option value="borrowed">กำลังใช้งานอยู่</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">เพิ่มรูปภาพใหม่ (เลือกได้หลายรูป)</label>
                        <input type="file" name="equipment_imgs[]" class="form-control" accept="image/*" multiple>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">ยืนยัน</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ลบรายการอุปกรณ์ -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom text-center">
                <h5 class="fw-bold mb-3">ลบรายการอุปกรณ์</h5>
                <p class="mb-4">คุณต้องการลบอุปกรณ์ <strong id="del_eq_code"></strong> ใช่หรือไม่</p>
                <form action="equipment.php?category_id=<?= $category_id ?>" method="POST">
                    <input type="hidden" name="action" value="delete_equipment">
                    <input type="hidden" name="category_id" value="<?= $category_id ?>">
                    <input type="hidden" name="eq_id" id="delete_eq_id">
                    <div class="d-flex justify-content-center gap-3">
                        <button type="submit" class="btn btn-danger px-4 fw-bold">ยืนยัน</button>
                        <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal ดูรูปภาพทั้งหมด (Gallery Grid) -->
    <div class="modal fade" id="imageGalleryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-bold" id="galleryTitle">รูปภาพอุปกรณ์</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3" id="galleryContainer"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ยืนยันการลบรูปภาพเฉพาะรูป -->
    <div class="modal fade" id="deleteImageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-box-custom text-center">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold w-100 text-center mb-0">ยืนยันการลบรูปภาพ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="mb-3">
                    <img id="del_preview_img" src="" class="img-thumbnail" style="max-height: 160px; object-fit: contain;">
                </div>
                <p class="mb-4">คุณต้องการลบรูปภาพนี้ใช่หรือไม่</p>
                <form action="equipment.php?category_id=<?= $category_id ?>" method="POST">
                    <input type="hidden" name="action" value="delete_single_image">
                    <input type="hidden" name="category_id" value="<?= $category_id ?>">
                    <input type="hidden" name="delete_image_id" id="modal_del_image_id">
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
        function openEditModal(eq) {
            document.getElementById('edit_eq_id').value = eq.eq_id;
            document.getElementById('edit_eq_code').value = eq.eq_code;
            document.getElementById('edit_status').value = eq.status;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function openDeleteModal(id, code) {
            document.getElementById('delete_eq_id').value = id;
            document.getElementById('del_eq_code').innerText = code;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function viewMultipleImages(images, code) {
            document.getElementById('galleryTitle').innerText = 'รูปภาพอุปกรณ์: ' + code;
            const container = document.getElementById('galleryContainer');
            container.innerHTML = '';

            images.forEach(function(item) {
                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4 text-center';
                col.innerHTML = `
                    <div class="card p-1 border shadow-sm position-relative">
                        <img src="${item.path}" class="img-fluid rounded" style="height: 180px; object-fit: cover; width: 100%;">
                        <div class="p-2">
                            <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="openDeleteImageModal('${item.id}', '${item.path}')">
                                <i class="bi bi-trash"></i> ลบรูปนี้
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(col);
            });

            new bootstrap.Modal(document.getElementById('imageGalleryModal')).show();
        }

        function openDeleteImageModal(imageId, imagePath) {
            // ปิด Modal แกลเลอรีรูปภาพก่อน
            const galleryModalEl = document.getElementById('imageGalleryModal');
            const galleryModal = bootstrap.Modal.getInstance(galleryModalEl);
            if (galleryModal) {
                galleryModal.hide();
            }

            document.getElementById('modal_del_image_id').value = imageId;
            document.getElementById('del_preview_img').src = imagePath;

            new bootstrap.Modal(document.getElementById('deleteImageModal')).show();
        }
    </script>
</body>
</html>