<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/firebase_init.php';

use PEAR2\Net\RouterOS;
use Dotenv\Dotenv;

// Load ENV
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Ambil body JSON
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['command'])) {
    echo json_encode(['success' => false, 'reason' => 'Perintah tidak ditemukan']);
    exit;
}

// Verifikasi token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'reason' => 'Token tidak ditemukan']);
    exit;
}
$idToken = $matches[1];

$uid = verifyFirebaseToken($matches[1]);
if (!$uid) {
    echo json_encode(['success' => false, 'reason' => 'Token tidak valid']);
    exit;
}

// Validasi method dan input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "Hanya menerima metode POST"]);
    exit;
}

$cmd = $_POST['command'] ?? '';
if (!$cmd) {
    echo json_encode(["success" => false, "error" => "Perintah kosong"]);
    exit;
}


// Ambil data mikrotik user
global $database;
$mikrotikRef = $database->getReference("mikrotik_config/$uid");
$mikrotikData = $mikrotikRef->getValue();
if (!$mikrotikData) {
    echo json_encode(['success' => false, 'reason' => 'Konfigurasi Mikrotik tidak ditemukan']);
    exit;
}

try {
    $client = new RouterOS\Client($mikrotikData['ip'], $mikrotikData['username'], $mikrotikData['password'], $port = 8728);

$request = new RouterOS\Request(trim($data['command']));
$responses = $client->sendSync($request);

    $output = "";
    foreach ($responses as $response) {
        if ($response->getType() === RouterOS\Response::TYPE_DATA) {
            foreach ($response->getArguments() as $key => $value) {
                $output .= "$key: $value\n";
            }
            $output .= "----------------------\n";
        }
    }

    if (!$output) {
        $output = "(Perintah berhasil dijalankan, tidak ada output)";
    }

    echo json_encode(['success' => true, 'output' => $output]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'reason' => $e->getMessage()]);
}
