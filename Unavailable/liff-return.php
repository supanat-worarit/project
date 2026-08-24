<?php
// liff-return.php - หน้าคืนอุปกรณ์เดี่ยว
session_start();
require_once __DIR__ . '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: register.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$uStmt = $pdo->prepare("SELECT * FROM `user` WHERE user_id = ? LIMIT 1");
$uStmt->execute([$userId]);
$user = $uStmt->fetch() ?: [
    'student_id' => $_SESSION['student_id'] ?? '6410210555',
    'full_name'  => $_SESSION['full_name'] ?? 'นักศึกษา'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return') {
    $transId     = (int)$_POST['trans_id'];
    $returnTime  = date('Y-m-d H:i:s');
    $returnImage = 'default_return.jpg';

    if (isset($_FILES['return_image']) && $_FILES['return_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['return_image']['name'], PATHINFO_EXTENSION);
        $newFileName = 'return_' . time() . '_' . rand(100, 999) . '.' . $ext;
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
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

            $updTrans = $pdo->prepare("UPDATE `transactions` SET return_time = ?, return_image = ?, trans_status = ? WHERE trans_id = ?");
            $updTrans->execute([$returnTime, $returnImage, $newStatus, $transId]);

            $pdo->prepare("UPDATE `sport_equipment` SET status = 'avaliable' WHERE eq_id = ?")->execute([$trans['eq_id']]);

            if ($isOverdue) {
                $daysOver = max(1, ceil((strtotime($returnTime) - strtotime($trans['due_time'])) / 86400));
                $fineAmount = $daysOver * 20.00;
                $fineId = rand(100000, 999999);

                $insFine = $pdo->prepare("INSERT INTO `equipment_fines` (fine_id, trans_id, price, payment_status, slipok_status) VALUES (?, ?, ?, 'unpaid', 'pending')");
                $insFine->execute([$fineId, $transId, $fineAmount]);
            }

            $pdo->commit();
            $msg = $isOverdue ? "ส่งคืนเกินกำหนด ({$daysOver} วัน) มีค่าปรับ " . number_format($fineAmount, 0) . " บาท" : "คืนอุปกรณ์สำเร็จเรียบร้อย!";
            echo "<script>alert('{$msg}'); window.location.href='liff-return.php';</script>";
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}

$stmt = $pdo->prepare("
    SELECT t.*, se.eq_name, se.eq_code, sc.category_name 
    FROM `transactions` t
    JOIN `sport_equipment` se ON t.eq_id = se.eq_id
    LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
    WHERE t.user_id = ? AND t.trans_status IN ('borrowed', 'overdue')
    ORDER BY t.due_time ASC
");
$stmt->execute([$userId]);
$borrowedItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>คืนอุปกรณ์กีฬา</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">
  <div class="w-full max-w-sm bg-white min-h-screen flex flex-col shadow-lg">

    <div class="bg-[#0022BA] text-white p-3 shadow-md">
      <div class="flex items-center justify-between relative mb-2">
        <button onclick="closeLiffWindow()" class="text-xl font-bold text-gray-200 hover:text-white">✕</button>
        <div class="flex items-center space-x-1 font-semibold text-sm mx-auto pr-4">
          <span>🔄 คืนอุปกรณ์กีฬา</span>
        </div>
      </div>
      <div class="bg-[#1239C2] py-2 px-3 rounded text-center text-xs text-blue-100 font-medium">
        <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['student_id']) ?>)
      </div>
    </div>

    <div id="return-list-step" class="flex-1 p-3 space-y-3 overflow-y-auto bg-gray-50">
      <?php if (empty($borrowedItems)): ?>
        <div class="text-center text-gray-400 text-xs py-20">คุณไม่มีรายการอุปกรณ์ที่ค้างคืนในขณะนี้</div>
      <?php else: ?>
        <div class="text-xs font-bold text-gray-700 mb-2">รายการที่ต้องส่งคืน (<?= count($borrowedItems) ?> ชิ้น):</div>
        <?php foreach ($borrowedItems as $item): 
          $isLate = (strtotime(date('Y-m-d H:i:s')) > strtotime($item['due_time']));
        ?>
        <div class="bg-white border-2 <?= $isLate ? 'border-red-400' : 'border-gray-200' ?> rounded-xl p-3.5 shadow-sm space-y-2">
          <div class="flex justify-between items-start">
            <div>
              <h4 class="font-bold text-sm text-gray-900"><?= htmlspecialchars($item['eq_name']) ?></h4>
              <p class="text-xs text-blue-800 font-mono font-bold">รหัสชิ้น: <?= htmlspecialchars($item['eq_code']) ?></p>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded text-white <?= $isLate ? 'bg-red-600' : 'bg-blue-600' ?>">
              <?= $isLate ? 'เกินกำหนดส่ง' : 'กำลังใช้งาน' ?>
            </span>
          </div>

          <div class="text-[11px] text-gray-600 space-y-0.5 border-t pt-2 border-gray-100">
            <div>วันที่ยืม: <?= date('d/m/Y (H:i)', strtotime($item['borrow_time'])) ?></div>
            <div class="<?= $isLate ? 'text-red-600 font-bold' : '' ?>">กำหนดคืน: <?= date('d/m/Y (H:i)', strtotime($item['due_time'])) ?></div>
          </div>

          <div class="pt-2">
            <button onclick="goToReturnConfirm(<?= $item['trans_id'] ?>, '<?= htmlspecialchars($item['eq_name']) ?>', '<?= htmlspecialchars($item['eq_code']) ?>', '<?= date('d/m/Y', strtotime($item['borrow_time'])) ?>')" class="w-full bg-[#B71C1C] hover:bg-red-800 text-white text-xs font-bold py-2 rounded shadow transition">
              ทำรายการคืนชิ้นนี้
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ฟอร์มถ่ายภาพคืน -->
    <div id="return-confirm-step" class="hidden flex-1 p-4 flex flex-col justify-between">
      <form method="POST" action="liff-return.php" enctype="multipart/form-data" id="return-form" class="h-full flex flex-col justify-between">
        <input type="hidden" name="action" value="return">
        <input type="hidden" name="trans_id" id="form-trans-id">

        <div class="bg-gray-200 p-4 rounded shadow-inner space-y-3">
          <div class="text-center font-bold text-sm text-gray-800" id="return-item-display">อุปกรณ์ที่จะส่งคืน</div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">วันที่ส่งคืน 📅</label>
            <input type="text" id="return-date-display" value="<?= date('d/m/Y') ?>" class="w-full bg-white border border-gray-300 text-xs p-2 rounded focus:outline-none" readonly>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-700 mb-1">ถ่ายรูปสภาพอุปกรณ์ที่ส่งคืน (Vision AI) 🖼️</label>
            <div class="flex border border-gray-400 bg-white rounded">
              <label class="bg-gray-300 border-r border-gray-400 text-xs px-3 py-2 cursor-pointer hover:bg-gray-400 font-semibold">
                เลือกรูป / ถ่ายภาพ
                <input type="file" name="return_image" id="return-file-input" accept="image/*" class="hidden" onchange="previewReturnFile(this)">
              </label>
              <span id="return-file-label" class="text-[11px] text-gray-500 p-2 truncate">ยังไม่ได้เลือกรูปถ่าย</span>
            </div>
          </div>
        </div>

        <div class="space-y-2 mt-6">
          <button type="submit" class="w-full bg-[#B71C1C] hover:bg-red-800 text-white py-2.5 rounded text-sm font-bold shadow transition">ยืนยันการคืนอุปกรณ์</button>
          <button type="button" onclick="backToReturnList()" class="w-full bg-gray-300 text-gray-700 py-2 rounded text-xs font-bold transition">ย้อนกลับ</button>
        </div>
      </form>
    </div>

  </div>

  <script>
    async function initLiff() {
      try {
        await liff.init({ liffId: "YOUR_LIFF_ID" });
      } catch (err) {}
    }
    initLiff();

    function closeLiffWindow() {
      if (typeof liff !== "undefined" && liff.isInClient()) liff.closeWindow();
      else window.location.href = 'liff_app.php?page=return';
    }

    function goToReturnConfirm(transId, name, code, bDate) {
      document.getElementById('form-trans-id').value = transId;
      document.getElementById('return-item-display').innerHTML = `${name}<br><span class="text-blue-800 font-bold">${code}</span>`;
      document.getElementById('return-list-step').classList.add('hidden');
      document.getElementById('return-confirm-step').classList.remove('hidden');
    }

    function backToReturnList() {
      document.getElementById('return-confirm-step').classList.add('hidden');
      document.getElementById('return-list-step').classList.remove('hidden');
    }

    function previewReturnFile(input) {
      if (input.files && input.files[0]) {
        document.getElementById('return-file-label').innerText = input.files[0].name;
      }
    }
  </script>
</body>
</html>