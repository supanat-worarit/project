<?php
// user/auth_user.php
session_start();
require_once '../config/db.php';

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

if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $lineUserId = trim($_POST['line_user_id'] ?? '');

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "กรุณากรอก Username และ Password";
        header("Location: login_user.php");
        exit;
    }

    $authen_result = psupassport_authentication($username, $password);

    if ($authen_result["status"] === 1 && !empty($authen_result["result"][0])) {
        $data = $authen_result["result"];

        $student_id = $data[0] ?? '';
        $full_name = trim(($data[12] ?? '') . ' ' . ($data[1] ?? '') . ' ' . ($data[2] ?? '')); // คำนำหน้า ชื่อ สกุล
        $email = $data[13] ?? '';
        $gender = $data[4] ?? '';
        $national_id = $data[5] ?? '';
        $faculty = $data[8] ?? '';
        $campus = $data[10] ?? '';

        // ดึงปีการศึกษา 25xx
        $enrollment_year = null;
        if(strlen($student_id) >= 2) {
            $enrollment_year = "25" . substr($student_id, 0, 2);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO user (line_user_id, student_id, full_name, email, gender, national_id, faculty, campus, enrollment_year, role, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 'active')
                ON DUPLICATE KEY UPDATE 
                line_user_id = IF(VALUES(line_user_id) != '', VALUES(line_user_id), line_user_id),
                full_name = VALUES(full_name), email = VALUES(email), gender = VALUES(gender),
                national_id = VALUES(national_id), faculty = VALUES(faculty), campus = VALUES(campus),
                enrollment_year = VALUES(enrollment_year)
            ");
            $stmt->execute([$lineUserId, $student_id, $full_name, $email, $gender, $national_id, $faculty, $campus, $enrollment_year]);

            // ดึง ID ของ User
            $stmtGet = $pdo->prepare("SELECT user_id, full_name, role FROM user WHERE student_id = ? LIMIT 1");
            $stmtGet->execute([$student_id]);
            $userRow = $stmtGet->fetch();

            $_SESSION['user_id'] = $userRow['user_id'];
            $_SESSION['student_id'] = $student_id;
            $_SESSION['full_name'] = $userRow['full_name'];
            $_SESSION['role'] = $userRow['role'];

            header("Location: liff_app.php");
            exit;

        } catch (Exception $e) {
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