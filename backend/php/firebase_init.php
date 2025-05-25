<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php'; // Autoload composer

use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Ambil env vars
define('FIREBASE_API_KEY', $_ENV['FIREBASE_API_KEY']);
define('FIREBASE_PROJECT_ID', $_ENV['FIREBASE_PROJECT_ID']);
define('FIREBASE_DB_URL', $_ENV['FIREBASE_DB_URL']);
define('FIREBASE_CREDENTIAL_PATH', $_ENV['FIREBASE_CREDENTIAL_PATH']);

// Load Firebase Admin SDK
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

try {
    $factory = (new Factory)
        ->withServiceAccount(__DIR__ . '/../' . FIREBASE_CREDENTIAL_PATH)
        ->withDatabaseUri(FIREBASE_DB_URL);

    $auth = $factory->createAuth();
    $database = $factory->createDatabase();
} catch (Exception $e) {
    die("Firebase initialization error: " . $e->getMessage());
}

// Fungsi verifikasi token
function verifyFirebaseToken(string $idToken)
{
    global $auth;
    try {
        $verifiedIdToken = $auth->verifyIdToken($idToken);
        return $verifiedIdToken->claims()->get('sub'); // UID
    } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
        return null;
    }
}