<?php
ini_set('session.save_path', sys_get_temp_dir());
/**
 * Bootstrap file - Include this at the top of all PHP files that need the app environment
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'development' ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Set default timezone
date_default_timezone_set('UTC');

// Session is managed entirely by Auth::init() — do not start it here

// CORS headers for API
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

// Set JSON response header for API endpoints
if (strpos($_SERVER['REQUEST_URI'], '/api/') === 0) {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
}

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
