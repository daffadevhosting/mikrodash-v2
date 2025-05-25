<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';
use PEAR2\Net\RouterOS;

$uid = getFirebaseUid(); // ✅ sudah diverifikasi

// Ambil form input
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$ip = $input['ip'] ?? '';
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (!$ip || !$username || !$password) {
    echo json_encode(["success" => false, "error" => "IP, Username, dan Password wajib diisi."]);
    exit;
}

try {
    $client = new RouterOS\Client($ip, $username, $password);

    // Ambil token dari header Authorization
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Authorization header missing"]);
        exit;
    }
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $jwt = $matches[1];
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Invalid Authorization header format"]);
        exit;
    }

    $firebase_url = $_ENV['FIREBASE_DB_URL'] . "/mikrotik_config/$uid.json?auth=$jwt";

    $data = [
        'ip' => $ip,
        'username' => $username,
        'password' => $password
    ];

    $ch = curl_init($firebase_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo json_encode([
            "success" => true,
            "msg" => "Tersimpan ke Firebase",
            "firebase_response" => $response
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Gagal simpan ke Firebase",
            "httpCode" => $httpCode,
            "firebase_response" => $response
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Gagal koneksi Mikrotik atau error lain: " . $e->getMessage()
    ]);
}
