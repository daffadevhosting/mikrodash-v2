<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/firebase_init.php';

use Dotenv\Dotenv;
use PEAR2\Net\RouterOS;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'reason' => 'Token tidak ditemukan']);
    exit;
}
$idToken = $matches[1];

$uid = verifyFirebaseToken($idToken);
if (!$uid) {
    echo json_encode(["success" => false, "reason" => "Token tidak valid"]);
    exit;
}

try {
    global $database;
    $mikrotikData = $database->getReference("mikrotik_config/$uid")->getValue();

    if (!$mikrotikData) {
        echo json_encode(['success' => false, 'reason' => 'Konfigurasi Mikrotik tidak ditemukan']);
        exit;
    }

    $client = new RouterOS\Client($mikrotikData['ip'], $mikrotikData['username'], $mikrotikData['password']);

    $request = new RouterOS\Request('/ip/hotspot/user/profile/print');
    $responses = $client->sendSync($request);

    $profiles = [];
    foreach ($responses as $entry) {
        $name = $entry->getArgument('name');
        if ($name) {
            $profiles[] = $name;
        }
    }

    echo json_encode(['success' => true, 'profiles' => $profiles]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'reason' => $e->getMessage()]);
}
