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
require_once __DIR__ . '/firebase_init.php';
use PEAR2\Net\RouterOS;

header('Content-Type: application/json');

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

    $ip = $mikrotikData['ip'];
    $username = $mikrotikData['username'];
    $password = $mikrotikData['password'];

    $client = new RouterOS\Client($ip, $username, $password);
    // Ambil semua user Hotspot (bisa ganti dengan /ppp/secret jika pakai PPP)
    $users = [];
    $allUsers = $client->sendSync(new RouterOS\Request('/ip/hotspot/user/print'));
    foreach ($allUsers as $res) {
        if ($res->getType() === RouterOS\Response::TYPE_DATA) {
            $user = [
                "username" => $res->getProperty('name') ?? '',
                "profile" => $res->getProperty('profile') ?? '',
                "limit_uptime" => $res->getProperty('limit-uptime') ?? '',
                "status" => "Offline",
                "ip" => '',
                "uptime" => '',
                "download" => '',
                "upload" => ''
            ];
            $users[$user['username']] = $user;
        }
    }

    // Ambil user yang sedang aktif
    $activeUsers = $client->sendSync(new RouterOS\Request('/ip/hotspot/active/print'));
    foreach ($activeUsers as $res) {
        if ($res->getType() === RouterOS\Response::TYPE_DATA) {
            $username = $res->getProperty('user');
            if (isset($users[$username])) {
                $users[$username]['status'] = "Online";
                $users[$username]['ip'] = $res->getProperty('address');
                $users[$username]['uptime'] = $res->getProperty('uptime');
                $users[$username]['download'] = formatBytes($res->getProperty('bytes-in'));
                $users[$username]['upload'] = formatBytes($res->getProperty('bytes-out'));
            }
        }
    }

    // Hapus user yang tidak punya username (null atau kosong)
    $cleaned = array_filter($users, fn($u) => !empty($u['username']));

    // Urutkan: yang online di atas
    usort($cleaned, function($a, $b) {
        return $a['status'] === 'Online' && $b['status'] !== 'Online' ? -1 :
               ($a['status'] !== 'Online' && $b['status'] === 'Online' ? 1 : 0);
    });

    echo json_encode(array_values($cleaned));
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

// Format byte ke MB/KB
function formatBytes($bytes) {
    if ($bytes === null) return '';
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . " MB";
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . " KB";
    } else {
        return $bytes . " B";
    }
}
