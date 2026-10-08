<?php
session_start();
require_once '../config/db.php';

function psupassport_authentication($username, $password)
{
    $params = array('username' => "$username", 'password' => "$password");
    $url = "https://passport.psu.ac.th/authentication/authentication.asmx?WSDL";
    
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ],
            'http' => [
                'timeout' => 5 // กำหนด timeout 5 วินาทีหากเชื่อมต่อไม่ได้
            ]
        ]);

        $client = @new SoapClient($url, array(
            "trace" => 1, 
            "exceptions" => 1, 
            "cache_wsdl" => 0,
            "connection_timeout" => 5,
            "stream_context" => $context
        ));

        $result = $client->GetUserDetails($params);

        if (isset($result->GetUserDetailsResult->string)) {
            return [
                "status" => 1,
                "result" => (array)$result->GetUserDetailsResult->string,
            ];
        } else {
            return [
                "status" => 0,
                "result" => "ไม่สามารถดึงข้อมูลผลลัพธ์จากเซิร์ฟเวอร์ได้",
            ];
        }
    } catch (Exception $e) {
        return [
            "status" => -1, // สถานะเชื่อมต่อ Server มหาลัยไม่ได้ (ไม่ได้ต่อ VPN / No Internet)
            "result" => $e->getMessage(),
        ];
    }
}

if (isset($_POST['username']) && isset($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "กรุณากรอก Username และ Password";
        header("Location: login_ldap.php");
        exit;
    }

    $authen_result = psupassport_authentication($username, $password);

    // กรณีที่ 1: เชื่อมต่อและยืนยันตัวตนกับ PSU Passport สำเร็จ
    if ($authen_result["status"] === 1 && !empty($authen_result["result"][0])) {
        $ldapData = $authen_result["result"];

        $ldapUsername = $ldapData[0] ?? '';
        $ldapFullName = ($ldapData[1] ?? '') . ' ' . ($ldapData[2] ?? '');
        $ldapStaffID  = $ldapData[3] ?? '';
        $ldapEmail    = $ldapData[13] ?? '';

        $stmt = $pdo->prepare("
            SELECT * FROM user 
            WHERE (student_id = ? OR student_id = ? OR email = ? OR email = ?) 
              AND role = 'admin' 
              AND status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([
            $ldapStaffID, 
            $ldapUsername, 
            $ldapEmail, 
            $ldapUsername . '@psu.ac.th'
        ]);
        $adminUser = $stmt->fetch();

        if ($adminUser) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $adminUser['user_id'];
            $_SESSION['admin_name'] = !empty($adminUser['full_name']) ? $adminUser['full_name'] : $ldapFullName;
            $_SESSION['admin_email'] = $adminUser['email'];
            $_SESSION['admin_student_id'] = $adminUser['student_id'];

            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['login_error'] = "ยืนยันตัวตนสำเร็จ แต่บัญชีนี้ไม่มีสิทธิ์เข้าใช้งานระบบแอดมิน (Role ไม่ใช่ admin)";
            header("Location: login_ldap.php");
            exit;
        }
    } 
    // กรณีที่ 2: ไม่สามารถเชื่อมต่อไปยังเครือข่ายของมหาวิทยาลัยได้ (ไม่ได้เปิด VPN)
    // elseif ($authen_result["status"] === -1) {
    //     // [Localhost Development Bypass] ตรวจสอบตรงกับฐานข้อมูลในเครื่องเพื่ออำนวยความสะดวกในการพัฒนา
    //     $stmt = $pdo->prepare("
    //         SELECT * FROM user 
    //         WHERE (student_id = ? OR email = ?) 
    //           AND role = 'admin' 
    //           AND status = 'active' 
    //         LIMIT 1
    //     ");
    //     $stmt->execute([$username, $username]);
    //     $adminUser = $stmt->fetch();

    //     if ($adminUser) {
    //         $_SESSION['admin_logged_in'] = true;
    //         $_SESSION['admin_id'] = $adminUser['user_id'];
    //         $_SESSION['admin_name'] = $adminUser['full_name'];
    //         $_SESSION['admin_email'] = $adminUser['email'];
    //         $_SESSION['admin_student_id'] = $adminUser['student_id'];

    //         header("Location: dashboard.php");
    //         exit;
    //     } else {
    //         $_SESSION['login_error'] = "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ PSU Passport ได้ (กรุณาต่อ PSU VPN) หรือไม่พบข้อมูลแอดมินในระบบ";
    //         header("Location: login_ldap.php");
    //         exit;
    //     }
    // } 
    // กรณีที่ 3: Username หรือ Password ของ PSU Passport ไม่ถูกต้อง
    else {
        $_SESSION['login_error'] = "เข้าสู่ระบบล้มเหลว: Username หรือ Password ของ PSU Passport ไม่ถูกต้อง";
        header("Location: login_ldap.php");
        exit;
    }
} else {
    header("Location: login_ldap.php");
    exit;
}