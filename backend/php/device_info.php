<?php
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
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

$routerRef = $database->getReference("mikrotik_config/$uid");
$routerData = $routerRef->getValue();

if (!$routerData || !isset($routerData['ip'], $routerData['username'], $routerData['password'])) {
    echo json_encode([
        "success" => false,
        "status" => "Data router MikroTik tidak ditemukan atau tidak lengkap untuk user ini.",
        "icon" => "bi-exclamation-triangle-fill",
        "data" => []
    ]);
    exit;
}

$config = [
    'ip' => $routerData['ip'],
    'username' => $routerData['username'],
    'password' => $routerData['password']
];

$statusMessage = '';
$statusClass = '';
$icon = '';
$routerInfo = [];

try {
  $client = new RouterOS\Client($config['ip'], $config['username'], $config['password']);

    $statusMessage = "Terhubung ke MikroTik!";
    $statusClass = 'success';
    $icon = 'bi-check-circle-fill';

// Dapatkan resource info (uptime, versi, board-name)
$responses = $client->sendSync(new RouterOS\Request('/system/resource/print'));
foreach ($responses as $response) {
    if ($response->getType() === RouterOS\Response::TYPE_DATA) {
        $routerInfo['version'] = $response->getProperty('version');
        $routerInfo['board_name'] = $response->getProperty('board-name');
        $routerInfo['uptime'] = $response->getProperty('uptime');
    }
}

    // Dapatkan identity
    $responses = $client->sendSync(new RouterOS\Request('/system/identity/print'));
    foreach ($responses as $response) {
        if ($response->getType() === RouterOS\Response::TYPE_DATA) {
            $routerInfo['identity'] = $response->getProperty('name');
        }
    }

    // Dapatkan uptime
    $responses = $client->sendSync(new RouterOS\Request('/system/uptime/print'));
    foreach ($responses as $response) {
        if ($response->getType() === RouterOS\Response::TYPE_DATA) {
            $routerInfo['uptime'] = $response->getProperty('uptime');
        }
    }

} catch (Exception $e) {
    $statusMessage = "Gagal terhubung: " . $e->getMessage();
    $statusClass = 'danger';
    $icon = 'bi-x-circle-fill';
}
echo json_encode([
    "success" => $statusClass === 'success',
    "status" => $statusMessage,
    "icon" => $icon,
    "data" => $routerInfo
]);
?>
