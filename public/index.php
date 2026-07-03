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
        'https://nexttripholiday.com',
        'https://www.nexttripholiday.com',
        'https://admin.nexttripholiday.com',
        'https://backend.nexttripholiday.com',
        'https://nexttripholiday.com',
        'https://www.nexttripholiday.com',
        'https://admin.nexttripholiday.com',
        'https://backend.nexttripholiday.com',
    ];

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-HTTP-Method-Override');
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

// Enable method override so POST + X-HTTP-Method-Override header works
// This is required because IIS/Plesk blocks PUT/PATCH/DELETE verbs
Request::enableHttpMethodParameterOverride();

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
