<?php
// webhook.php - จัดการ Event และ Flex Message ของ LINE Messaging API
require_once __DIR__ . '../../config/db.php';

$channelAccessToken = 'YOUR_CHANNEL_ACCESS_TOKEN';
$slipokApiKey       = 'YOUR_SLIPOK_API_KEY';
$slipokBranchId     = 'YOUR_BRANCH_ID';

$content = file_get_contents('php://input');
$events  = json_decode($content, true)['events'] ?? [];

if (empty($events)) {
    http_response_code(200);
    exit('No Events');
}

foreach ($events as $event) {
    $replyToken = $event['replyToken'] ?? '';
    $lineUserId = $event['source']['userId'] ?? '';

    if ($event['type'] === 'message' && $event['message']['type'] === 'text') {
        $txt = trim($event['message']['text']);
        if ($txt === 'ชำระค่าปรับ') {
            $msg = [
                'type' => 'text',
                'text' => "กรุณาส่งรูปสลิปโอนเงินเข้ามาในแชทนี้เพื่อยืนยันการชำระค่าปรับอุปกรณ์กีฬา"
            ];
            replyMessage($channelAccessToken, $replyToken, [$msg]);
        }
    }
}

function replyMessage($token, $replyToken, $messages) {
    $url = 'https://api.line.me/v2/bot/message/reply';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['replyToken' => $replyToken, 'messages' => $messages]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
    curl_exec($ch);
    curl_close($ch);
}