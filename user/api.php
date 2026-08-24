<?php
// api.php - REST API สำหรับดึงข้อมูลอุปกรณ์และหมวดหมู่
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
require_once __DIR__ . '../../config/db.php';

$action = $_GET['action'] ?? '';

if ($action === 'get_categories') {
    try {
        $stmt = $pdo->query("SELECT * FROM `sport_categories` ORDER BY category_id ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_equipment') {
    try {
        $stmt = $pdo->query("
            SELECT se.*, sc.category_name 
            FROM `sport_equipment` se
            LEFT JOIN `sport_categories` sc ON se.category_id = sc.category_id
            ORDER BY se.eq_name ASC, se.eq_code ASC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid Action']);