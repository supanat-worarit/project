<?php
// admin/users.php - จัดการสิทธิ์และดูข้อมูลนักศึกษา/ผู้ใช้งาน
require_once '../config/auth_check.php';
require_once '../config/db.php';

// จัดการอัปเดต Role และ Status ของ User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $user_id = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE user SET role = ?, status = ? WHERE user_id = ?");
    $stmt->execute([$role, $status, $user_id]);
    $_SESSION['success_msg'] = "อัปเดตสถานะและสิทธิ์ของผู้ใช้งานสำเร็จ";
    header("Location: users.php");
    exit;
}

// API ดึงรายละเอียดนักศึกษาและประวัติแบบ AJAX สำหรับเปิดดูใน Modal
if (isset($_GET['get_user_detail'])) {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)$_GET['get_user_detail'];
    
    try {
        // ดึงข้อมูลผู้ใช้จากตาราง user ทั้งหมด
        $stmtUser =$pdo->prepare("SELECT * FROM user WHERE user_id = ? LIMIT 1");
        $stmtUser->execute([$uid]);
        $uData =$stmtUser->fetch(PDO::FETCH_ASSOC);

        // ดึงประวัติการทำรายการยืม-คืนของนักศึกษาคนนี้
        $stmtHistory =$pdo->prepare("
            SELECT t.*, e.eq_code, e.eq_name
            FROM transactions t
            LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
            WHERE t.user_id = ?
            ORDER BY t.trans_id DESC
        ");
        $stmtHistory->execute([$uid]);
        $history =$stmtHistory->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'user' => $uData,
            'history' => $history
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ค้นหาและกรองข้อมูลรายชื่อผู้ใช้งาน
$search = trim($_GET['search'] ?? '');
$role_filter =$_GET['role'] ?? 'all';
$status_filter =$_GET['status'] ?? 'all';

$conditions = [];$params = [];

if (!empty($search)) {$conditions[] = "(u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ? OR u.faculty LIKE ? OR u.campus LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter !== 'all') {$conditions[] = "u.role = ?";
    $params[] =$role_filter;
}

if ($status_filter !== 'all') {$conditions[] = "u.status = ?";
    $params[] =$status_filter;
}

$whereSql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
$stmt =$pdo->prepare("SELECT u.* FROM user u $whereSql ORDER BY u.user_id DESC");
$stmt->execute($params);
$userList =$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสิทธิ์ & ข้อมูลนักศึกษา | ศูนย์กีฬา ม.อ.ตรัง</title>
</head>
<body class="d-flex">
    <?php include '../components/sidebar.php'; ?>

    <div class="flex-grow-1 p-4" style="background-color: #f8fafc; min-height: 100vh;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1 text-dark">จัดการสิทธิ์ & ข้อมูลนักศึกษา</h4>
                <p class="text-muted small mb-0">ตรวจสอบรายชื่อผู้ใช้งานในระบบ กำหนดสิทธิ์ และดูประวัติการทำรายการ</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success_msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <div class="card card-custom p-3 border-0 shadow-sm mb-4">
            <form method="GET" action="users.php" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหาด้วยชื่อ, รหัสนักศึกษา, คณะ หรือวิทยาเขต..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>สิทธิ์ทั้งหมด (Role)</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>นักศึกษา (user)</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>แอดมิน (admin)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>สถานะทั้งหมด</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>ใช้งานปกติ (active)</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>ระงับใช้งาน (inactive)</option>
                        <option value="graduated" <?= $status_filter === 'graduated' ? 'selected' : '' ?>>จบการศึกษา (graduated)</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-medium">ค้นหา</button>
                    <?php if (!empty($search) || $role_filter !== 'all' ||$status_filter !== 'all'): ?>
                        <a href="users.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card card-custom p-0 overflow-hidden border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-people-fill me-2"></i>รายชื่อผู้ใช้งานในระบบ</h6>
                <span class="badge bg-primary rounded-pill"><?= count($userList) ?> รายการ</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสนักศึกษา</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>อีเมล</th>
                            <th>คณะ / วิทยาเขต</th>
                            <th>สิทธิ์ (Role)</th>
                            <th>สถานะ</th>
                            <th class="text-end pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($userList)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-5">ไม่พบข้อมูลผู้ใช้งานตามเงื่อนไขที่ค้นหา</td></tr>
                        <?php endif; ?>
                        <?php foreach ($userList as$u): ?>
                        <tr>
                            <td class="fw-semibold text-dark">
                                <?= htmlspecialchars($u['student_id'] ?? '-') ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($u['full_name'] ?? 'ไม่ระบุชื่อ') ?></div>
                                <?php if (!empty($u['line_user_id'])): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success" style="font-size: 10px;">
                                        <i class="bi bi-line me-1"></i>ผูก LINE แล้ว
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                            <td class="small">
                                <div><?= htmlspecialchars($u['faculty'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($u['campus'] ?? '-') ?></small>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-primary px-2.5 py-1">admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary px-2.5 py-1">user</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success">ใช้งานปกติ</span>
                                <?php elseif ($u['status'] === 'inactive'): ?>
                                    <span class="badge bg-danger">ระงับใช้งาน</span>
                                <?php elseif ($u['status'] === 'graduated'): ?>
                                    <span class="badge bg-warning text-dark">จบการศึกษา</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($u['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-info px-2.5 fw-medium me-1" onclick="viewUserDetail(<?= $u['user_id'] ?>)">
                                    <i class="bi bi-eye"></i> ดูข้อมูล
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary px-2.5 fw-medium" onclick='openEditUserModal(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)'>
                                    <i class="bi bi-pencil-square"></i> แก้ไข
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="userDetailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-primary"><i class="bi bi-person-lines-fill me-2"></i>รายละเอียดข้อมูลนักศึกษา</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="card border-0 bg-light p-3 rounded-3 mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">ชื่อ - นามสกุล:</div>
                                <div class="fw-bold fs-6 text-dark" id="d_fullname">-</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">รหัสนักศึกษา:</div>
                                <div class="fw-bold fs-6 text-primary" id="d_student_id">-</div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">อีเมล:</div>
                                <div class="fw-medium text-dark" id="d_email">-</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">เลขประจำตัวประชาชน (National ID):</div>
                                <div class="fw-medium text-dark" id="d_national_id">-</div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">เพศ:</div>
                                <div class="fw-medium text-dark" id="d_gender">-</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">คณะ (Faculty):</div>
                                <div class="fw-medium text-dark" id="d_faculty">-</div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">วิทยาเขต (Campus):</div>
                                <div class="fw-semibold text-primary" id="d_campus">-</div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">รุ่น / ปีที่เข้าศึกษา (Enrollment Year):</div>
                                <div class="fw-semibold text-dark" id="d_enrollment_year">-</div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">สิทธิ์ในระบบ (Role):</div>
                                <div class="mt-1" id="d_role_badge">-</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">สถานะบัญชี (Status):</div>
                                <div class="mt-1" id="d_status_badge">-</div>
                            </div>

                            <div class="col-12">
                                <div class="text-muted small">สถานะการเชื่อมต่อ LINE OA:</div>
                                <div class="mt-1" id="d_line_status">-</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-clock-history me-2"></i>ประวัติการยืม-คืนอุปกรณ์ทั้งหมด</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light text-secondary">
                                    <tr>
                                        <th>รหัสอุปกรณ์</th>
                                        <th>ชื่ออุปกรณ์</th>
                                        <th>วันที่ยืม</th>
                                        <th>กำหนดส่งคืน</th>
                                        <th>สถานะรายการ</th>
                                    </tr>
                                </thead>
                                <tbody id="d_history_tbody">
                                    <tr><td colspan="5" class="text-center text-muted py-3">กำลังโหลดประวัติ...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-gear-fill me-2"></i>แก้ไขสิทธิ์และสถานะผู้ใช้</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small">ชื่อ - นามสกุล</label>
                            <input type="text" id="edit_full_name" class="form-control bg-light" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small">รหัสนักศึกษา</label>
                            <input type="text" id="edit_student_id" class="form-control bg-light" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">สิทธิ์ในระบบ (Role) <span class="text-danger">*</span></label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="user">นักศึกษา (user)</option>
                                <option value="admin">ผู้ดูแลระบบ (admin)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">สถานะบัญชี (Status) <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="active">ใช้งานปกติ (active)</option>
                                <option value="inactive">ระงับการใช้งาน (inactive)</option>
                                <option value="graduated">สำเร็จการศึกษา (graduated)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">บันทึกการเปลี่ยนแปลง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ฟังก์ชันดึงข้อมูลจาก Database ผ่าน AJAX มาใส่ใน Modal ดูข้อมูล
        function viewUserDetail(userId) {
            // ตั้งค่าเริ่มต้นระหว่างรอข้อมูล
            document.getElementById('d_fullname').innerText = 'กำลังโหลดข้อมูล...';
            document.getElementById('d_student_id').innerText = '-';
            document.getElementById('d_email').innerText = '-';
            document.getElementById('d_national_id').innerText = '-';
            document.getElementById('d_gender').innerText = '-';
            document.getElementById('d_faculty').innerText = '-';
            document.getElementById('d_campus').innerText = '-';
            document.getElementById('d_enrollment_year').innerText = '-';
            document.getElementById('d_role_badge').innerHTML = '-';
            document.getElementById('d_status_badge').innerHTML = '-';
            document.getElementById('d_line_status').innerHTML = '-';
            document.getElementById('d_history_tbody').innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">กำลังโหลดประวัติ...</td></tr>';

            const detailModal = new bootstrap.Modal(document.getElementById('userDetailModal'));
            detailModal.show();

            fetch(`users.php?get_user_detail=${userId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.user) {
                        const u = data.user;
                        document.getElementById('d_fullname').innerText = u.full_name || '-';
                        document.getElementById('d_student_id').innerText = u.student_id || '-';
                        document.getElementById('d_email').innerText = u.email || '-';
                        document.getElementById('d_national_id').innerText = u.national_id || '-';
                        document.getElementById('d_gender').innerText = u.gender || '-';
                        document.getElementById('d_faculty').innerText = u.faculty || '-';
                        
                        // 1. ดึง campus แทน สาขาวิชา (Major)
                        document.getElementById('d_campus').innerText = u.campus || '-';
                        
                        // 2. ดึง enrollment_year จากฐานข้อมูลโดยตรง
                        document.getElementById('d_enrollment_year').innerText = u.enrollment_year ? `${u.enrollment_year}` : '-';

                        // แสดง Role Badge
                        const roleBadge = document.getElementById('d_role_badge');
                        if (u.role === 'admin') {
                            roleBadge.innerHTML = '<span class="badge bg-primary px-2.5 py-1">ผู้ดูแลระบบ (Admin)</span>';
                        } else {
                            roleBadge.innerHTML = '<span class="badge bg-secondary px-2.5 py-1">นักศึกษา (User)</span>';
                        }

                        // แสดง Status Badge
                        const statusBadge = document.getElementById('d_status_badge');
                        if (u.status === 'active') {
                            statusBadge.innerHTML = '<span class="badge bg-success px-2.5 py-1">ใช้งานปกติ</span>';
                        } else if (u.status === 'inactive') {
                            statusBadge.innerHTML = '<span class="badge bg-danger px-2.5 py-1">ระงับการใช้งาน</span>';
                        } else if (u.status === 'graduated') {
                            statusBadge.innerHTML = '<span class="badge bg-warning text-dark px-2.5 py-1">สำเร็จการศึกษา</span>';
                        } else {
                            statusBadge.innerHTML = `<span class="badge bg-secondary px-2.5 py-1">${u.status || '-'}</span>`;
                        }

                        // แสดงสถานะผูก LINE OA
                        const lineBadge = document.getElementById('d_line_status');
                        if (u.line_user_id) {
                            lineBadge.innerHTML = `<span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="bi bi-check-circle-fill me-1"></i>เชื่อมต่อแล้ว</span>`;
                        } else {
                            lineBadge.innerHTML = '<span class="badge bg-secondary bg-opacity-10 text-muted border"><i class="bi bi-dash-circle me-1"></i>ยังไม่เคยเชื่อมต่อ LINE OA</span>';
                        }

                        // เติมตารางประวัติการยืม-คืน
                        const tbody = document.getElementById('d_history_tbody');
                        tbody.innerHTML = '';
                        if (!data.history || data.history.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">ยังไม่มีประวัติการยืม-คืนอุปกรณ์</td></tr>';
                        } else {
                            data.history.forEach(item => {
                                let badgeClass = 'bg-secondary';
                                let statusText = item.trans_status;
                                if (item.trans_status === 'borrowed') {
                                    badgeClass = 'bg-primary';
                                    statusText = 'กำลังยืม';
                                } else if (item.trans_status === 'overdue') {
                                    badgeClass = 'bg-danger';
                                    statusText = 'เกินกำหนด';
                                } else if (item.trans_status === 'returned') {
                                    badgeClass = 'bg-success';
                                    statusText = 'คืนแล้ว';
                                } else if (item.trans_status === 'pending_return') {
                                    badgeClass = 'bg-info text-dark';
                                    statusText = 'รออนุมัติคืน';
                                }

                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td><span class="badge bg-light text-dark border">${item.eq_code || '-'}</span></td>
                                    <td>${item.eq_name || '-'}</td>
                                    <td class="small">${item.borrow_time || '-'}</td>
                                    <td class="small text-muted">${item.due_time || '-'}</td>
                                    <td><span class="badge ${badgeClass}">${statusText}</span></td>
                                `;
                                tbody.appendChild(tr);
                            });
                        }
                    } else {
                        document.getElementById('d_fullname').innerText = 'ไม่พบข้อมูลผู้ใช้งาน';
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('d_fullname').innerText = 'เกิดข้อผิดพลาดในการโหลดข้อมูล';
                });
        }

        // ฟังก์ชันเปิด Modal แก้ไข Role และ Status
        function openEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_student_id').value = user.student_id || '';
            document.getElementById('edit_role').value = user.role || 'user';
            document.getElementById('edit_status').value = user.status || 'active';
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
    </script>
</body>
</html>