<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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

$statusMessage = '';
$statusClass = '';
$icon = '';
$routerInfo = [];

try {
  $client = new RouterOS\Client($config['ip'], $config['username'], $config['password']);
    $statusMessage = "Terhubung ke MikroTik!";
    $statusClass = 'success';
    $icon = 'bi-check-circle-fill';

// Dapatkan resource info (uptime, versi, board-name)
$responses = $client->sendSync(new RouterOS\Request('/system/resource/print'));
foreach ($responses as $response) {
    if ($response->getType() === RouterOS\Response::TYPE_DATA) {
        $routerInfo['version'] = $response->getProperty('version');
        $routerInfo['board_name'] = $response->getProperty('board-name');
        $routerInfo['uptime'] = $response->getProperty('uptime');
    }
}

    // Dapatkan identity
    $responses = $client->sendSync(new RouterOS\Request('/system/identity/print'));
    foreach ($responses as $response) {
        if ($response->getType() === RouterOS\Response::TYPE_DATA) {
            $routerInfo['identity'] = $response->getProperty('name');
        }
    }

    // Dapatkan uptime
    $responses = $client->sendSync(new RouterOS\Request('/system/uptime/print'));
    foreach ($responses as $response) {
        if ($response->getType() === RouterOS\Response::TYPE_DATA) {
            $routerInfo['uptime'] = $response->getProperty('uptime');
        }
    }

} catch (Exception $e) {
    $statusMessage = "Gagal terhubung: " . $e->getMessage();
    $statusClass = 'danger';
    $icon = 'bi-x-circle-fill';
}
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Status Koneksi MikroTik</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Google Fonts + Bootstrap + Bootstrap Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            transition: background 0.5s ease;
        }
        .card {
            background-color: var(--bs-card-bg, #1c1f26);
            border-radius: 20px;
            box-shadow: 0 15px 25px rgba(0,0,0,0.4);
            padding: 30px;
            max-width: 550px;
            width: 100%;
        }
        .card h1 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        .status-box {
            padding: 15px;
            border-radius: 12px;
            font-weight: 600;
            text-align: center;
            margin-top: 20px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .status-box.success {
            background-color: #28a74533;
            color: #28a745;
        }
        .status-box.danger {
            background-color: #dc354533;
            color: #dc3545;
        }
        .bi {
            font-size: 1.4rem;
        }
        .router-info {
            text-align: left;
            margin-top: 20px;
        }
        .router-info dt {
            font-weight: 600;
        }
        .theme-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
    <button class="btn btn-outline-light theme-toggle" onclick="toggleTheme()">
        <i class="bi bi-circle-half"></i>
    </button>

    <div class="card text-center">
        <h1>Status Koneksi MikroTik</h1>
        <p class="text-muted">Menampilkan hasil koneksi dan info router Mikrotik Anda.</p>
        <div class="status-box <?= $statusClass ?>">
            <i class="bi <?= $icon ?>"></i>
            <?= htmlspecialchars($statusMessage) ?>
        </div>

        <?php if (!empty($routerInfo)): ?>
        <dl class="router-info mt-4">
            <dt>Identity:</dt>
            <dd><?= htmlspecialchars($routerInfo['identity']) ?></dd>

            <dt>Versi RouterOS:</dt>
            <dd><?= htmlspecialchars($routerInfo['version']) ?></dd>

            <dt>Board:</dt>
            <dd><?= htmlspecialchars($routerInfo['board_name']) ?></dd>

            <dt>Uptime:</dt>
            <dd><?= htmlspecialchars($routerInfo['uptime']) ?></dd>
        </dl>
        <?php endif; ?>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute("data-bs-theme");
            html.setAttribute("data-bs-theme", current === "light" ? "dark" : "light");
        }
    </script>
</body>
</html>
