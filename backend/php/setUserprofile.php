<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/firebase_init.php';

use Dotenv\Dotenv;
use PEAR2\Net\RouterOS;
use Kreait\Firebase\Factory;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$data = json_decode(file_get_contents("php://input"), true);

// Validasi input
if (
    !$data || 
    !isset($data['name']) || 
    !isset($data['shared_users']) || 
    !isset($data['session_timeout']) || 
    !isset($data['rate_limit']) || 
    !isset($data['price'])
) {
    echo json_encode(["success" => false, "reason" => "Data tidak lengkap"]);
    exit;
}

// Ambil token dari header Authorization Bearer
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
    global $database;
    $db = $database;
    $mikrotikRef = $db->getReference("mikrotik_config/$uid");
    $mikrotikData = $mikrotikRef->getValue();

    if (!$mikrotikData || !isset($mikrotikData['ip'], $mikrotikData['username'], $mikrotikData['password'])) {
        echo json_encode(["success" => false, "error" => "Data login Mikrotik tidak ditemukan"]);
        exit;
    }

    // Buat koneksi ke Mikrotik
    $ip = $mikrotikData['ip'];
    $username = $mikrotikData['username'];
    $password = $mikrotikData['password'];

    $client = new RouterOS\Client($ip, $username, $password);

    $request = new RouterOS\Request('/ip/hotspot/user/profile/add');

    $request->setArgument('name', $data['name']);
    $request->setArgument('shared-users', $data['shared_users']);
    if (!empty($data['idle_timeout'])) {
        $request->setArgument('idle-timeout', $data['idle_timeout']);
    }
    $request->setArgument('session-timeout', $data['session_timeout']);
    $request->setArgument('rate-limit', $data['rate_limit']);
    if (!empty($data['on_login'])) {
        $request->setArgument('on-login', $data['on_login']);
    }

    $response = $client->sendSync($request);

    // 2. Simpan harga ke Firebase
    $database->getReference('user_profiles/' . $data['name'])->update([
        'price' => (int) $data['price']
    ]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "reason" => $e->getMessage()]);
}
