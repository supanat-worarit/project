<?php
// index1.php - หน้ารายการอุปกรณ์แบบแคตตาล็อก
session_start();
require_once __DIR__ . '../../config/db.php';

$categories = $pdo->query("SELECT * FROM `sport_categories` ORDER BY category_id ASC")->fetchAll();
$stmt = $pdo->query("
    SELECT se.*, sc.category_name 
    FROM `sport_equipment` se
    LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
    ORDER BY se.eq_name ASC, se.eq_code ASC
");
$rows = $stmt->fetchAll();

$grouped = [];
foreach ($rows as $r) {
    $name = $r['eq_name'];
    if (!isset($grouped[$name])) {
        $grouped[$name] = [
            'category_id'   => $r['category_id'],
            'category_name' => $r['category_name'] ?? 'ทั่วไป',
            'eq_name'       => $name,
            'total_count'   => 0,
            'available'     => 0,
            'items'         => []
        ];
    }
    $grouped[$name]['total_count']++;
    if ($r['status'] === 'avaliable') $grouped[$name]['available']++;
    $grouped[$name]['items'][] = $r;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>รายการอุปกรณ์กีฬา</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">
  <div class="w-full max-w-md bg-white min-h-screen flex flex-col shadow-lg">
    <div class="bg-[#0022BA] text-white p-4 shadow flex items-center justify-between">
      <h1 class="font-bold text-base">🏆 รายการอุปกรณ์กีฬา</h1>
      <a href="liff_app.php" class="bg-blue-700 hover:bg-blue-800 text-xs px-3 py-1 rounded font-bold transition">เปิดแอป LIFF ↗</a>
    </div>

    <div class="p-3 space-y-3 flex-1 overflow-y-auto">
      <?php foreach ($grouped as $item): ?>
      <div class="bg-white border border-gray-200 rounded-xl p-3.5 shadow-sm flex items-center justify-between">
        <div>
          <h3 class="font-bold text-sm text-gray-900"><?= htmlspecialchars($item['eq_name']) ?></h3>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($item['category_name']) ?></p>
          <div class="mt-2 flex space-x-3 text-xs">
            <span>ทั้งหมด: <b><?= $item['total_count'] ?></b></span>
            <span class="<?= $item['available'] > 0 ? 'text-green-600 font-bold' : 'text-red-500 font-bold' ?>">
              พร้อมใช้งาน: <?= $item['available'] ?>
            </span>
          </div>
        </div>
        <a href="liff-borrow.php" class="bg-[#0022BA] text-white text-xs px-4 py-2 rounded-lg font-bold shadow">ยืม</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>