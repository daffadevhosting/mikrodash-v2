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

$cmd = $_POST['command'] ?? '';
if (!$cmd) {
    echo json_encode(['output' => 'Perintah kosong']);
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

    $ip = $mikrotikData['ip'];
    $username = $mikrotikData['username'];
    $password = $mikrotikData['password'];

    $client = new RouterOS\Client($ip, $username, $password);

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

    echo json_encode(['output' => trim($output)]);

} catch (Exception $e) {
    echo json_encode(['output' => 'Koneksi gagal: ' . $e->getMessage()]);
}
