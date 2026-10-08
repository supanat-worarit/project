<?php
// webhook.php
require_once 'config/db.php';
require_once 'config/settings.php'; // โหลด API Keys

$content = file_get_contents('php://input');
$events = json_decode($content, true);

if (!is_null($events['events'])) {
    foreach ($events['events'] as $event) {
        $line_user_id = $event['source']['userId'];
        $replyToken = $event['replyToken'];

        // Req 1: User แอด Line OA
        if ($event['type'] == 'follow') {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user (line_user_id, status) VALUES (?, 'active')");
            $stmt->execute([$line_user_id]);
        }

        // Req 5: User ส่งข้อความประเภทรูปภาพ (ตรวจสลิปโอนเงิน)
        if ($event['type'] == 'message' && $event['message']['type'] == 'image') {
            $messageId = $event['message']['id'];
            
            // 1. โหลดรูปจาก LINE API
            $ch = curl_init("https://api-data.line.me/v2/bot/message/{$messageId}/content");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . LINE_ACCESS_TOKEN]);
            $imageBinary = curl_exec($ch);
            curl_close($ch);
            
            $tempPath = __DIR__ . "/user/uploads/slip_{$messageId}.jpg";
            file_put_contents($tempPath, $imageBinary);

            // 2. ส่งตรวจ SlipOK
            $cFile = curl_file_create($tempPath);
            $ch2 = curl_init("https://api.slipok.com/api/line/apikey/" . SLIPOK_BRANCH_ID);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, ['files' => $cFile]);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ["x-authorization: " . SLIPOK_API_KEY]);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            $slipokResponse = json_decode(curl_exec($ch2), true);
            curl_close($ch2);

            // 3. ตรวจผลและอัปเดตฐานข้อมูล
            if (isset($slipokResponse['success']) && $slipokResponse['success'] == true) {

                // ค้นหารายการค่าปรับที่ยังไม่จ่าย เพิ่ม t.trans_id เข้ามาใน SELECT ด้วย
                $stmtF = $pdo->prepare("
                    SELECT f.fine_id, f.price, t.trans_id 
                    FROM equipment_fines f 
                    JOIN transactions t ON f.trans_id = t.trans_id 
                    JOIN user u ON t.user_id = u.user_id 
                    WHERE u.line_user_id = ? AND f.payment_status = 'unpaid' 
                    LIMIT 1
                ");
                $stmtF->execute([$line_user_id]);
                $fine = $stmtF->fetch();

                if ($fine) {
                    $transferAmount = $slipokResponse['data']['amount'];
                    
                    if ($transferAmount >= $fine['price']) {
                        // 1. อัปเดตสถานะบิลค่าปรับเป็นจ่ายแล้ว
                        $pdo->prepare("UPDATE equipment_fines SET payment_status = 'paid', slipok_status = 'verified', paid_time = NOW() WHERE fine_id = ?")->execute([$fine['fine_id']]);
                        
                        // 2. อัปเดตสถานะ Transaction จาก overdue เป็น returned
                        $pdo->prepare("UPDATE transactions SET trans_status = 'returned' WHERE trans_id = ?")->execute([$fine['trans_id']]);
                        
                        $replyText = "ตรวจสอบสำเร็จ! ระบบได้รับชำระค่าปรับจำนวน {$transferAmount} บาท ของคุณเรียบร้อยแล้ว \nขอบคุณที่ใช้บริการ";
                    } else {
                        // ยอดเงินไม่ตรง
                        $replyText = "สลิปถูกต้อง แต่ยอดเงินไม่ตรงกับค่าปรับ (ยอดโอน {$transferAmount} บาท / ค่าปรับ {$fine['price']} บาท) กรุณาติดต่อแอดมิน ⚠";
                    }
                } else {
                    $replyText = "สลิปตรวจสอบผ่าน แต่ไม่พบรายการค้างชำระในระบบของคุณครับ 🤷‍♂️";
                }
            } else {
                // สลิปปลอม, สลิปซ้ำ, หรือโอนไปบัญชีอื่น
                $reason = $slipokResponse['message'] ?? 'ไม่ทราบสาเหตุ';
                $replyText = "สลิปของท่านไม่ถูกต้อง! ❌\n(เหตุผล: {$reason})\nกรุณาตรวจสอบและส่งใหม่อีกครั้งครับ";
            }
            unlink($tempPath); // ลบไฟล์รูป Temp ทิ้งเพื่อประหยัดพื้นที่

            // 4. ตอบกลับแชท LINE
            $post_data = json_encode(['replyToken' => $replyToken, 'messages' => [['type' => 'text', 'text' => $replyText]]]);
            $ch3 = curl_init('https://api.line.me/v2/bot/message/reply');
            curl_setopt($ch3, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch3, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch3, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . LINE_ACCESS_TOKEN]);
            curl_exec($ch3);
            curl_close($ch3);
        }
    }
}
echo "OK";
?>