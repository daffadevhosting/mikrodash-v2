<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Autoload
require_once __DIR__ . '/vendor/PEAR2/Autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Kreait\Firebase\Factory;
use Firebase\Auth\Token\Exception\InvalidToken;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Firebase Admin SDK
$firebase = (new Factory)->withServiceAccount(__DIR__ . '/../secret/firebase-adminsdk.json');
$auth = $firebase->createAuth();

// Fungsi untuk verifikasi token dan ambil UID
function getFirebaseUid()
{
    global $auth;

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader) {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Authorization header missing"]);
        exit;
    }

    // Ambil token tanpa "Bearer"
    $jwt = trim(str_replace('Bearer', '', $authHeader));

    try {
        $verifiedToken = $auth->verifyIdToken($jwt);
        return $verifiedToken->claims()->get('sub'); // UID Firebase
    } catch (InvalidToken $e) {
        http_response_code(403);
        echo json_encode(["success" => false, "error" => "Invalid Firebase token"]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "error" => "Token verification failed: " . $e->getMessage()]);
        exit;
    }
}
