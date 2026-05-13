<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::init();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    return;
}

$leaderboard = DB::queryAll('SELECT * FROM leaderboard LIMIT 100');

header('Content-Type: application/json');
echo json_encode($leaderboard);
