<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Content-Type: application/json");

require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/firebase_init.php';

use PEAR2\Net\RouterOS;

// Ambil token dari header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    echo json_encode(["success" => false, "message" => "Token tidak ditemukan"]);
    exit;
}
$idToken = $matches[1];

// Verifikasi token Firebase
$uid = verifyFirebaseToken($idToken);
if (!$uid) {
    echo json_encode(["success" => false, "message" => "Token tidak valid"]);
    exit;
}

try {
    global $database;
    $mikrotikData = $database->getReference("mikrotik_config/$uid")->getValue();

    if (!$mikrotikData) {
        throw new Exception("Data Mikrotik tidak ditemukan");
    }

    $client = new RouterOS\Client(
        $mikrotikData['ip'],
        $mikrotikData['username'],
        $mikrotikData['password'],
        3
    );

    // Dapatkan semua interface
    $interfaces = $client->sendSync(new RouterOS\Request('/interface/print'));
    $selectedInterface = null;

    foreach ($interfaces as $intf) {
        if ($intf->getType() === RouterOS\Response::TYPE_DATA) {
            $name = $intf->getProperty('name');

            // Cek trafik
            $req = new RouterOS\Request('/interface/monitor-traffic');
            $req->setArgument('interface', $name);
            $req->setArgument('once', '');

            $res = $client->sendSync($req);

            foreach ($res as $r) {
                if ($r->getType() === RouterOS\Response::TYPE_DATA) {
                    $rx = (int)$r->getProperty('rx-bits-per-second');
                    $tx = (int)$r->getProperty('tx-bits-per-second');

                    if ($rx > 0 || $tx > 0) {
                        $selectedInterface = [
                            'name' => $name,
                            'rx' => round($rx / 1024 / 1024, 2),
                            'tx' => round($tx / 1024 / 1024, 2)
                        ];
                        break 2;
                    }
                }
            }
        }
    }

    if (!$selectedInterface) {
        throw new Exception("Tidak ada interface dengan trafik aktif.");
    }

    // Logging interface ke Firebase
    $database->getReference("interface_logs/$uid")->set([
        'interface' => $selectedInterface['name'],
        'download' => $selectedInterface['rx'],
        'upload' => $selectedInterface['tx'],
        'timestamp' => date('c')  // ISO8601 format
    ]);

    echo json_encode([
        "success" => true,
        "interface" => $selectedInterface['name'],
        "download" => $selectedInterface['rx'],
        "upload" => $selectedInterface['tx']
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Gagal ambil data: " . $e->getMessage()
    ]);
}
