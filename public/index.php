<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

// Load environment early so CORS works for preflight requests too.
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$defaultAllowedOrigins = [
    'http://localhost:5173',
    'http://localhost:5174',
];

$configuredOrigins = array_filter(
    array_map('trim', explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? ''))
);
$allowVercelPreviewOrigins = filter_var(
    $_ENV['CORS_ALLOW_VERCEL_PREVIEWS'] ?? 'true',
    FILTER_VALIDATE_BOOLEAN
);
$allowedOrigins = array_values(array_unique(array_merge($defaultAllowedOrigins, $configuredOrigins)));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originHost = parse_url($origin, PHP_URL_HOST) ?: '';
$isVercelPreviewOrigin = $allowVercelPreviewOrigins
    && is_string($originHost)
    && preg_match('/\.vercel\.app$/i', $originHost);

if (in_array($origin, $allowedOrigins, true) || $isVercelPreviewOrigin) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// For simplicity, we'll just include the routes file.
// In a real application, you would have a more sophisticated router.
require_once __DIR__ . '/../routes/web.php';
