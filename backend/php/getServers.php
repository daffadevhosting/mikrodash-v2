<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use PEAR2\Net\RouterOS;

header("Content-Type: application/json");

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$firebase_url = rtrim($_ENV['FIREBASE_DB_URL'], '/') . '/mikrotik_config.json';

// Ambil config Mikrotik dari Firebase
$ch = curl_init($firebase_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$config = json_decode($response, true);

if (!$config || empty($config['ip']) || empty($config['username']) || empty($config['password'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Konfigurasi Mikrotik tidak lengkap']);
    exit;
}

try {
    $client = new RouterOS\Client($config['ip'], $config['username'], $config['password']);
    $request = new RouterOS\Request('/ip/hotspot/print');
    $responses = $client->sendSync($request);

    $servers = [];
    foreach ($responses as $response) {
        if ($response->getType() === RouterOS\Response::TYPE_DATA) {
            $servers[] = $response->getProperty('name');
        }
    }

    echo json_encode(['servers' => $servers]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal terhubung ke Mikrotik: ' . $e->getMessage()]);
}
