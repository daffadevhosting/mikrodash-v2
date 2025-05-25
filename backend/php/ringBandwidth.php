<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/firebase_init.php';

use Dotenv\Dotenv;
use PEAR2\Net\RouterOS;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();


$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(["success" => false, "error" => "Token tidak ditemukan di header Authorization"]);
    exit;
}
$idToken = $matches[1];

// Verifikasi token dan ambil UID user
$uid = verifyFirebaseToken($idToken);
if (!$uid) {
    echo json_encode(["success" => false, "error" => "Token tidak valid atau expired"]);
    exit;
}

try {
    $client = new RouterOS\Client($config['ip'], $config['username'], $config['password'], port: 1209);

    $request = new RouterOS\Request('/interface/monitor-traffic');
    $request->setArgument('interface', 'ether4-ISP');
    $request->setArgument('once', '');

    $responses = $client->sendSync($request);

    $result = [];
    foreach ($responses as $res) {
        if ($res->getType() === RouterOS\Response::TYPE_DATA) {
            $rx = (float)$res->getProperty('rx-bits-per-second') / 1000000; // Mbps
            $tx = (float)$res->getProperty('tx-bits-per-second') / 1000000; // Mbps
            $result = [
                "success" => true,
                "download" => round($rx, 2),
                "upload" => round($tx, 2)
            ];
        }
    }

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
