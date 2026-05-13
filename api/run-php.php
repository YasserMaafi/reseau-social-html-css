<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::init();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Session-based rate limiting
$rate_key = 'php_run_timestamps';
$now = time();
$_SESSION[$rate_key] = array_values(array_filter(
    $_SESSION[$rate_key] ?? [],
    fn($t) => $t > $now - 60
));

if (count($_SESSION[$rate_key]) >= MAX_RUN_PHP_REQUESTS_PER_MINUTE) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Max 10 runs per minute.']);
    exit;
}
$_SESSION[$rate_key][] = $now;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$code = trim($input['code'] ?? '');

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['error' => 'No code provided']);
    exit;
}

// Block dangerous constructs
$dangerous = ['exec', 'system', 'passthru', 'shell_exec', 'popen', 'proc_open',
              'file_put_contents', 'file_get_contents', 'fopen', 'fwrite', 'unlink',
              'rmdir', 'mkdir', 'rename', 'copy', 'include', 'require',
              'include_once', 'require_once', 'phpinfo', 'base64_decode', 'curl_'];
foreach ($dangerous as $fn) {
    if (stripos($code, $fn) !== false) {
        echo json_encode(['output' => '', 'error' => "Function '{$fn}' is not allowed"]);
        exit;
    }
}

// Strip opening PHP tag if present
$code = preg_replace('/^\s*<\?php\s*/i', '', $code);

$output = '';
$error = null;

try {
    ob_start();
    set_time_limit(PHP_EXECUTION_TIMEOUT);
    eval($code);
    $output = ob_get_clean();
} catch (Throwable $e) {
    ob_end_clean();
    $error = $e->getMessage();
    $output = 'Error: ' . $error;
}

echo json_encode(['output' => $output, 'error' => $error]);
