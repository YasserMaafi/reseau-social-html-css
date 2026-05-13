<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Level.php';
require_once __DIR__ . '/../src/User.php';

Auth::init();

$user_id = Auth::getCurrentUserId();
$level_id = $_GET['id'] ?? null;
$level = $level_id ? Level::getById($level_id) : null;

// Check lock
if ($level && $user_id && !Level::isUnlocked($level['id'], $user_id)) {
    header('Location: /?locked=1');
    exit;
}

if (!$level_id || !$level) {
    $level = Level::getNext($user_id);
}

// Get all levels for sidebar navigation
$all_levels = DB::queryAll(
    'SELECT l.*,
        CASE WHEN up.status = \'completed\' THEN \'completed\'
             WHEN up.status IS NOT NULL THEN \'in_progress\'
             ELSE NULL END as progress_status
     FROM levels l
     LEFT JOIN user_progress up ON l.id = up.level_id AND up.user_id = ?
     ORDER BY CASE l.difficulty WHEN \'beginner\' THEN 1 WHEN \'intermediate\' THEN 2 WHEN \'advanced\' THEN 3 WHEN \'senior\' THEN 4 END, l.order_index',
    [$user_id]
);

$completed_count = count(array_filter($all_levels, fn($l) => $l['progress_status'] === 'completed'));
$total_count = count($all_levels);

$user_progress = ($level && $user_id)
    ? DB::queryOne('SELECT * FROM user_progress WHERE user_id = ? AND level_id = ?', [$user_id, $level['id']])
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($level['title'] ?? 'Learning Platform'); ?> - Learning Platform</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/editor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="/" class="nav-brand">🎓 Learning Platform</a>
            <div class="nav-menu">
                <?php if (Auth::isLoggedIn()): ?>
                    <a href="/profile.php">Profile</a>
                    <a href="/leaderboard.php">Leaderboard</a>
                    <?php if (Auth::isAdmin()): ?>
                        <a href="/admin">Admin</a>
                    <?php endif; ?>
                    <button id="logoutBtn">Logout</button>
                <?php else: ?>
                    <a href="/login.php">Login</a>
                    <a href="/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php if (isset($_GET['locked'])): ?>
        <div class="alert alert-warning" style="text-align:center;padding:1rem;background:#fff3cd;color:#856404;border-bottom:1px solid #ffc107;">
            🔒 Complete the previous level first to unlock this one.
        </div>
    <?php endif; ?>

    <div class="app-layout">
        <!-- Sidebar: Level List -->
        <?php if (!empty($all_levels)): ?>
        <aside class="level-sidebar">
            <div class="sidebar-header">
                <span class="sidebar-title">Levels</span>
                <?php if ($user_id): ?>
                <span class="sidebar-progress"><?php echo $completed_count; ?>/<?php echo $total_count; ?></span>
                <?php endif; ?>
            </div>
            <?php
            $current_diff = null;
            // Find the "next" level id — first unlocked incomplete level
            $next_level_id = null;
            foreach ($all_levels as $lvl) {
                if ($lvl['progress_status'] !== 'completed' && Level::isUnlocked($lvl['id'], $user_id)) {
                    $next_level_id = $lvl['id'];
                    break;
                }
            }
            foreach ($all_levels as $lvl):
                if ($lvl['difficulty'] !== $current_diff):
                    if ($current_diff !== null) echo '</div>';
                    $current_diff = $lvl['difficulty'];
                    // Count completed in this difficulty
                    $diff_done = count(array_filter($all_levels, fn($l) => $l['difficulty'] === $current_diff && $l['progress_status'] === 'completed'));
                    $diff_total = count(array_filter($all_levels, fn($l) => $l['difficulty'] === $current_diff));
                    echo '<div class="sidebar-group">';
                    echo '<div class="sidebar-group-header">';
                    echo '<h4>' . ucfirst($current_diff) . '</h4>';
                    echo '<span class="sidebar-group-count">' . $diff_done . '/' . $diff_total . '</span>';
                    echo '</div>';
                endif;
                $is_current  = $level && $lvl['id'] == $level['id'];
                $is_completed = $lvl['progress_status'] === 'completed';
                $is_next     = $lvl['id'] == $next_level_id;
                $is_unlocked = Level::isUnlocked($lvl['id'], $user_id);
                $cls = 'sidebar-level';
                if ($is_current)   $cls .= ' active';
                if ($is_completed) $cls .= ' completed';
                if ($is_next && !$is_current) $cls .= ' next-up';
                if (!$is_unlocked) $cls .= ' locked';
            ?>
                <a href="<?php echo $is_unlocked ? '/?id=' . $lvl['id'] : '#'; ?>"
                   class="<?php echo $cls; ?>"
                   <?php echo !$is_unlocked ? 'onclick="return false;" title="Complete the previous level first"' : 'title="' . htmlspecialchars($lvl['title']) . '"'; ?>>
                    <span class="sidebar-icon"><?php
                        if ($is_completed) echo '✅';
                        elseif ($is_next && !$is_current) echo '▶';
                        elseif ($is_unlocked) echo '○';
                        else echo '🔒';
                    ?></span>
                    <span class="sidebar-name"><?php echo htmlspecialchars($lvl['title']); ?></span>
                    <?php if ($is_next && !$is_current): ?>
                        <span class="next-badge">NEXT</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <?php if ($current_diff !== null) echo '</div>'; ?>
        </aside>
        <?php endif; ?>

        <main class="editor-container">
            <?php if (!$level): ?>
                <div class="empty-state">
                    <h2>No levels available yet</h2>
                    <p>An admin needs to create levels before you can start learning.</p>
                    <?php if (Auth::isLoggedIn() && Auth::isAdmin()): ?>
                        <a href="/admin/levels.php" class="btn btn-primary">Create Levels</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>

            <div class="level-header">
                <div class="level-meta">
                    <span class="badge badge-<?php echo $level['language']; ?>"><?php echo strtoupper($level['language']); ?></span>
                    <span class="badge badge-difficulty"><?php echo ucfirst($level['difficulty']); ?></span>
                    <span class="badge badge-type"><?php echo $level['type'] === 'code_challenge' ? '💻 Code Challenge' : '🎨 Page Recreation'; ?></span>
                </div>
                <h2><?php echo htmlspecialchars($level['title']); ?></h2>
                <p><?php echo htmlspecialchars($level['description']); ?></p>
                <?php if ($user_progress && $user_progress['status'] === 'completed'): ?>
                    <div class="completed-banner">✅ Completed — <?php echo $user_progress['points_earned']; ?> pts earned</div>
                <?php endif; ?>
            </div>

            <div class="editor-pane <?php echo $level['type'] === 'page_recreation' ? 'page-recreation' : ''; ?>">
                <div class="editor-section">
                    <h3><?php echo $level['language'] === 'javascript' ? 'JavaScript' : ($level['type'] === 'page_recreation' ? 'HTML/CSS/JS' : 'PHP'); ?> Editor</h3>
                    <textarea id="codeEditor"></textarea>
                    <div class="editor-controls">
                        <button id="runBtn" class="btn btn-primary">▶ Run</button>
                        <button id="submitBtn" class="btn btn-success">✔ Submit</button>
                        <button id="hintBtn" class="btn btn-secondary">💡 Hint (-100pts)</button>
                    </div>
                </div>

                <div class="output-section">
                    <?php if ($level['type'] === 'page_recreation'): ?>
                        <div class="recreation-panes">
                            <div class="recreation-pane">
                                <h3>Reference</h3>
                                <?php if (!empty($level['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($level['image_path']); ?>" alt="Reference" class="reference-image">
                                <?php else: ?>
                                    <p class="no-image">No reference image uploaded yet.</p>
                                <?php endif; ?>
                            </div>
                            <div class="recreation-pane">
                                <h3>Your Preview</h3>
                                <iframe id="previewFrame" class="preview-frame" sandbox="allow-scripts"></iframe>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="output-tabs">
                            <button class="tab-btn active" data-tab="output">Output</button>
                            <button class="tab-btn" data-tab="expected">Expected</button>
                        </div>
                        <div id="outputTab" class="tab-content active">
                            <pre id="output"></pre>
                        </div>
                        <div id="expectedTab" class="tab-content">
                            <pre id="expected"><?php echo htmlspecialchars($level['expected_output'] ?? ''); ?></pre>
                        </div>
                    <?php endif; ?>

                    <div id="feedback" class="feedback-section"></div>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>

    <div id="achievementToast" class="toast" style="display:none;"></div>

    <script>
        const level = <?php echo json_encode($level); ?>;
        const isLoggedIn = <?php echo Auth::isLoggedIn() ? 'true' : 'false'; ?>;
        const isPageRecreation = <?php echo ($level && $level['type'] === 'page_recreation') ? 'true' : 'false'; ?>;
    </script>
    <script src="/assets/js/editor.js"></script>
</body>
</html>
