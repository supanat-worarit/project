<?php
// liff-borrow.php - หน้ายืมอุปกรณ์เดี่ยว (พร้อม Date Picker และอัปโหลดรูป)
session_start();
require_once __DIR__ . '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: register.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$uStmt = $pdo->prepare("SELECT * FROM `user` WHERE user_id = ? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch() ?: [
    'student_id' => $_SESSION['student_id'] ?? '6410210555',
    'full_name'  => $_SESSION['full_name'] ?? 'นักศึกษา'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    $eqId = (int)$_POST['eq_id'];
    
    $bDate = !empty($_POST['borrow_date']) ? $_POST['borrow_date'] : date('Y-m-d');
    $dDate = !empty($_POST['due_date'])    ? $_POST['due_date']    : date('Y-m-d', strtotime('+3 days'));

    $borrowTime  = $bDate . ' ' . date('H:i:s');
    $dueTime     = $dDate . ' 23:59:59';
    $borrowImage = 'default_borrow.jpg';

    if (isset($_FILES['borrow_image']) && $_FILES['borrow_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['borrow_image']['name'], PATHINFO_EXTENSION);
        $newFileName = 'borrow_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($_FILES['borrow_image']['tmp_name'], $uploadDir . $newFileName)) {
            $borrowImage = $newFileName;
        }
    }

    try {
        $chk = $pdo->prepare("SELECT status FROM `sport_equipment` WHERE eq_id = ?");
        $chk->execute([$eqId]);
        $currEq = $chk->fetch();

        if (!$currEq || $currEq['status'] !== 'avaliable') {
            echo "<script>alert('ขออภัย อุปกรณ์ชิ้นนี้ถูกยืมไปแล้ว'); window.location.href='liff-borrow.php';</script>";
            exit;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO `transactions` (eq_id, user_id, borrow_time, due_time, borrow_image, trans_status, analyze_damaged_status) 
            VALUES (?, ?, ?, ?, ?, 'borrowed', 'normal')
        ");
        $stmt->execute([$eqId, $userId, $borrowTime, $dueTime, $borrowImage]);
        $pdo->prepare("UPDATE `sport_equipment` SET status = 'borrowed' WHERE eq_id = ?")->execute([$eqId]);
        $pdo->commit();

        echo "<script>alert('บันทึกการยืมสำเร็จเรียบร้อย!'); window.location.href='liff-borrow.php';</script>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "');</script>";
    }
}

$stmt = $pdo->query("
    SELECT se.*, sc.category_name 
    FROM `sport_equipment` se
    LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
    ORDER BY se.eq_name ASC, se.eq_code ASC
");
$allRows = $stmt->fetchAll();

$groups = [];
foreach ($allRows as $row) {
    $name = $row['eq_name'];
    if (!isset($groups[$name])) {
        $groups[$name] = ['eq_name' => $name, 'category_name' => $row['category_name'] ?? 'ทั่วไป', 'total' => 0, 'items' => []];
    }
    $groups[$name]['total']++;
    $groups[$name]['items'][] = $row;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ยืมอุปกรณ์กีฬา</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">
  <div class="w-full max-w-sm bg-white min-h-screen flex flex-col shadow-lg">

    <div class="bg-[#0022BA] text-white p-3 shadow-md">
      <div class="flex items-center justify-between relative mb-2">
        <button onclick="window.history.back()" class="text-xl font-bold text-gray-200 hover:text-white px-1">✕</button>
        <div class="flex items-center space-x-1 font-semibold text-sm">
          <span>📦 ยืมอุปกรณ์กีฬา</span>
        </div>
        <a href="logout.php" onclick="return confirm('ต้องการออกจากระบบหรือไม่?')" class="text-[11px] bg-red-600 hover:bg-red-700 text-white px-2 py-0.5 rounded font-bold transition">ออก</a>
      </div>
      <div class="bg-[#1239C2] py-2 px-3 rounded text-center text-xs text-blue-100 font-medium">
        <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['student_id'] ?? $user['user_id']) ?>)
      </div>
    </div>

    <!-- Step 1: หมวดหมู่ -->
    <div id="step1" class="flex-1 overflow-y-auto">
      <div class="divide-y divide-gray-200">
        <?php foreach ($groups as $grp): ?>
        <div class="p-3 bg-white flex justify-between items-center hover:bg-gray-50 transition">
          <div>
            <div class="font-bold text-sm text-gray-800"><?= htmlspecialchars($grp['eq_name']) ?></div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars($grp['category_name']) ?></div>
          </div>
          <div class="flex items-center space-x-3">
            <span class="text-xs text-gray-600">จำนวน <?= $grp['total'] ?></span>
            <button onclick='goToStep2(<?= json_encode($grp, JSON_UNESCAPED_UNICODE) ?>)' class="bg-[#0022BA] hover:bg-blue-900 text-white text-xs px-3.5 py-1 rounded shadow font-semibold">ดู</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Step 2: รายการย่อย -->
    <div id="step2" class="hidden flex-1 overflow-y-auto">
      <div class="p-3 bg-gray-300 flex justify-between items-center border-b">
        <div>
          <div class="font-bold text-sm text-gray-800" id="step2-title-main">ชื่ออุปกรณ์</div>
          <div class="text-xs text-gray-600" id="step2-title-sub">หมวดหมู่</div>
        </div>
        <div class="flex items-center space-x-2">
          <span class="text-xs text-gray-700" id="step2-count">จำนวน 0</span>
          <button onclick="backToStep1()" class="text-xs text-blue-800 font-bold ml-1 hover:underline">❮ กลับ</button>
        </div>
      </div>
      <div id="step2-items-list" class="divide-y divide-gray-300 bg-white"></div>
    </div>

    <!-- Step 3: ฟอร์มยืนยันการยืม -->
    <div id="step3" class="hidden flex-1 p-4 flex flex-col justify-between">
      <form method="POST" action="liff-borrow.php" enctype="multipart/form-data" class="h-full flex flex-col justify-between">
        <input type="hidden" name="action" value="borrow">
        <input type="hidden" name="eq_id" id="form-eq-id">

        <div class="bg-gray-200 p-4 rounded-xl shadow-inner space-y-3">
          <div class="text-center font-bold text-sm leading-tight text-gray-800" id="selected-item-display">อุปกรณ์ที่เลือก</div>
          
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">วันที่ยืม 🗓️</label>
            <input 
              type="date" 
              name="borrow_date" 
              id="borrow-date-input" 
              value="<?= date('Y-m-d') ?>" 
              class="w-full bg-white border border-gray-400 text-gray-800 text-xs p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"
            >
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">วันที่คืน 📅</label>
            <input 
              type="date" 
              name="due_date" 
              id="due-date-input" 
              value="<?= date('Y-m-d', strtotime('+3 days')) ?>" 
              class="w-full bg-white border border-gray-400 text-gray-800 text-xs p-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm"
            >
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">ถ่ายรูปบัตรนักศึกษาคู่กับอุปกรณ์ 🖼️</label>
            <div class="flex border border-gray-400 bg-white rounded overflow-hidden">
              <label class="bg-gray-300 border-r border-gray-400 text-xs px-3 py-2 cursor-pointer hover:bg-gray-400 font-semibold flex-shrink-0">
                เลือกไฟล์
                <input type="file" name="borrow_image" id="file-input" accept="image/*" class="hidden" onchange="previewFile(this)">
              </label>
              <span id="file-label" class="text-[11px] text-gray-500 p-2 truncate">ยังไม่ได้เลือกไฟล์</span>
            </div>
          </div>
        </div>

        <div class="space-y-2 mt-6">
          <button type="submit" class="w-full bg-[#0022BA] hover:bg-blue-900 text-white py-2.5 rounded text-sm font-bold shadow transition">ยืนยันการยืม</button>
          <button type="button" onclick="backToStep2()" class="w-full bg-gray-300 text-gray-700 py-2 rounded text-xs font-bold transition">ย้อนกลับ</button>
        </div>
      </form>
    </div>

  </div>

  <script>
    function goToStep2(group) {
      document.getElementById('step2-title-main').innerText = group.eq_name;
      document.getElementById('step2-title-sub').innerText = group.category_name || '';
      document.getElementById('step2-count').innerText = 'จำนวน ' + group.total;

      const container = document.getElementById('step2-items-list');
      container.innerHTML = '';

      group.items.forEach(item => {
        const isAvail = (item.status === 'avaliable');
        let statusBadge = isAvail ? '<span class="text-xs font-bold text-green-600">พร้อมใช้งาน</span>' : '<span class="text-xs font-bold text-amber-500">ใช้งานอยู่</span>';
        let btnHtml = isAvail 
          ? `<button onclick="goToStep3(${item.eq_id}, '${item.eq_name}', '${item.eq_code}')" class="bg-[#0022BA] hover:bg-blue-900 text-white text-xs px-3.5 py-1 rounded shadow font-semibold">ยืม</button>`
          : `<button disabled class="bg-gray-400 text-white text-xs px-3.5 py-1 rounded cursor-not-allowed">ยืม</button>`;

        container.insertAdjacentHTML('beforeend', `
          <div class="p-3 bg-gray-100 flex justify-between items-center">
            <div>
              <div class="text-sm font-semibold text-gray-800">${item.eq_name}</div>
              <div class="text-xs text-gray-600">รหัส: ${item.eq_code}</div>
            </div>
            <div class="flex items-center space-x-4">${statusBadge}${btnHtml}</div>
          </div>
        `);
      });

      document.getElementById('step1').classList.add('hidden');
      document.getElementById('step2').classList.remove('hidden');
    }

    function backToStep1() {
      document.getElementById('step2').classList.add('hidden');
      document.getElementById('step1').classList.remove('hidden');
    }

    function goToStep3(eqId, eqName, eqCode) {
      document.getElementById('form-eq-id').value = eqId;
      document.getElementById('selected-item-display').innerHTML = `${eqName}<br><span class="text-blue-800 font-bold">${eqCode}</span>`;
      document.getElementById('step2').classList.add('hidden');
      document.getElementById('step3').classList.remove('hidden');
    }

    function backToStep2() {
      document.getElementById('step3').classList.add('hidden');
      document.getElementById('step2').classList.remove('hidden');
    }

    function previewFile(input) {
      if (input.files && input.files[0]) {
        document.getElementById('file-label').innerText = input.files[0].name;
      }
    }
  </script>
</body>
</html>