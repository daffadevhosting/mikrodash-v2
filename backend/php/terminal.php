<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json');

// Tangani preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/firebase_init.php';

use Dotenv\Dotenv;
use PEAR2\Net\RouterOS;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Ambil token dari header Authorization
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(["success" => false, "error" => "Token tidak ditemukan di header Authorization"]);
    exit;
}
$idToken = $matches[1];

// Verifikasi token Firebase
$uid = verifyFirebaseToken($idToken);
if (!$uid) {
    echo json_encode(["success" => false, "error" => "Token tidak valid atau expired"]);
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

try {
    // Ambil konfigurasi Mikrotik dari Firebase
    global $database;
    $db = $database;
    $mikrotikData = $db->getReference("mikrotik_config/$uid")->getValue();

    if (!$mikrotikData || !isset($mikrotikData['ip'], $mikrotikData['username'], $mikrotikData['password'])) {
        echo json_encode(["success" => false, "error" => "Data login Mikrotik tidak ditemukan"]);
        exit;
    }

    // Koneksi ke MikroTik
    $client = new RouterOS\Client($mikrotikData['ip'], $mikrotikData['username'], $mikrotikData['password']);

    $request = new RouterOS\Request($cmd);
    $responses = $client->sendSync($request);

    $output = "";
    foreach ($responses as $response) {
        if ($response->getType() === RouterOS\Response::TYPE_DATA) {
            foreach ($response->getIterator() as $key => $value) {
                $output .= "$key: $value\n";
            }
            $output .= "----\n";
        } elseif ($response->getType() === RouterOS\Response::TYPE_ERROR) {
            $output .= "Error: " . $response->getMessage() . "\n";
        }
    }

    echo json_encode([
        "success" => true,
        "output" => trim($output)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Koneksi gagal: " . $e->getMessage()
    ]);
}
