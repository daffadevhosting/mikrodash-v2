<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/firebase_init.php';


$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'error' => 'Token tidak ditemukan di header Authorization']);
    exit;
}

$token = $matches[1];
$uid = verifyFirebaseToken($token);

if (!$uid) {
    echo json_encode(['success' => false, 'error' => 'Token tidak valid']);
    exit;
}

try {
    $ref = $database->getReference('mikrotik_config/' . $uid);
    $routerData = $ref->getValue();

    if ($routerData) {
        echo json_encode(['success' => true, 'router' => $routerData]);
    } else {
        echo json_encode(['success' => false, 'router' => null]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
