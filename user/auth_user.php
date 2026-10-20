<?php
session_start();
require_once '../config/db.php';

// ส่ง user pass ไปทาง ม.อ. ผ่าน soap ด้วย api และ ส่งข้อมูลนศ.กลับมา || error
function psupassport_authentication($username, $password)
{
    $params = array('username' => "$username", 'password' => "$password");
    $url = "https://passport.psu.ac.th/authentication/authentication.asmx?WSDL";
    try {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $client = @new SoapClient($url, array("trace" => 1, "exceptions" => 1, "connection_timeout" => 5, "stream_context" => $context));
        $result = $client->GetUserDetails($params);
        if (isset($result->GetUserDetailsResult->string)) {
            return ["status" => 1, "result" => (array)$result->GetUserDetailsResult->string];
        }
        return ["status" => 0, "result" => "ไม่สามารถดึงข้อมูลได้"];
    } catch (Exception $e) {
        return ["status" => -1, "result" => $e->getMessage()];
    }
}

// รับค่า จาก ฟอร์ม login
if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $lineUserId = trim($_POST['line_user_id'] ?? '');
    $lineUserId = ($lineUserId !== '') ? $lineUserId : null;

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "กรุณากรอก Username และ Password";
        header("Location: login_user.php");
        exit;
    }

    $authen_result = psupassport_authentication($username, $password);

    // นำข้อมูล นศ. ที่เป็น array มาใส่ตัวแปร
    if ($authen_result["status"] === 1 && !empty($authen_result["result"][0])) {
        $data = $authen_result["result"];

        $student_id = trim($data[0] ?? $username);
        $full_name = trim(($data[12] ?? '') . ' ' . ($data[1] ?? '') . ' ' . ($data[2] ?? '')); // คำนำหน้า ชื่อ สกุล
        $email = $data[13] ?? '';
        $gender = $data[4] ?? '';
        $national_id = $data[5] ?? '';
        $faculty = $data[8] ?? '';
        $campus = $data[10] ?? '';

        // ดึงปีการศึกษา 25xx
        $enrollment_year = null;
        if (strlen($student_id) >= 2) {
            $current_be_year = date('Y') + 543;
            $century_prefix = substr($current_be_year, 0, 2);
            $enrollment_year = $century_prefix . substr($student_id, 0, 2);
        }

        try {
            $pdo->beginTransaction();

            // 1. ค้นหาว่ามี student_id นี้ในระบบแล้วหรือไม่
            $stmtCheckStudent = $pdo->prepare("SELECT user_id, full_name, role, status FROM user WHERE student_id = ? LIMIT 1");
            $stmtCheckStudent->execute([$student_id]);
            $userByStudent = $stmtCheckStudent->fetch(PDO::FETCH_ASSOC);

            // 2. ค้นหาว่ามีแถวที่สร้างจาก LINE OA (line_user_id) รอไว้แล้วหรือไม่
            $userByLine = false;
            if ($lineUserId !== null) {
                $stmtCheckLine = $pdo->prepare("SELECT user_id, student_id, full_name, role, status FROM user WHERE line_user_id = ? LIMIT 1");
                $stmtCheckLine->execute([$lineUserId]);
                $userByLine = $stmtCheckLine->fetch(PDO::FETCH_ASSOC);
            }

            // 3. ตรวจสอบเงื่อนไขการบล็อก (Blocker)
            $targetStatus = $userByStudent['status'] ?? ($userByLine['status'] ?? 'active');
            if (in_array($targetStatus, ['inactive', 'graduated'])) {
                $pdo->rollBack();
                $_SESSION['login_error'] = "บัญชีของคุณถูกระงับการใช้งาน หรือจบการศึกษาแล้ว ไม่สามารถเข้าสู่ระบบได้";
                header("Location: login_user.php");
                exit;
            }

            // 4. จัดการบันทึก/อัปเดตข้อมูลให้ไม่ชน Unique Key
            if ($userByStudent) {
                // กรณีที่ 1: มีรหัสนักศึกษานี้อยู่ในระบบแล้ว
                // หากมีแถวว่างที่สร้างจาก Webhook ของ LINE ค้างอยู่คนละ user_id ให้ลบหรือเคลียร์ออกก่อนเพื่อไม่ให้ซ้ำ
                if ($userByLine && (int)$userByLine['user_id'] !== (int)$userByStudent['user_id']) {
                    if (empty($userByLine['student_id'])) {
                        $pdo->prepare("DELETE FROM user WHERE user_id = ?")->execute([$userByLine['user_id']]);
                    } else {
                        $pdo->prepare("UPDATE user SET line_user_id = NULL WHERE user_id = ?")->execute([$userByLine['user_id']]);
                    }
                }

                // อัปเดตข้อมูลและผูก line_user_id ลงในแถวของนักศึกษา
                $stmtUpdate = $pdo->prepare("
                    UPDATE user SET 
                        line_user_id = COALESCE(?, line_user_id),
                        full_name = ?, email = ?, gender = ?, 
                        national_id = ?, faculty = ?, campus = ?, enrollment_year = ?
                    WHERE user_id = ?
                ");
                $stmtUpdate->execute([
                    $lineUserId, $full_name, $email, $gender, 
                    $national_id, $faculty, $campus, $enrollment_year, 
                    $userByStudent['user_id']
                ]);

            } elseif ($userByLine) {
                // กรณีที่ 2: แอดเพื่อน LINE OA มาแล้ว (มี line_user_id ในตาราง แต่ student_id ยังเป็น NULL)
                // ให้อัปเดต student_id และข้อมูล PSU Passport เข้าไปในแถวนั้นเลย
                $stmtUpdateLine = $pdo->prepare("
                    UPDATE user SET 
                        student_id = ?, full_name = ?, email = ?, gender = ?, 
                        national_id = ?, faculty = ?, campus = ?, enrollment_year = ?
                    WHERE user_id = ?
                ");
                $stmtUpdateLine->execute([
                    $student_id, $full_name, $email, $gender, 
                    $national_id, $faculty, $campus, $enrollment_year, 
                    $userByLine['user_id']
                ]);

            } else {
                // กรณีที่ 3: ผู้ใช้ใหม่ทั้งหมด (ยังไม่มีทั้ง student_id และ line_user_id)
                $stmtInsert = $pdo->prepare("
                    INSERT INTO user (line_user_id, student_id, full_name, email, gender, national_id, faculty, campus, enrollment_year, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 'active')
                ");
                $stmtInsert->execute([
                    $lineUserId, $student_id, $full_name, $email, 
                    $gender, $national_id, $faculty, $campus, $enrollment_year
                ]);
            }

            $pdo->commit();

            // 5. ดึงข้อมูลล่าสุดเพื่อสร้าง Session
            $stmtGet = $pdo->prepare("SELECT user_id, full_name, role FROM user WHERE student_id = ? LIMIT 1");
            $stmtGet->execute([$student_id]);
            $userRow = $stmtGet->fetch(PDO::FETCH_ASSOC);

            $_SESSION['user_id'] = $userRow['user_id'];
            $_SESSION['student_id'] = $student_id;
            $_SESSION['full_name'] = $userRow['full_name'];
            $_SESSION['role'] = $userRow['role'];

            header("Location: liff_app.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['login_error'] = "Database Error: " . $e->getMessage();
            header("Location: login_user.php");
            exit;
        }

    } else {
        $_SESSION['login_error'] = "รหัสนักศึกษา หรือ รหัสผ่าน PSU Passport ไม่ถูกต้อง";
        header("Location: login_user.php");
        exit;
    }
} else {
    header("Location: login_user.php");
    exit;
}
?>