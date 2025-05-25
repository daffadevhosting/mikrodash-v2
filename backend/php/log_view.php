<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/firebase_init.php';

use PEAR2\Net\RouterOS;

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(["success" => false, "message" => "Token tidak ditemukan"]);
    exit;
}
$idToken = $matches[1];

$uid = verifyFirebaseToken($idToken);
if (!$uid) {
    echo json_encode(["success" => false, "message" => "Token tidak valid"]);
    exit;
}

try {
    global $database;
    $mikrotikData = $database->getReference("mikrotik_config/$uid")->getValue();

    if (!$mikrotikData) {
        throw new Exception("Data Mikrotik tidak ditemukan");
    }

    $client = new RouterOS\Client(
        $mikrotikData['ip'],
        $mikrotikData['username'],
        $mikrotikData['password'],
        3
    );

    // Ambil log dari Mikrotik
    $logs = $client->sendSync(new RouterOS\Request('/log/print'));

    $logData = [];
    foreach ($logs as $log) {
        if ($log->getType() === RouterOS\Response::TYPE_DATA) {
            $logData[] = [
                'time' => $log->getProperty('time'),
                'topics' => $log->getProperty('topics'),
                'message' => $log->getProperty('message')
            ];
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $logData
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
