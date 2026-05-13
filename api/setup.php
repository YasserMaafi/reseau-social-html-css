<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';

// Allow this to be run once to set up initial admin
$token = $_GET['token'] ?? '';
$setup_token = getenv('SETUP_TOKEN') ?: 'setup123';

if ($token !== $setup_token) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized. Set SETUP_TOKEN environment variable.']));
}

// Check if any admin exists
$admin_exists = DB::queryOne('SELECT id FROM users WHERE role = ?', ['admin']);
if ($admin_exists) {
    http_response_code(400);
    die(json_encode(['error' => 'Admin account already exists']));
}

// Create admin account
$result = Auth::register('admin', 'admin@localhost', 'admin123456');
if (!$result['success']) {
    http_response_code(400);
    die(json_encode(['error' => $result['error']]));
}

// Get the created user and promote to admin
$admin_user = DB::queryOne('SELECT id FROM users WHERE username = ?', ['admin']);
DB::execute('UPDATE users SET role = ? WHERE id = ?', ['admin', $admin_user['id']]);

// Also create a test user
Auth::register('student', 'student@localhost', 'student123456');

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Setup complete. Admin and test user created.',
    'admin' => ['username' => 'admin', 'password' => 'admin123456'],
    'student' => ['username' => 'student', 'password' => 'student123456']
]);
