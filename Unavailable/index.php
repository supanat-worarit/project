<?php
// index.php - Admin Dashboard
require_once __DIR__ . '../../config/db.php';

// อัปเดตรายการเกินกำหนดอัตโนมัติ
$pdo->exec("UPDATE `transactions` SET trans_status = 'overdue' WHERE trans_status = 'borrowed' AND due_time < NOW()");

$statTotalEq   = $pdo->query("SELECT COUNT(*) FROM `sport_equipment`")->fetchColumn();
$statAvailEq   = $pdo->query("SELECT COUNT(*) FROM `sport_equipment` WHERE status = 'avaliable'")->fetchColumn();
$statBorrowEq  = $pdo->query("SELECT COUNT(*) FROM `sport_equipment` WHERE status = 'borrowed'")->fetchColumn();
$statOverdue   = $pdo->query("SELECT COUNT(*) FROM `transactions` WHERE trans_status = 'overdue'")->fetchColumn();

$activeStmt = $pdo->query("
    SELECT t.*, u.full_name, u.student_id, se.eq_name, se.eq_code, sc.category_name
    FROM `transactions` t
    JOIN `user` u ON t.user_id = u.user_id
    JOIN `sport_equipment` se ON t.eq_id = se.eq_id
    LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
    WHERE t.trans_status IN ('borrowed', 'overdue')
    ORDER BY t.due_time ASC
");
$activeList = $activeStmt->fetchAll();

$stockStmt = $pdo->query("
    SELECT se.eq_name, sc.category_name,
           COUNT(se.eq_id) AS total_qty,
           SUM(CASE WHEN se.status = 'avaliable' THEN 1 ELSE 0 END) AS avail_qty,
           SUM(CASE WHEN se.status = 'borrowed' THEN 1 ELSE 0 END) AS borrow_qty
    FROM `sport_equipment` se
    LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
    GROUP BY se.eq_name, sc.category_name
    ORDER BY se.eq_name ASC
");
$stockList = $stockStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - ระบบยืมคืนอุปกรณ์กีฬา</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
  <header class="bg-[#0022BA] text-white shadow-md">
    <div class="max-w-7xl mx-auto px-4 py-3.5 flex justify-between items-center">
      <div class="flex items-center space-x-3">
        <span class="text-xl font-bold">PSU Sports Equipment</span>
        <span class="bg-blue-800 text-xs px-2 py-0.5 rounded font-semibold text-blue-200">Admin Dashboard</span>
      </div>
      <nav class="flex space-x-4 text-sm font-medium">
        <a href="index.php" class="text-white border-b-2 border-white pb-0.5">ภาพรวมระบบ</a>
        <a href="users.php" class="text-blue-200 hover:text-white transition">จัดการผู้ใช้งาน</a>
        <a href="history.php" class="text-blue-200 hover:text-white transition">ประวัติการยืม-คืน</a>
      </nav>
    </div>
  </header>

  <main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
        <div class="text-xs text-gray-500 font-semibold">อุปกรณ์ทั้งหมดในระบบ</div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($statTotalEq) ?> ชิ้น</div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
        <div class="text-xs text-green-600 font-semibold">พร้อมใช้งาน (Available)</div>
        <div class="text-2xl font-bold text-green-600 mt-1"><?= number_format($statAvailEq) ?> ชิ้น</div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
        <div class="text-xs text-blue-600 font-semibold">กำลังถูกยืม (Borrowed)</div>
        <div class="text-2xl font-bold text-blue-700 mt-1"><?= number_format($statBorrowEq) ?> ชิ้น</div>
      </div>
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
        <div class="text-xs text-red-600 font-semibold">เกินกำหนดส่ง (Overdue)</div>
        <div class="text-2xl font-bold text-red-600 mt-1"><?= number_format($statOverdue) ?> รายการ</div>
      </div>
    </div>

    <!-- รายการยืมค้างอยู่ -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="p-4 border-b border-gray-200 bg-gray-50">
        <h3 class="font-bold text-sm text-gray-800">รายการที่อยู่ระหว่างการยืม (<?= count($activeList) ?> รายการ)</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-700">
          <thead class="bg-gray-100 text-gray-600 uppercase font-semibold">
            <tr>
              <th class="p-3.5">ID</th>
              <th class="p-3.5">ผู้ยืม / รหัส</th>
              <th class="p-3.5">อุปกรณ์ / รหัสชิ้น</th>
              <th class="p-3.5">วันที่ยืม</th>
              <th class="p-3.5">กำหนดส่งคืน</th>
              <th class="p-3.5 text-center">สถานะ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php foreach ($activeList as $row): $isLate = ($row['trans_status'] === 'overdue'); ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="p-3.5 font-bold">#<?= $row['trans_id'] ?></td>
              <td class="p-3.5"><b><?= htmlspecialchars($row['full_name']) ?></b> (<?= htmlspecialchars($row['student_id'] ?? '-') ?>)</td>
              <td class="p-3.5"><?= htmlspecialchars($row['eq_name']) ?> (<?= htmlspecialchars($row['eq_code']) ?>)</td>
              <td class="p-3.5"><?= date('d/m/Y H:i', strtotime($row['borrow_time'])) ?></td>
              <td class="p-3.5 font-semibold <?= $isLate ? 'text-red-600' : '' ?>"><?= date('d/m/Y H:i', strtotime($row['due_time'])) ?></td>
              <td class="p-3.5 text-center">
                <span class="px-2.5 py-1 rounded text-[10px] font-bold <?= $isLate ? 'bg-red-600 text-white' : 'bg-blue-600 text-white' ?>">
                  <?= $isLate ? 'เกินกำหนด' : 'กำลังใช้งาน' ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>