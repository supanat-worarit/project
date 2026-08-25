<?php
// liff_app.php - รวมหน้ายืม คืน ประวัติ (เลือกวันที่ยืม-คืนได้อิสระ + แนบรูปถ่าย)
session_start();
require_once __DIR__ . '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: register.php");
    exit;
}

try {
    $pdo->exec("
        UPDATE `transactions` 
        SET `trans_status` = 'overdue' 
        WHERE `trans_status` = 'borrowed' 
          AND `due_time` < NOW()
    ");
} catch (Exception $e) {
}

$userId = (int)$_SESSION['user_id'];
$page = $_GET['page'] ?? 'borrow';

// สร้างโฟลเดอร์ uploads อัตโนมัติหากยังไม่มี
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// 1. ดึงข้อมูลผู้ใช้งานปัจจุบัน
$uStmt = $pdo->prepare("SELECT * FROM `user` WHERE user_id = ? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch() ?: [
    'full_name'  => $_SESSION['full_name'] ?? 'นักศึกษา',
    'student_id' => $_SESSION['student_id'] ?? '6410210555'
];

// 2. จัดการบันทึกการยืม (รับค่าจาก Date Picker ที่ผู้ใช้เลือก)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow') {
    $eqId = (int)$_POST['eq_id'];
    
    // ดึงวันที่ที่เลือกมาจากฟอร์ม (ถ้าไม่ได้เลือก ใช้วันที่ปัจจุบัน)
    $bDate = !empty($_POST['borrow_date']) ? $_POST['borrow_date'] : date('Y-m-d');
    $dDate = !empty($_POST['due_date'])    ? $_POST['due_date']    : date('Y-m-d', strtotime('+3 days'));
    
    $borrowTime  = $bDate . ' ' . date('H:i:s');
    $dueTime     = $dDate . ' 23:59:59';
    $borrowImage = 'default_borrow.jpg';

    // จัดการอัปโหลดรูปภาพบัตรนักศึกษา
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
            echo "<script>alert('ขออภัย อุปกรณ์ชิ้นนี้ถูกยืมไปแล้ว'); window.location.href='liff_app.php?page=borrow';</script>";
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

        echo "<script>alert('บันทึกการยืมสำเร็จเรียบร้อย!'); window.location.href='liff_app.php?page=borrow';</script>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 3. จัดการบันทึกการคืน (พร้อมรูปภาพสภาพอุปกรณ์)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    $transId     = (int)$_POST['trans_id'];
    $returnTime  = date('Y-m-d H:i:s');
    $returnImage = 'default_return.jpg';

    if (isset($_FILES['return_image']) && $_FILES['return_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['return_image']['name'], PATHINFO_EXTENSION);
        $newFileName = 'return_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($_FILES['return_image']['tmp_name'], $uploadDir . $newFileName)) {
            $returnImage = $newFileName;
        }
    }

    try {
        $tStmt = $pdo->prepare("SELECT * FROM `transactions` WHERE trans_id = ? AND user_id = ?");
        $tStmt->execute([$transId, $userId]);
        $trans = $tStmt->fetch();

        if ($trans && $trans['trans_status'] !== 'returned') {
            $pdo->beginTransaction();
            $isOverdue = strtotime($returnTime) > strtotime($trans['due_time']);
            $newStatus = $isOverdue ? 'overdue' : 'returned';

            $upd = $pdo->prepare("UPDATE `transactions` SET return_time = ?, return_image = ?, trans_status = ? WHERE trans_id = ?");
            $upd->execute([$returnTime, $returnImage, $newStatus, $transId]);

            $pdo->prepare("UPDATE `sport_equipment` SET status = 'avaliable' WHERE eq_id = ?")->execute([$trans['eq_id']]);

            if ($isOverdue) {
                $daysOver = max(1, ceil((strtotime($returnTime) - strtotime($trans['due_time'])) / 86400));
                $fineAmount = $daysOver * 20.00;
                $insFine = $pdo->prepare("INSERT INTO `equipment_fines` (fine_id, trans_id, price, payment_status, slipok_status) VALUES (?, ?, ?, 'unpaid', 'pending')");
                $insFine->execute([$fineId, $transId, $fineAmount]);
            }

            $pdo->commit();
            $msg = $isOverdue ? "คืนเกินกำหนด มีค่าปรับ " . number_format($fineAmount, 0) . " บาท" : "คืนอุปกรณ์สำเร็จเรียบร้อย!";
            echo "<script>alert('{$msg}'); window.location.href='liff_app.php?page=return';</script>";
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('เกิดข้อผิดพลาด: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// 4. ดึงข้อมูลอุปกรณ์ (หน้ายืม)
$allEq = $pdo->query("
    SELECT se.*, sc.category_name 
    FROM `sport_equipment` se
    LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
    ORDER BY se.eq_name ASC, se.eq_code ASC
")->fetchAll();

$groups = [];
foreach ($allEq as $row) {
    $name = $row['eq_name'];
    if (!isset($groups[$name])) {
        $groups[$name] = ['eq_name' => $name, 'category_name' => $row['category_name'] ?? 'ทั่วไป', 'total' => 0, 'items' => []];
    }
    $groups[$name]['total']++;
    $groups[$name]['items'][] = $row;
}

// 5. ดึงรายการกำลังยืม (หน้าคืน)
$activeBorrows = $pdo->prepare("
    SELECT t.*, se.eq_name, se.eq_code 
    FROM `transactions` t
    JOIN `sport_equipment` se ON t.eq_id = se.eq_id
    WHERE t.user_id = ? 
      AND t.trans_status IN ('borrowed', 'overdue') 
      AND t.return_time IS NULL 
    ORDER BY t.due_time ASC
");
$activeBorrows->execute([$userId]);
$borrowList = $activeBorrows->fetchAll();

// 6. ดึงข้อมูลประวัติ (หน้าประวัติ)
$histStmt = $pdo->prepare("
    SELECT t.*, se.eq_name, se.eq_code, ef.price AS fine_price, ef.payment_status
    FROM `transactions` t
    JOIN `sport_equipment` se ON t.eq_id = se.eq_id
    LEFT JOIN `equipment_fines` ef ON t.trans_id = ef.trans_id
    WHERE t.user_id = ?
    ORDER BY t.trans_id DESC
");
$histStmt->execute([$userId]);
$historyList = $histStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ระบบยืม-คืนอุปกรณ์กีฬา PSU</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">
  <div class="w-full max-w-sm bg-white min-h-screen flex flex-col shadow-lg pb-16">

    <!-- Header ด้านบน -->
    <div class="bg-[#0022BA] text-white p-3 shadow">
      <div class="flex items-center justify-between">
        <span class="text-sm font-bold" id="app-header-title">📦 ยืมอุปกรณ์กีฬา</span>
        <div class="flex items-center space-x-2">
          <span class="text-[11px] bg-blue-900 border border-blue-400 px-2 py-0.5 rounded text-blue-100 font-mono">
            <?= htmlspecialchars($user['student_id'] ?? $user['user_id']) ?>
          </span>
          <a href="logout.php" onclick="return confirm('ต้องการออกจากระบบหรือไม่?')" class="text-[11px] bg-red-600 hover:bg-red-700 text-white px-2 py-0.5 rounded font-bold transition">
            ออกจากระบบ
          </a>
        </div>
      </div>
      <div class="text-[11px] text-blue-200 mt-1 truncate">
        ผู้ใช้งาน: <?= htmlspecialchars($user['full_name']) ?>
      </div>
    </div>

    <!-- ==================== TAB 1: หน้ายืมอุปกรณ์ ==================== -->
    <div id="tab-content-borrow" class="tab-view flex-1 flex flex-col <?= $page !== 'borrow' ? 'hidden' : '' ?>">
      <!-- Step 1: รายการหมวดหมู่อุปกรณ์ -->
      <div id="borrow-step-1" class="flex-1 overflow-y-auto">
        <div class="divide-y divide-gray-200">
          <?php foreach ($groups as $grp): ?>
          <div class="p-3 bg-white flex justify-between items-center hover:bg-gray-50 transition">
            <div>
              <div class="font-bold text-sm text-gray-800"><?= htmlspecialchars($grp['eq_name']) ?></div>
              <div class="text-xs text-gray-500"><?= htmlspecialchars($grp['category_name']) ?></div>
            </div>
            <div class="flex items-center space-x-3">
              <span class="text-xs text-gray-600">จำนวน <?= $grp['total'] ?></span>
              <button onclick='openBorrowSubList(<?= json_encode($grp, JSON_UNESCAPED_UNICODE) ?>)' class="bg-[#0022BA] text-white text-xs px-3.5 py-1 rounded shadow font-semibold">ดู</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Step 2: รายการย่อยตามรหัสชิ้น -->
      <div id="borrow-step-2" class="hidden flex-1 overflow-y-auto">
        <div class="p-3 bg-gray-200 flex justify-between items-center border-b">
          <div>
            <div class="font-bold text-sm text-gray-800" id="sub-eq-name">ชื่ออุปกรณ์</div>
            <div class="text-xs text-gray-600" id="sub-eq-cat">หมวดหมู่</div>
          </div>
          <button onclick="backToBorrow1()" class="text-xs text-blue-800 font-bold hover:underline">❮ กลับ</button>
        </div>
        <div id="sub-eq-items" class="divide-y divide-gray-200 bg-white"></div>
      </div>

      <!-- Step 3: ฟอร์มยืนยันการยืม (เลือกวันที่ได้อิสระ) -->
      <div id="borrow-step-3" class="hidden flex-1 p-4 flex flex-col justify-between">
        <form method="POST" action="liff_app.php?page=borrow" enctype="multipart/form-data" class="h-full flex flex-col justify-between">
          <input type="hidden" name="action" value="borrow">
          <input type="hidden" name="eq_id" id="borrow-form-eq-id">

          <div class="bg-gray-200 p-4 rounded-xl shadow-inner space-y-3">
            <div class="text-center font-bold text-sm text-gray-800" id="borrow-selected-title">อุปกรณ์ที่เลือก</div>
            
            <!-- ช่องเลือกวันที่ยืม (เปิดให้กดปฏิทินเลือกได้) -->
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

            <!-- ช่องเลือกกำหนดคืน (เปิดให้กดปฏิทินเลือกได้) -->
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">กำหนดส่งคืน 📅</label>
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
                  <input type="file" name="borrow_image" accept="image/*" class="hidden" onchange="previewBorrowFile(this)">
                </label>
                <span id="borrow-file-label" class="text-[11px] text-gray-500 p-2 truncate">ยังไม่ได้เลือกไฟล์</span>
              </div>
            </div>
          </div>

          <div class="space-y-2 mt-4">
            <button type="submit" class="w-full bg-[#0022BA] hover:bg-blue-900 text-white py-2.5 rounded text-sm font-bold shadow transition">ยืนยันการยืม</button>
            <button type="button" onclick="backToBorrow2()" class="w-full bg-gray-300 text-gray-700 py-2 rounded text-xs font-bold transition">ย้อนกลับ</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ==================== TAB 2: หน้าคืนอุปกรณ์ ==================== -->
    <div id="tab-content-return" class="tab-view flex-1 flex flex-col <?= $page !== 'return' ? 'hidden' : '' ?>">
      <div id="return-list-view" class="p-3 space-y-2 flex-1 overflow-y-auto">
        <?php if (empty($borrowList)): ?>
          <div class="text-center text-gray-400 text-xs py-16">ไม่มีรายการอุปกรณ์ที่ค้างคืนในขณะนี้</div>
        <?php else: ?>
          <?php foreach ($borrowList as $bl): 
            $isLate = (strtotime(date('Y-m-d H:i:s')) > strtotime($bl['due_time']));
          ?>
          <div class="p-3 bg-white border border-gray-200 rounded-xl shadow-sm flex justify-between items-center">
            <div>
              <div class="font-bold text-sm text-gray-800"><?= htmlspecialchars($bl['eq_name']) ?></div>
              <div class="text-xs text-gray-500">รหัส: <?= htmlspecialchars($bl['eq_code']) ?> | กำหนด: <?= date('d/m/Y', strtotime($bl['due_time'])) ?></div>
              <?php if ($isLate): ?>
                <span class="text-[10px] text-red-600 font-bold">เกินกำหนดส่งคืน</span>
              <?php endif; ?>
            </div>
            <button onclick="openReturnConfirm(<?= $bl['trans_id'] ?>, '<?= htmlspecialchars($bl['eq_name']) ?>', '<?= htmlspecialchars($bl['eq_code']) ?>')" class="bg-[#B71C1C] hover:bg-red-800 text-white text-xs px-3.5 py-1.5 rounded font-bold shadow">คืน</button>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- ฟอร์มยืนยันคืนพร้อมช่องอัปโหลดรูปภาพ -->
      <div id="return-confirm-view" class="hidden p-4 flex-1 flex flex-col justify-between">
        <form method="POST" action="liff_app.php?page=return" enctype="multipart/form-data" class="h-full flex flex-col justify-between">
          <input type="hidden" name="action" value="return">
          <input type="hidden" name="trans_id" id="return-form-trans-id">

          <div class="bg-gray-200 p-4 rounded-xl shadow-inner space-y-3">
            <div class="text-center font-bold text-sm text-gray-800" id="return-selected-title">คืนอุปกรณ์</div>
            <div>
              <label class="block text-xs font-semibold text-gray-700 mb-1">ถ่ายรูปสภาพอุปกรณ์ที่ส่งคืน (Vision AI) 🖼️</label>
              <div class="flex border border-gray-400 bg-white rounded overflow-hidden">
                <label class="bg-gray-300 border-r border-gray-400 text-xs px-3 py-2 cursor-pointer hover:bg-gray-400 font-semibold flex-shrink-0">
                  ถ่ายภาพ / เลือกรูป
                  <input type="file" name="return_image" accept="image/*" class="hidden" onchange="previewReturnFile(this)">
                </label>
                <span id="return-file-label" class="text-[11px] text-gray-500 p-2 truncate">ยังไม่ได้เลือกรูปถ่าย</span>
              </div>
              <p class="text-[10px] text-gray-500 mt-1">* รูปถ่ายจะถูกนำไปตรวจสภาพการชำรุดของอุปกรณ์</p>
            </div>
          </div>

          <div class="space-y-2 mt-4">
            <button type="submit" class="w-full bg-[#B71C1C] hover:bg-red-800 text-white py-2.5 rounded text-sm font-bold shadow transition">ยืนยันการคืน</button>
            <button type="button" onclick="cancelReturnConfirm()" class="w-full bg-gray-300 text-gray-700 py-2 rounded text-xs font-bold transition">ยกเลิก</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ==================== TAB 3: หน้าประวัติ ==================== -->
    <div id="tab-content-history" class="tab-view flex-1 flex flex-col <?= $page !== 'history' ? 'hidden' : '' ?>">
      <div class="grid grid-cols-4 bg-gray-200 text-[11px] font-semibold text-gray-700 border-b">
        <button onclick="filterHistoryCards('all', this)" class="hist-nav py-2.5 bg-[#0022BA] text-white">ทั้งหมด</button>
        <button onclick="filterHistoryCards('borrowed', this)" class="hist-nav py-2.5">กำลังยืม</button>
        <button onclick="filterHistoryCards('returned', this)" class="hist-nav py-2.5">คืนแล้ว</button>
        <button onclick="filterHistoryCards('overdue', this)" class="hist-nav py-2.5">เกินกำหนด</button>
      </div>

      <div class="p-3 space-y-2.5 flex-1 overflow-y-auto bg-gray-50">
        <?php if (empty($historyList)): ?>
          <div class="text-center text-gray-400 text-xs py-16">ไม่มีประวัติการทำรายการ</div>
        <?php else: ?>
          <?php foreach ($historyList as $h): 
            $isOver = ($h['trans_status'] === 'overdue' || ($h['payment_status'] && $h['payment_status'] === 'unpaid'));
            $isRet = ($h['trans_status'] === 'returned');
            $typeClass = $isOver ? 'overdue border-red-500 bg-red-50' : ($isRet ? 'returned border-green-500 bg-green-50' : 'borrowed border-blue-600 bg-blue-50');
          ?>
          <div class="hist-card <?= $typeClass ?> border-2 rounded-xl p-3 shadow-sm text-xs relative">
            <div class="flex justify-between items-start">
              <div>
                <h4 class="font-bold text-gray-800"><?= htmlspecialchars($h['eq_name']) ?> (<?= htmlspecialchars($h['eq_code']) ?>)</h4>
                <p class="text-[10px] text-gray-500 mt-0.5">ยืม: <?= date('d/m/Y', strtotime($h['borrow_time'])) ?> | กำหนด: <?= date('d/m/Y', strtotime($h['due_time'])) ?></p>
                <?php if ($isOver): ?>
                  <p class="text-xs font-bold text-red-600 mt-1">ค่าปรับ: <?= number_format($h['fine_price'] ?? 20, 0) ?> บาท</p>
                <?php endif; ?>
              </div>
              <span class="text-[9px] font-bold px-2 py-0.5 rounded text-white <?= $isOver ? 'bg-red-600' : ($isRet ? 'bg-green-600' : 'bg-blue-600') ?>">
                <?= $isOver ? 'เกินกำหนด' : ($isRet ? 'คืนแล้ว' : 'กำลังยืม') ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- แถบเมนูด้านล่าง (Bottom Navbar) -->
    <div class="fixed bottom-0 w-full max-w-sm bg-white border-t border-gray-300 grid grid-cols-3 text-center text-xs py-2 shadow-lg">
      <button onclick="navigateTab('borrow')" id="nav-btn-borrow" class="flex flex-col items-center <?= $page === 'borrow' ? 'text-blue-700 font-bold' : 'text-gray-500' ?>">
        <span class="text-base">📦</span>
        <span>ยืมอุปกรณ์</span>
      </button>
      <button onclick="navigateTab('return')" id="nav-btn-return" class="flex flex-col items-center <?= $page === 'return' ? 'text-blue-700 font-bold' : 'text-gray-500' ?>">
        <span class="text-base">🔄</span>
        <span>คืนอุปกรณ์</span>
      </button>
      <button onclick="navigateTab('history')" id="nav-btn-history" class="flex flex-col items-center <?= $page === 'history' ? 'text-blue-700 font-bold' : 'text-gray-500' ?>">
        <span class="text-base">📋</span>
        <span>ประวัติการยืม</span>
      </button>
    </div>

  </div>

  <script>
    // ซ่อน/แสดงแท็บหลัก และเปลี่ยนชื่อหัวเรื่องด้านบน
    function navigateTab(tabName) {
      document.querySelectorAll('.tab-view').forEach(el => el.classList.add('hidden'));
      document.getElementById(`tab-content-${tabName}`).classList.remove('hidden');

      document.querySelectorAll('div.fixed button').forEach(b => {
        b.className = "flex flex-col items-center text-gray-500";
      });
      document.getElementById(`nav-btn-${tabName}`).className = "flex flex-col items-center text-blue-700 font-bold";
      
      const titles = { borrow: '📦 ยืมอุปกรณ์กีฬา', return: '🔄 คืนอุปกรณ์', history: '📋 ประวัติการยืม-คืน' };
      document.getElementById('app-header-title').innerText = titles[tabName];
    }

    // ฟังก์ชันที่คอยสลับหน้าจอ Step 1, 2, 3 ในขั้นตอนการยืม
    function openBorrowSubList(grp) {
      document.getElementById('sub-eq-name').innerText = grp.eq_name;
      document.getElementById('sub-eq-cat').innerText = grp.category_name || '';
      const list = document.getElementById('sub-eq-items');
      list.innerHTML = '';

      grp.items.forEach(it => {
        const isAvail = (it.status === 'avaliable');
        list.insertAdjacentHTML('beforeend', `
          <div class="p-3 flex justify-between items-center">
            <div>
              <div class="text-sm font-semibold">${it.eq_name}</div>
              <div class="text-xs text-gray-500">รหัส: ${it.eq_code}</div>
            </div>
            <div class="flex items-center space-x-2">
              <span class="text-xs font-bold ${isAvail ? 'text-green-600' : 'text-amber-500'}">${isAvail ? 'พร้อมใช้งาน' : 'ใช้งานอยู่'}</span>
              <button ${isAvail ? '' : 'disabled'} onclick="openBorrowConfirm(${it.eq_id}, '${it.eq_name}', '${it.eq_code}')" class="${isAvail ? 'bg-[#0022BA]' : 'bg-gray-400 cursor-not-allowed'} text-white text-xs px-3.5 py-1 rounded shadow font-semibold">ยืม</button>
            </div>
          </div>
        `);
      });
      document.getElementById('borrow-step-1').classList.add('hidden');
      document.getElementById('borrow-step-2').classList.remove('hidden');
    }

    function backToBorrow1() {
      document.getElementById('borrow-step-2').classList.add('hidden');
      document.getElementById('borrow-step-1').classList.remove('hidden');
    }

    function openBorrowConfirm(eqId, name, code) {
      document.getElementById('borrow-form-eq-id').value = eqId;
      document.getElementById('borrow-selected-title').innerHTML = `${name}<br><span class="text-blue-800 font-bold">${code}</span>`;
      document.getElementById('borrow-step-2').classList.add('hidden');
      document.getElementById('borrow-step-3').classList.remove('hidden');
    }

    function backToBorrow2() {
      document.getElementById('borrow-step-3').classList.add('hidden');
      document.getElementById('borrow-step-2').classList.remove('hidden');
    }

    function openReturnConfirm(transId, name, code) {
      document.getElementById('return-form-trans-id').value = transId;
      document.getElementById('return-selected-title').innerHTML = `ยืนยันคืน: ${name}<br><span class="text-blue-800 font-bold">${code}</span>`;
      document.getElementById('return-list-view').classList.add('hidden');
      document.getElementById('return-confirm-view').classList.remove('hidden');
    }

    function cancelReturnConfirm() {
      document.getElementById('return-confirm-view').classList.add('hidden');
      document.getElementById('return-list-view').classList.remove('hidden');
    }

    // ฟังก์ชันที่คอยจับว่าถ้าผู้ใช้อัปโหลดรูปแล้ว ให้เปลี่ยนข้อความจาก "ยังไม่ได้เลือกไฟล์" เป็นชื่อไฟล์ที่เลือก เพื่อให้ผู้ใช้มั่นใจว่าแนบรูปติดแล้ว
    function previewBorrowFile(input) {
      if (input.files && input.files[0]) {
        document.getElementById('borrow-file-label').innerText = input.files[0].name;
      }
    }

    function previewReturnFile(input) {
      if (input.files && input.files[0]) {
        document.getElementById('return-file-label').innerText = input.files[0].name;
      }
    }

    // ใช้จัดการปุ่มฟิลเตอร์ในหน้าประวัติ เพื่อกรองให้แสดงเฉพาะการ์ดที่มีคลาสตรงกับสถานะที่ผู้ใช้ต้องการ
    function filterHistoryCards(type, btn) {
      document.querySelectorAll('.hist-nav').forEach(b => b.className = "hist-nav py-2.5");
      btn.className = "hist-nav py-2.5 bg-[#0022BA] text-white";

      document.querySelectorAll('.hist-card').forEach(c => {
        if (type === 'all') c.classList.remove('hidden');
        else c.classList.contains(type) ? c.classList.remove('hidden') : c.classList.add('hidden');
      });
    }
  </script>
</body>
</html>