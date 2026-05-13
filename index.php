<?php
// Load .env file
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key) putenv("$key=$value");
    }
}

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/bootstrap.php';

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip trailing slash
$request_uri = rtrim($request_uri, '/') ?: '/';

// Route API requests
if (strpos($request_uri, '/api/') === 0) {
    $name = pathinfo(basename($request_uri), PATHINFO_FILENAME);
    $api_file = __DIR__ . '/api/' . $name . '.php';
    if (file_exists($api_file)) { require_once $api_file; exit; }
}

// Route admin requests
if (strpos($request_uri, '/admin') === 0) {
    $name = pathinfo(basename($request_uri), PATHINFO_FILENAME) ?: 'index';
    $admin_file = __DIR__ . '/admin/' . $name . '.php';
    if (file_exists($admin_file)) { require_once $admin_file; exit; }
}

// Route public pages
$name = pathinfo(basename($request_uri), PATHINFO_FILENAME) ?: 'index';
$public_file = __DIR__ . '/public/' . $name . '.php';
if (file_exists($public_file)) { require_once $public_file; exit; }

// Default to homepage
require_once __DIR__ . '/public/index.php';
