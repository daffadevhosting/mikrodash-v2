<?php
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/firebase_init.php';

use Dotenv\Dotenv;
use PEAR2\Net\RouterOS;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Ambil token Firebase dari header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(["success" => false, "reason" => "Token tidak ditemukan"]);
    exit;
}
$idToken = $matches[1];

// Verifikasi token dan ambil UID
$uid = verifyFirebaseToken($idToken);
if (!$uid) {
    echo json_encode(["success" => false, "reason" => "Token tidak valid"]);
    exit;
}

try {
    // Ambil konfigurasi Mikrotik user dari Firebase
    global $database;
    $mikrotikData = $database
        ->getReference("mikrotik_config/$uid")
        ->getValue();

    if (!$mikrotikData || !isset($mikrotikData['ip'], $mikrotikData['username'], $mikrotikData['password'])) {
        echo json_encode(["success" => false, "reason" => "Konfigurasi Mikrotik tidak ditemukan"]);
        exit;
    }

    // Koneksi ke Mikrotik
    $client = new RouterOS\Client(
        $mikrotikData['ip'],
        $mikrotikData['username'],
        $mikrotikData['password']
    );
    
    // Ambil semua profile
    $request = new RouterOS\Request('/ip/hotspot/user/profile/print');
    $responses = $client->sendSync($request);

    $profileNames = [];
    foreach ($responses as $r) {
        $profileNames[] = $r->getArgument('name');
    }

    echo json_encode(["success" => true, "profiles" => $profileNames]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "reason" => $e->getMessage()]);
}
