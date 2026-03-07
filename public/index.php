<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// ──────────────────────────────────────────────────────────────
// Handle CORS preflight (OPTIONS) before Laravel boots.
// On IIS/Plesk the OPTIONS request may never reach Laravel's
// CORS middleware, so we respond here directly.
// ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $allowedOrigins = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://nexttrip.asia',
        'https://www.nexttrip.asia',
        'https://admin.nexttrip.asia',
        'https://backend.nexttrip.asia',
        'https://nexttrip.world',
        'https://www.nexttrip.world',
        'https://admin.nexttrip.world',
        'https://backend.nexttrip.world',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        header('Content-Length: 0');
        http_response_code(204);
        exit(0);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
