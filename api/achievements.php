<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Achievement.php';

Auth::init();
Auth::requireLogin();

$user_id = Auth::getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $achievements = Achievement::getUserAchievements($user_id);
    header('Content-Type: application/json');
    echo json_encode($achievements);
    return;
}

http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['error' => 'Method not allowed']);
