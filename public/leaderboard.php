<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::init();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$leaderboard = DB::queryAll(
    'SELECT l.* FROM leaderboard l
     INNER JOIN users u ON l.id = u.id
     WHERE u.role = ? LIMIT ? OFFSET ?',
    ['user', $limit, $offset]
);
$total = DB::queryOne('SELECT COUNT(*) as count FROM users WHERE is_banned = FALSE AND role = ?', ['user']);
$total_pages = max(1, ceil($total['count'] / $limit));

$current_user_id = Auth::getCurrentUserId();

// Favorite language per user
$fav_langs = [];
if (!empty($leaderboard)) {
    $ids = array_column($leaderboard, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = DB::queryAll(
        "SELECT up.user_id, l.language, COUNT(*) as cnt
         FROM user_progress up
         INNER JOIN levels l ON up.level_id = l.id
         WHERE up.user_id IN ($placeholders) AND up.status = 'completed'
         GROUP BY up.user_id, l.language
         ORDER BY cnt DESC",
        $ids
    );
    foreach ($rows as $row) {
        if (!isset($fav_langs[$row['user_id']])) {
            $fav_langs[$row['user_id']] = $row['language'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - Learning Platform</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="/" class="nav-brand">🎓 Learning Platform</a>
            <div class="nav-menu">
                <a href="/">Code Editor</a>
                <a href="/leaderboard.php">Leaderboard</a>
                <?php if (Auth::isLoggedIn()): ?>
                    <a href="/profile.php">Profile</a>
                    <button id="logoutBtn">Logout</button>
                <?php else: ?>
                    <a href="/login.php">Login</a>
                    <a href="/register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="leaderboard-container">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
            <h1>🏆 Global Leaderboard</h1>
            <span id="refreshTimer" style="color:#999;font-size:0.875rem;">Refreshes in 60s</span>
        </div>

        <table class="leaderboard-table" id="leaderboardTable">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>User</th>
                    <th>Points</th>
                    <th>Levels</th>
                    <th>Fav Language</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaderboard as $entry): ?>
                    <tr <?php echo ($current_user_id && $entry['id'] == $current_user_id) ? 'class="current-user-row"' : ''; ?>>
                        <td>
                            <?php
                            if ($entry['rank'] == 1) echo '🥇';
                            elseif ($entry['rank'] == 2) echo '🥈';
                            elseif ($entry['rank'] == 3) echo '🥉';
                            else echo '#' . $entry['rank'];
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($entry['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($entry['avatar']); ?>" alt="" class="leaderboard-avatar">
                            <?php endif; ?>
                            <a href="/profile.php?username=<?php echo urlencode($entry['username']); ?>">
                                <?php echo htmlspecialchars($entry['username']); ?>
                            </a>
                            <?php if ($current_user_id && $entry['id'] == $current_user_id): ?>
                                <span style="color:#007bff;font-size:0.75rem;"> (you)</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($entry['total_points']); ?></td>
                        <td><?php echo $entry['levels_completed']; ?></td>
                        <td><?php echo isset($fav_langs[$entry['id']]) ? ucfirst($fav_langs[$entry['id']]) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leaderboard)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:2rem;">No users yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">← Previous</a>
            <?php endif; ?>
            <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next →</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', () => window.location.href = '/logout.php');

    // AJAX refresh every 60 seconds
    let countdown = 60;
    const timerEl = document.getElementById('refreshTimer');
    setInterval(() => {
        countdown--;
        timerEl.textContent = `Refreshes in ${countdown}s`;
        if (countdown <= 0) {
            fetch('/api/leaderboard.php')
                .then(r => r.json())
                .then(data => {
                    const tbody = document.querySelector('#leaderboardTable tbody');
                    const medals = ['🥇','🥈','🥉'];
                    tbody.innerHTML = data.map(e => `
                        <tr>
                            <td>${e.rank <= 3 ? medals[e.rank-1] : '#'+e.rank}</td>
                            <td>${e.avatar ? `<img src="${e.avatar}" class="leaderboard-avatar">` : ''}
                                <a href="/profile.php?username=${encodeURIComponent(e.username)}">${e.username}</a></td>
                            <td>${Number(e.total_points).toLocaleString()}</td>
                            <td>${e.levels_completed}</td>
                            <td>—</td>
                        </tr>`).join('');
                    countdown = 60;
                    timerEl.textContent = 'Refreshes in 60s';
                });
        }
    }, 1000);
    </script>
</body>
</html>
