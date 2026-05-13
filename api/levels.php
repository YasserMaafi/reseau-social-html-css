<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Level.php';

Auth::init();

$user_id = Auth::getCurrentUserId();
$lang = $_GET['lang'] ?? null;
$difficulty = $_GET['difficulty'] ?? null;

if (!$user_id) {
    // Allow public access to level list
    $levels = Level::getAll($lang, $difficulty);
    header('Content-Type: application/json');
    echo json_encode($levels);
    return;
}

$user_id = Auth::getCurrentUserId();
$levels = Level::getAll($lang, $difficulty);

// Enrich with user progress
foreach ($levels as &$level) {
    $progress = DB::queryOne(
        'SELECT * FROM user_progress WHERE user_id = ? AND level_id = ?',
        [$user_id, $level['id']]
    );
    $level['user_progress'] = $progress;
}

header('Content-Type: application/json');
echo json_encode($levels);
