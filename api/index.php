<?php

// Normalise SCRIPT_NAME and SCRIPT_FILENAME so Laravel routing preserves /api prefix
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

// Handle CORS Preflight (OPTIONS) requests immediately
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit(0);
}

// Delegate all other requests to Laravel's built-in HandleCors middleware
require __DIR__ . '/../public/index.php';
