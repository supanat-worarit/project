<?php
// history.php - หน้าประวัติการยืม-คืน 4 แท็บ
session_start();
require_once __DIR__ . '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: register.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$userStmt = $pdo->prepare("SELECT * FROM `user` WHERE user_id = ? LIMIT 1");
$userStmt->execute([$userId]);
$user = $userStmt->fetch() ?: [
    'full_name'  => $_SESSION['full_name'] ?? 'ผู้ใช้งาน',
    'student_id' => $_SESSION['student_id'] ?? '-'
];

function formatThaiDateTime($dateTimeStr) {
    if (!$dateTimeStr) return '-';
    $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $timestamp = strtotime($dateTimeStr);
    $d = date('j', $timestamp);
    $m = $thaiMonths[(int)date('n', $timestamp)];
    $y = date('Y', $timestamp);
    $h = date('H:i', $timestamp);
    return "{$d} {$m} {$y} ({$h} น.)";
}

$sql = "
    SELECT t.*, se.eq_name, se.eq_code, sc.category_name,
           ef.price AS fine_price, ef.payment_status
    FROM `transactions` t
    JOIN `sport_equipment` se ON t.eq_id = se.eq_id
    LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
    LEFT JOIN `equipment_fines` ef ON t.trans_id = ef.trans_id
    WHERE t.user_id = ?
    ORDER BY t.trans_id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$allRows = $stmt->fetchAll();

$borrowedList = [];
$returnedList = [];
$overdueList  = [];

foreach ($allRows as $row) {
    if ($row['trans_status'] === 'overdue' || ($row['payment_status'] && $row['payment_status'] === 'unpaid')) {
        $overdueList[] = $row;
    } elseif ($row['trans_status'] === 'returned') {
        $returnedList[] = $row;
    } else {
        $borrowedList[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ประวัติการยืม-คืน</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">
  <div class="w-full max-w-sm bg-white min-h-screen flex flex-col shadow-lg">

    <div class="bg-[#0022BA] text-white p-3 shadow-md">
      <div class="flex items-center justify-between relative mb-2">
        <button onclick="window.history.back()" class="text-xl font-bold text-gray-200 hover:text-white px-1">✕</button>
        <div class="flex items-center space-x-1 font-semibold text-sm">
          <span>🌐 ประวัติการยืม-คืน</span>
        </div>
        <a href="logout.php" onclick="return confirm('ต้องการออกจากระบบหรือไม่?')" class="text-[11px] bg-red-600 hover:bg-red-700 text-white px-2 py-0.5 rounded font-bold transition">ออก</a>
      </div>
      <div class="bg-[#1239C2] py-2 px-3 rounded text-center text-xs text-blue-100 font-medium">
        <?= htmlspecialchars($user['full_name']) ?> (<?= htmlspecialchars($user['student_id'] ?? $user['user_id']) ?>)
      </div>
    </div>

    <div class="grid grid-cols-4 bg-gray-300 text-[11px] font-semibold text-gray-800 border-b border-gray-300">
      <button onclick="switchTab('all', this)" class="tab-btn py-2.5 bg-[#0022BA] text-white">ทั้งหมด(<?= count($allRows) ?>)</button>
      <button onclick="switchTab('borrowed', this)" class="tab-btn py-2.5">กำลังยืม(<?= count($borrowedList) ?>)</button>
      <button onclick="switchTab('returned', this)" class="tab-btn py-2.5">คืนแล้ว(<?= count($returnedList) ?>)</button>
      <button onclick="switchTab('overdue', this)" class="tab-btn py-2.5">เกินกำหนด(<?= count($overdueList) ?>)</button>
    </div>

    <div class="p-3 space-y-3 flex-1 overflow-y-auto bg-gray-50">
      <?php if (empty($allRows)): ?>
        <div class="text-center text-gray-400 text-xs py-16">ไม่มีประวัติการทำรายการในบัญชีนี้</div>
      <?php else: ?>
        <?php foreach ($allRows as $item): 
          $isOverdue  = ($item['trans_status'] === 'overdue' || ($item['payment_status'] && $item['payment_status'] === 'unpaid'));
          $isReturned = ($item['trans_status'] === 'returned');
          $cardGroup   = $isOverdue ? 'overdue' : ($isReturned ? 'returned' : 'borrowed');
          $borderClass = $isOverdue ? 'border-2 border-red-500' : ($isReturned ? 'border-2 border-[#48D065]' : 'border-2 border-[#0022BA]');
          $badgeText   = $isOverdue ? 'เกินกำหนด / ค้างชำระ' : ($isReturned ? 'คืนสำเร็จ' : 'กำลังใช้งาน');
          $badgeBg     = $isOverdue ? 'bg-red-600' : ($isReturned ? 'bg-[#48D065]' : 'bg-[#0022BA]');
        ?>
        <div class="history-item <?= $cardGroup ?> bg-[#D9D9D9] <?= $borderClass ?> rounded relative p-3 pt-2 shadow-sm text-xs">
          <span class="absolute top-0 right-0 <?= $badgeBg ?> text-white text-[9px] px-2 py-0.5 font-bold rounded-bl">
            <?= $badgeText ?>
          </span>

          <div class="flex space-x-3 items-start mt-1">
            <div class="w-12 h-12 bg-[#0022BA] rounded flex-shrink-0 flex items-center justify-center text-white text-xs font-bold shadow-sm">
              อุปกรณ์
            </div>
            <div class="flex-1 text-gray-800">
              <h4 class="font-bold text-sm leading-tight text-gray-900"><?= htmlspecialchars($item['eq_name']) ?></h4>
              <p class="text-[10px] text-gray-600 mb-1">รหัสชิ้น: <?= htmlspecialchars($item['eq_code']) ?></p>
              <div class="text-[10px] text-gray-700 space-y-0.5">
                <p>วันที่ยืม: <?= formatThaiDateTime($item['borrow_time']) ?></p>
                <p class="<?= $isOverdue ? 'text-red-600 font-semibold' : '' ?>">กำหนดคืน: <?= formatThaiDateTime($item['due_time']) ?></p>
              </div>
            </div>
          </div>

          <div class="mt-3 pt-2 border-t border-gray-400/30 flex justify-between items-center text-xs">
            <?php if ($isOverdue): ?>
              <span class="font-bold text-red-600">ค่าปรับ: <?= number_format($item['fine_price'] ?? 20, 0) ?> บาท</span>
              <button onclick="alert('กรุณาส่งรูปสลิปเข้ามาในแชท LINE OA เพื่อชำระเงิน')" class="bg-red-600 hover:bg-red-700 text-white font-bold text-[11px] px-4 py-1 rounded shadow">ชำระค่าปรับ</button>
            <?php elseif ($isReturned): ?>
              <span class="text-gray-700 text-[11px]">สภาพของอุปกรณ์ : ไม่ชำรุด</span>
              <span class="font-bold text-[#1E8E3E]">เรียบร้อย</span>
            <?php else: ?>
              <span class="text-gray-700 text-[11px]">สถานะปกติ</span>
              <a href="liff-return.php" class="bg-[#0022BA] hover:bg-blue-900 text-white font-bold text-[11px] px-3 py-1 rounded shadow inline-block">ทำรายการคืน</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <script>
    function switchTab(tabName, btn) {
      document.querySelectorAll('.tab-btn').forEach(b => b.className = "tab-btn py-2.5 text-gray-800 hover:bg-gray-400 transition");
      btn.className = "tab-btn py-2.5 bg-[#0022BA] text-white transition";

      const cards = document.querySelectorAll('.history-item');
      cards.forEach(card => {
        if (tabName === 'all') card.classList.remove('hidden');
        else card.classList.contains(tabName) ? card.classList.remove('hidden') : card.classList.add('hidden');
      });
    }
  </script>
</body>
</html>