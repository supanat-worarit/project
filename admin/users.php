<?php
require_once '../config/auth_check.php';
require_once '../config/db.php';

// อัปเดต Role และ Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $user_id = (int)$_POST['user_id'];
    $role = $_POST['role'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE user SET role = ?, status = ? WHERE user_id = ?");
    $stmt->execute([$role, $status, $user_id]);
    header("Location: users.php");
    exit;
}

// API ดึงรายละเอียดนักศึกษาและประวัติแบบ AJAX สำหรับ Modal
if (isset($_GET['get_user_detail'])) {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)$_GET['get_user_detail'];
    
    try {
        $stmtUser = $pdo->prepare("SELECT * FROM user WHERE user_id = ? LIMIT 1");
        $stmtUser->execute([$uid]);
        $uData = $stmtUser->fetch(PDO::FETCH_ASSOC);

        // ดึงประวัติการยืม-คืน
        $stmtHistory = $pdo->prepare("
            SELECT t.*, e.eq_code, e.eq_name
            FROM transactions t
            LEFT JOIN sport_equipment e ON t.eq_id = e.eq_id
            WHERE t.user_id = ?
            ORDER BY t.trans_id DESC
            LIMIT 10
        ");
        $stmtHistory->execute([$uid]);
        $history = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'user' => $uData, 'history' => $history]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// รับค่าค้นหา รหัสนักศึกษา / ชื่อ / อีเมล
$search = trim($_GET['search'] ?? '');
$params = [];
$sql = "SELECT * FROM user";

if (!empty($search)) {
    $sql .= " WHERE student_id LIKE ? OR full_name LIKE ? OR email LIKE ?";
    $kw = "%{$search}%";
    $params = [$kw, $kw, $kw];
}

$sql .= " ORDER BY user_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <p class="text-muted small mb-0">ตรวจสอบประวัติ ข้อมูลนักศึกษา และกำหนดสิทธิ์การใช้งานในระบบ</p>
            </div>
            <span class="badge bg-primary fs-6 px-3 py-2">
                ผู้ใช้ทั้งหมด: <?= count($users) ?> บัญชี
            </span>
        </div>

        <!-- กล่องค้นหา -->
        <div class="card card-custom p-3 mb-4 border-0 shadow-sm bg-white">
            <form method="GET" action="users.php" class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="ค้นหารหัสนักศึกษา, ชื่อ - นามสกุล หรืออีเมล..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> ค้นหา
                    </button>
                </div>
                <?php if (!empty($search)): ?>
                    <div class="col-md-2">
                        <a href="users.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1"></i> ล้างการค้นหา
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- ตารางข้อมูลผู้ใช้ -->
        <div class="card card-custom p-0 overflow-hidden border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th>รหัสนักศึกษา / Staff ID</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>อีเมล</th>
                            <th>สิทธิ์ (Role)</th>
                            <th>สถานะ (Status)</th>
                            <th class="text-end pe-4">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-person-x fs-1 d-block mb-2"></i>
                                    ไม่พบข้อมูลผู้ใช้งานตามเงื่อนไขที่ค้นหา
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($u['student_id'] ?? '-') ?></span></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($u['full_name'] ?? 'ไม่ระบุชื่อ') ?></td>
                            <td><?= htmlspecialchars($u['email'] ?? '-') ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-primary">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success">ใช้งานได้</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">ระงับการใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="showUserDetail(<?= (int)$u['user_id'] ?>)">
                                    <i class="bi bi-person-lines-fill"></i> ดูข้อมูล
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick='openEditUserModal(<?= json_encode($u) ?>)'>
                                    <i class="bi bi-pencil"></i> แก้ไขสิทธิ์
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal ดูรายละเอียดนักศึกษา -->
    <div class="modal fade" id="detailUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content text-dark border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-badge text-primary me-2"></i>รายละเอียดข้อมูลนักศึกษา</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded mb-4 border">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <span class="text-muted small">ชื่อ - นามสกุล:</span>
                                <div class="fw-bold fs-6" id="d_fullname">กำลังโหลด...</div>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small">รหัสนักศึกษา / Account:</span>
                                <div class="fw-bold fs-6" id="d_studentid">-</div>
                            </div>
                            
                            <div class="col-md-6">
                                <span class="text-muted small">คณะ (Faculty):</span>
                                <div class="fw-bold text-dark" id="d_faculty">-</div>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small">สาขาวิชา (Major):</span>
                                <div class="fw-bold text-dark" id="d_major">-</div>
                            </div>

                            <div class="col-md-6">
                                <span class="text-muted small">อีเมล:</span>
                                <div class="fw-bold" id="d_email">-</div>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small">รุ่น / รหัสปีที่เข้าศึกษา:</span>
                                <div class="fw-bold text-primary" id="d_year">-</div>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small">สิทธิ์การใช้งาน (Role):</span>
                                <div id="d_role">-</div>
                            </div>
                            <div class="col-md-6">
                                <span class="text-muted small">สถานะการใช้งาน:</span>
                                <div id="d_status">-</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold mb-2"><i class="bi bi-clock-history text-secondary me-1"></i> ประวัติการทำรายการล่าสุด (10 รายการ)</h6>
                    <div class="table-responsive border rounded" style="max-height: 250px;">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>รหัสอุปกรณ์</th>
                                    <th>ชื่ออุปกรณ์</th>
                                    <th>วันที่ยืม</th>
                                    <th>กำหนดคืน</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr><td colspan="5" class="text-center text-muted py-3">กำลังโหลดข้อมูล...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขสิทธิ์ -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-dark border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="fw-bold mb-0">แก้ไขสิทธิ์และสถานะผู้ใช้</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="users.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="edit_user_id">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">ชื่อ - นามสกุล</label>
                            <input type="text" id="edit_user_name" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">สิทธิ์ (Role)</label>
                            <select name="role" id="edit_user_role" class="form-select">
                                <option value="user">User (นักศึกษา/ผู้ใช้ทั่วไป)</option>
                                <option value="admin">Admin (ผู้ดูแลระบบ/เจ้าหน้าที่)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">สถานะการใช้งาน (Status)</label>
                            <select name="status" id="edit_user_status" class="form-select">
                                <option value="active">ใช้งานได้ (Active)</option>
                                <option value="inactive">ระงับการใช้งาน (Inactive)</option>
                            </select>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const detailModal = new bootstrap.Modal(document.getElementById('detailUserModal'));
        const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));

        function openEditUserModal(u) {
            document.getElementById('edit_user_id').value = u.user_id;
            document.getElementById('edit_user_name').value = u.full_name || u.student_id;
            document.getElementById('edit_user_role').value = u.role;
            document.getElementById('edit_user_status').value = u.status;
            editModal.show();
        }

        function parsePsuFacultyMajor(studentId) {
            if (!studentId || studentId.length < 5) {
                return { faculty: 'ไม่ระบุ', major: 'ไม่ระบุ' };
            }

            const facCode = studentId.substring(2, 4);
            const majorCode = studentId.substring(2, 7);

            let faculty = 'มหาวิทยาลัยสงขลานครินทร์';
            let major = 'ไม่ระบุสาขาวิชา';

            if (facCode === '50') {
                faculty = 'คณะพาณิชยศาสตร์และการจัดการ';
                if (majorCode.startsWith('50110')) major = 'ระบบสารสนเทศและนวัตกรรมดิจิทัล (DI)';
                else if (majorCode.startsWith('50111')) major = 'การจัดการธุรกิจดิจิทัล';
                else if (majorCode.startsWith('50112')) major = 'การเงินและการลงทุน';
                else if (majorCode.startsWith('50113')) major = 'การตลาดดิจิทัล';
                else if (majorCode.startsWith('50114')) major = 'การบัญชี';
                else if (majorCode.startsWith('50115')) major = 'การจัดการท่องเที่ยวและบริการ';
                else major = 'พาณิชยศาสตร์และการจัดการ';
            } else if (facCode === '51') {
                faculty = 'คณะสถาปัตยกรรมศาสตร์';
                if (majorCode.startsWith('51101')) major = 'สถาปัตยกรรม';
                else if (majorCode.startsWith('51102')) major = 'การออกแบบสิ่งทอและแฟชั่น';
                else major = 'สถาปัตยกรรมศาสตร์และการออกแบบ';
            }

            return { faculty, major };
        }

        function showUserDetail(uid) {
            document.getElementById('d_fullname').innerText = 'กำลังโหลดข้อมูล...';
            document.getElementById('d_studentid').innerText = '-';
            document.getElementById('d_faculty').innerText = '-';
            document.getElementById('d_major').innerText = '-';
            document.getElementById('d_email').innerText = '-';
            document.getElementById('d_year').innerText = '-';
            document.getElementById('d_role').innerHTML = '-';
            document.getElementById('d_status').innerHTML = '-';
            document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm text-primary me-2"></div>กำลังโหลดข้อมูล...</td></tr>';
            
            detailModal.show();

            fetch('users.php?get_user_detail=' + uid)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'error') {
                        alert('เกิดข้อผิดพลาด: ' + data.message);
                        return;
                    }

                    const u = data.user;
                    document.getElementById('d_fullname').innerText = u.full_name || 'ไม่ระบุชื่อ';
                    document.getElementById('d_studentid').innerText = u.student_id || '-';
                    document.getElementById('d_email').innerText = u.email || '-';

                    const parsed = parsePsuFacultyMajor(u.student_id);
                    document.getElementById('d_faculty').innerText = u.faculty || u.department || parsed.faculty;
                    document.getElementById('d_major').innerText = u.major || parsed.major;

                    let stdId = (u.student_id || '').trim();
                    let yearPrefix = stdId.length >= 2 ? stdId.substring(0, 2) : '';
                    if (yearPrefix && !isNaN(yearPrefix)) {
                        document.getElementById('d_year').innerText = 'รหัสปี 25' + yearPrefix + ' (นักศึกษารหัส ' + yearPrefix + ')';
                    } else {
                        document.getElementById('d_year').innerText = 'บุคลากร / ไม่ระบุ';
                    }

                    document.getElementById('d_role').innerHTML = u.role === 'admin' ? '<span class="badge bg-primary">Admin (เจ้าหน้าที่)</span>' : '<span class="badge bg-secondary">User (นักศึกษา)</span>';
                    document.getElementById('d_status').innerHTML = u.status === 'active' ? '<span class="badge bg-success">ใช้งานได้</span>' : '<span class="badge bg-danger">ระงับการใช้งาน</span>';

                    const tbody = document.getElementById('historyTableBody');
                    tbody.innerHTML = '';
                    if (!data.history || data.history.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">ไม่มีประวัติการยืม-คืนในระบบ</td></tr>';
                    } else {
                        data.history.forEach(item => {
                            let badgeClass = 'bg-secondary';
                            let statusText = item.trans_status || '-';
                            if (item.trans_status === 'borrowed') { badgeClass = 'bg-primary'; statusText = 'กำลังยืม'; }
                            else if (item.trans_status === 'returned') { badgeClass = 'bg-success'; statusText = 'คืนสำเร็จ'; }
                            else if (item.trans_status === 'rejected') { badgeClass = 'bg-danger'; statusText = 'ไม่อนุมัติ'; }
                            else if (item.trans_status === 'pending_borrow') { badgeClass = 'bg-warning text-dark'; statusText = 'รออนุมัติยืม'; }
                            else if (item.trans_status === 'pending_return') { badgeClass = 'bg-info text-dark'; statusText = 'รออนุมัติคืน'; }

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
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('d_fullname').innerText = 'โหลดข้อมูลไม่สำเร็จ';
                });
        }
    </script>
</body>
</html>