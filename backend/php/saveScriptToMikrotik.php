<?php
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

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$data = json_decode(file_get_contents('php://input'), true);

// Validasi
if (
    !$data || 
    !isset($data['profileName']) || 
    (!isset($data['on_login_script']) && !isset($data['scheduler_script']))
) {
    echo json_encode(['success' => false, 'reason' => 'Data tidak lengkap']);
    exit;
}

// Verifikasi token Firebase
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(['success' => false, 'reason' => 'Token tidak ditemukan']);
    exit;
}
$uid = verifyFirebaseToken($matches[1]);
if (!$uid) {
    echo json_encode(['success' => false, 'reason' => 'Token tidak valid']);
    exit;
}

// Ambil data koneksi Mikrotik
global $database;
$mikrotikRef = $database->getReference("mikrotik_config/$uid");
$mikrotikData = $mikrotikRef->getValue();

if (!$mikrotikData) {
    echo json_encode(['success' => false, 'reason' => 'Konfigurasi Mikrotik tidak ditemukan']);
    exit;
}

try {
    $client = new RouterOS\Client($mikrotikData['ip'], $mikrotikData['username'], $mikrotikData['password']);

    // Update profile
    $update = new RouterOS\Request('/ip/hotspot/user/profile/set');
    $update->setArgument('.id', "*". $data['profileName']); // Gunakan .id jika tahu ID, atau pakai find by name
    $update->setArgument('name', $data['profileName']);

    if (!empty($data['on_login_script'])) {
        $update->setArgument('on-login', $data['on_login_script']);
    }

    $client->sendSync($update);

    // Buat scheduler jika ada
    if (!empty($data['scheduler_script'])) {
        $scriptReq = new RouterOS\Request('/system/script/add');
        $scriptReq->setArgument('name', 'auto_' . $data['profileName']);
        $scriptReq->setArgument('source', $data['scheduler_script']);
        $client->sendSync($scriptReq);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'reason' => $e->getMessage()]);
}
