<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/User.php';

Auth::init();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST' && isset($input['action']) && $input['action'] === 'register') {
    $result = Auth::register($input['username'] ?? '', $input['email'] ?? '', $input['password'] ?? '');
    header('Content-Type: application/json');
    http_response_code($result['success'] ? 201 : 400);
    echo json_encode($result);
} elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'login') {
    $result = Auth::login($input['username'] ?? '', $input['password'] ?? '');
    header('Content-Type: application/json');
    http_response_code($result['success'] ? 200 : 401);
    echo json_encode($result);
} elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'logout') {
    Auth::logout();
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'update_profile') {
    Auth::requireLogin();
    $bio = htmlspecialchars(trim($input['bio'] ?? ''), ENT_QUOTES, 'UTF-8');
    DB::execute('UPDATE users SET bio = ? WHERE id = ?', [$bio, Auth::getCurrentUserId()]);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} elseif ($method === 'POST' && isset($input['action']) && $input['action'] === 'csrf') {
    header('Content-Type: application/json');
    echo json_encode(['token' => Auth::generateCSRFToken()]);
} elseif ($method === 'GET') {
    Auth::requireLogin();
    $user = User::getById(Auth::getCurrentUserId());
    header('Content-Type: application/json');
    echo json_encode($user);
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found']);
}
