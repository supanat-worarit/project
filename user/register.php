<?php
// register.php - ลงชื่อเข้าใช้ / ผูกบัญชี PSU Passport
session_start();
require_once __DIR__ . '../../config/db.php';

$errorMsg = '';

// ปรับ student_id เป็น VARCHAR อัตโนมัติ ป้องกันบั๊ก 2147483647
try {
    $pdo->exec("ALTER TABLE `user` MODIFY COLUMN `student_id` VARCHAR(50) NULL");
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = trim($_POST['psu_account'] ?? '');
    $lineUserId = trim($_POST['line_user_id'] ?? '');

    if (empty($rawInput)) {
        $errorMsg = 'กรุณากรอกบัญชี PSU Passport หรือรหัสนักศึกษา';
    } else {
        preg_match('/\d{10}/', $rawInput, $matches);
        $studentId = $matches[0] ?? preg_replace('/[^0-9]/', '', $rawInput);
        if (empty($studentId)) {
            $studentId = $rawInput;
        }

        $email = strpos($rawInput, '@') !== false ? $rawInput : "s{$studentId}@psu.ac.th";
        $fullName = "นักศึกษา รหัส " . $studentId;

        try {
            $checkStmt = $pdo->prepare("
                SELECT * FROM `user` 
                WHERE student_id = ? OR (line_user_id = ? AND line_user_id != '' AND line_user_id IS NOT NULL) 
                LIMIT 1
            ");
            $checkStmt->execute([$studentId, $lineUserId ?: 'NONE']);
            $existingUser = $checkStmt->fetch();

            if ($existingUser) {
                if (!empty($lineUserId) && empty($existingUser['line_user_id'])) {
                    $upd = $pdo->prepare("UPDATE `user` SET line_user_id = ? WHERE user_id = ?");
                    $upd->execute([$lineUserId, $existingUser['user_id']]);
                }
                $currentUser = $existingUser;
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO `user` (line_user_id, student_id, full_name, email, role, status) 
                    VALUES (?, ?, ?, ?, 'user', 'active')
                ");
                $ins->execute([$lineUserId ?: null, $studentId, $fullName, $email]);
                $newUserId = $pdo->lastInsertId();

                $currentUser = [
                    'user_id'    => $newUserId,
                    'student_id' => $studentId,
                    'full_name'  => $fullName,
                    'role'       => 'user'
                ];
            }

            $_SESSION['user_id']    = $currentUser['user_id'];
            $_SESSION['student_id'] = $currentUser['student_id'] ?? $studentId;
            $_SESSION['full_name']  = $currentUser['full_name'] ?? $fullName;
            $_SESSION['role']       = $currentUser['role'] ?? 'user';

            echo "<script>
                alert('เข้าสู่ระบบสำเร็จ: " . addslashes($_SESSION['full_name']) . "');
                window.location.href = 'liff_app.php';
            </script>";
            exit;

        } catch (Exception $e) {
            $errorMsg = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ลงชื่อเข้าใช้ - PSU Passport</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
</head>
<body class="bg-gray-100 flex justify-center min-h-screen font-sans">
  <div class="w-full max-w-sm bg-white min-h-screen flex flex-col justify-between p-6 shadow-md border-x border-gray-200">
    <div>
      <div class="mb-6 flex items-center space-x-2">
        <div class="w-8 h-10 bg-[#0022BA] text-white font-bold flex items-center justify-center rounded text-sm shadow">PSU</div>
        <div>
          <div class="text-xs font-bold tracking-wider text-[#0022BA]">PRINCE OF SONGKLA</div>
          <div class="text-[10px] text-gray-500 tracking-widest uppercase">UNIVERSITY</div>
        </div>
      </div>

      <h1 class="text-xl font-bold text-gray-900 mb-4">ลงชื่อเข้าใช้</h1>

      <?php if (!empty($errorMsg)): ?>
        <div class="p-2.5 mb-3 bg-red-100 border border-red-300 text-red-700 text-xs rounded font-semibold leading-relaxed">
          <?= htmlspecialchars($errorMsg) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="register.php" class="space-y-4">
        <input type="hidden" name="line_user_id" id="line_user_id">
        <div>
          <input 
            type="text" 
            name="psu_account" 
            id="psu_account"
            autofocus 
            required 
            placeholder="username@psu.ac.th หรือ 6410210555" 
            class="w-full bg-white border border-gray-400 focus:border-[#0022BA] focus:ring-2 focus:ring-blue-200 text-gray-900 text-sm px-3 py-2 rounded focus:outline-none shadow-sm transition"
          >
        </div>
        <div class="flex justify-end space-x-2 pt-2">
          <button type="submit" class="bg-[#0078D4] hover:bg-blue-700 text-white text-xs px-6 py-2 rounded font-semibold shadow transition">ถัดไป</button>
        </div>
      </form>

      <div class="mt-8 pt-4 border-t border-gray-200 text-[11px] text-gray-600 space-y-2 leading-relaxed">
        <p class="font-semibold text-gray-800">❗❗❗ นักศึกษา ให้เข้าใช้งานด้วย PSU Passport</p>
        <div class="bg-gray-50 p-2.5 rounded border border-gray-200 text-[10px] space-y-1 text-gray-700">
          <div><b>Student:</b> [Student ID]<b>@psu.ac.th</b> หรือใส่รหัสนักศึกษา 10 หลัก</div>
        </div>
      </div>
    </div>
    <div class="text-center text-[10px] text-gray-400 py-2 border-t border-gray-100">
      Prince of Songkla University Authentication
    </div>
  </div>

  <script>
    async function initLiff() {
      try {
        await liff.init({ liffId: "YOUR_LIFF_ID" });
        if (liff.isLoggedIn()) {
          const profile = await liff.getProfile();
          document.getElementById('line_user_id').value = profile.userId;
        }
      } catch (err) {}
    }
    initLiff();
  </script>
</body>
</html>