// getRouters.php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

header('Content-Type: application/json');
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$firebase_url = rtrim($_ENV['FIREBASE_DB_URL'], '/') . '/mikrotik_config.json';
$response = file_get_contents($firebase_url);
echo $response;
