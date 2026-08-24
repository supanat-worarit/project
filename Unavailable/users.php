<?php
// users.php - ระบบจัดการข้อมูลผู้ใช้งานสำหรับแอดมิน
require_once __DIR__ . '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $targetUserId = (int)$_POST['user_id'];

    if ($action === 'update_user') {
        $stmt = $pdo->prepare("UPDATE `user` SET full_name = ?, student_id = ?, role = ?, status = ? WHERE user_id = ?");
        $stmt->execute([trim($_POST['full_name']), trim($_POST['student_id']), $_POST['role'], $_POST['status'], $targetUserId]);
        echo "<script>alert('อัปเดตข้อมูลสำเร็จ!'); window.location.href='users.php';</script>";
        exit;
    }
    if ($action === 'delete_user') {
        $pdo->prepare("DELETE FROM `user` WHERE user_id = ?")->execute([$targetUserId]);
        echo "<script>alert('ลบผู้ใช้สำเร็จ!'); window.location.href='users.php';</script>";
        exit;
    }
}

$users = $pdo->query("
    SELECT u.*, COUNT(CASE WHEN t.trans_status = 'borrowed' THEN 1 END) AS active_borrow_count
    FROM `user` u
    LEFT JOIN `transactions` t ON u.user_id = t.user_id
    GROUP BY u.user_id ORDER BY u.user_id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>จัดการผู้ใช้งาน - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen font-sans">
  <header class="bg-[#0022BA] text-white shadow p-4 flex justify-between items-center max-w-7xl mx-auto">
    <span class="font-bold text-lg">จัดการผู้ใช้งาน</span>
    <a href="index.php" class="text-xs bg-blue-800 px-3 py-1.5 rounded">❮ กลับ Dashboard</a>
  </header>

  <main class="max-w-7xl mx-auto p-4">
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-left text-xs text-gray-700">
        <thead class="bg-gray-50 border-b text-gray-600 uppercase font-semibold">
          <tr>
            <th class="p-3">ID</th>
            <th class="p-3">ชื่อ - สกุล / รหัส</th>
            <th class="p-3">อีเมล</th>
            <th class="p-3 text-center">สิทธิ์</th>
            <th class="p-3 text-center">สถานะ</th>
            <th class="p-3 text-center">ยืมค้าง</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="p-3 font-bold"><?= $u['user_id'] ?></td>
            <td class="p-3"><b><?= htmlspecialchars($u['full_name']) ?></b> (<?= htmlspecialchars($u['student_id'] ?? '-') ?>)</td>
            <td class="p-3"><?= htmlspecialchars($u['email'] ?? '-') ?></td>
            <td class="p-3 text-center uppercase font-bold"><?= $u['role'] ?></td>
            <td class="p-3 text-center font-bold text-green-600"><?= $u['status'] ?></td>
            <td class="p-3 text-center"><?= $u['active_borrow_count'] ?> ชิ้น</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</body>
</html>