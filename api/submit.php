<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Level.php';
require_once __DIR__ . '/../src/Scorer.php';
require_once __DIR__ . '/../src/Achievement.php';
require_once __DIR__ . '/../src/User.php';

Auth::init();
Auth::requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$user_id    = Auth::getCurrentUserId();
$level_id   = $input['level_id'] ?? null;
$code       = $input['code'] ?? '';
$time_spent = (int)($input['time_spent_seconds'] ?? 0);
$tries      = max(1, (int)($input['tries'] ?? 1));
$hint_used  = (bool)($input['hint_used'] ?? false);

if (!$level_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing level_id']);
    exit;
}

$level = Level::getById($level_id);
if (!$level) {
    http_response_code(404);
    echo json_encode(['error' => 'Level not found']);
    exit;
}

// ── Page Recreation: submit for manual review, no auto-scoring ────────────────
if ($level['type'] === 'page_recreation') {

    // Check if already completed (admin already awarded points)
    $existing = DB::queryOne(
        'SELECT status FROM user_progress WHERE user_id = ? AND level_id = ?',
        [$user_id, $level_id]
    );
    if ($existing && $existing['status'] === 'completed') {
        echo json_encode(['pending_review' => false, 'already_completed' => true]);
        exit;
    }

    // Check if already has a pending submission
    $pending = DB::queryOne(
        "SELECT id FROM submissions WHERE user_id = ? AND level_id = ? AND review_status = 'pending'",
        [$user_id, $level_id]
    );

    if ($pending) {
        // Update the existing pending submission with latest code
        DB::execute(
            "UPDATE submissions SET code_submitted = ?, time_spent_seconds = ?, tries = ?, created_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$code, $time_spent, $tries, $pending['id']]
        );
    } else {
        // Log as pending review
        DB::execute(
            "INSERT INTO submissions (user_id, level_id, code_submitted, output_produced, time_spent_seconds, tries, passed, review_status)
             VALUES (?, ?, ?, '', ?, ?, FALSE, 'pending')",
            [$user_id, $level_id, $code, $time_spent, $tries]
        );

        // Create/update user_progress as in_progress
        $progress = DB::queryOne(
            'SELECT id FROM user_progress WHERE user_id = ? AND level_id = ?',
            [$user_id, $level_id]
        );
        if ($progress) {
            DB::execute(
                'UPDATE user_progress SET tries = tries + 1, time_spent_seconds = ? WHERE user_id = ? AND level_id = ?',
                [$time_spent, $user_id, $level_id]
            );
        } else {
            DB::execute(
                'INSERT INTO user_progress (user_id, level_id, status, tries, time_spent_seconds, hint_used) VALUES (?, ?, ?, ?, ?, ?)',
                [$user_id, $level_id, 'in_progress', $tries, $time_spent, $hint_used ? 1 : 0]
            );
        }
    }

    echo json_encode(['pending_review' => true]);
    exit;
}

// ── Code Challenge: auto-score ────────────────────────────────────────────────
$output = $input['output'] ?? '';
$result = Scorer::recordSubmission(
    $user_id, $level_id, $code, $output,
    $level['expected_output'], $time_spent, $tries, $hint_used
);

$unlocked_achievements = [];
$points        = null;
$next_level_id = null;

if ($result['passed']) {
    $progress = DB::queryOne(
        'SELECT * FROM user_progress WHERE user_id = ? AND level_id = ?',
        [$user_id, $level_id]
    );
    $points = $progress['points_earned'] ?? 0;

    $unlocked_achievements = Achievement::checkAndUnlock(
        $user_id, $level_id,
        (int)($progress['time_spent_seconds'] ?? 0),
        (int)($progress['tries'] ?? 1),
        (bool)($progress['hint_used'] ?? false)
    );

    $next = Level::getNext($user_id, $level['language']);
    if ($next && $next['id'] != $level_id) {
        $next_level_id = $next['id'];
    }
} else {
    $result['debug_got']      = Scorer::normalize($output);
    $result['debug_expected'] = Scorer::normalize($level['expected_output']);
}

$result['unlocked_achievements'] = $unlocked_achievements;
$result['points']                = $points;
$result['next_level_id']         = $next_level_id;

echo json_encode($result);
