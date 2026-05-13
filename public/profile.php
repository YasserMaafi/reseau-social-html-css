<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/User.php';

Auth::init();

$username = $_GET['username'] ?? '';

if (!$username) {
    if (!Auth::isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
    $user = User::getById(Auth::getCurrentUserId());
} else {
    $user = User::getByUsername($username);
}

if (!$user) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:4rem"><h1>User not found</h1><a href="/">← Home</a></body></html>';
    exit;
}

$is_own_profile = Auth::isLoggedIn() && Auth::getCurrentUserId() == $user['id'];

// Handle avatar upload
$flash_error = null;
$flash_success = null;

if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['avatar'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed) && $file['size'] <= 2 * 1024 * 1024) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $upload_dir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                User::updateProfile($user['id'], ['avatar' => '/assets/uploads/' . $filename]);
                $user['avatar'] = '/assets/uploads/' . $filename;
                $flash_success = 'Avatar updated!';
            }
        } else {
            $flash_error = 'Invalid file. Use JPG/PNG/GIF/WEBP under 2MB.';
        }
    }

    if (isset($_POST['bio'])) {
        $bio = trim($_POST['bio']);
        User::updateProfile($user['id'], ['bio' => $bio]);
        $user['bio'] = $bio;
        $flash_success = $flash_success ?? 'Profile saved!';
    }
}

// Stats
$stats = User::getStats($user['id']);
$rank_row = DB::queryOne('SELECT rank FROM leaderboard WHERE id = ?', [$user['id']]);
$rank = $rank_row ? '#' . $rank_row['rank'] : '—';

// Achievements
$achievements = DB::queryAll(
    'SELECT a.* FROM achievements a
     INNER JOIN user_achievements ua ON a.id = ua.achievement_id
     WHERE ua.user_id = ? ORDER BY ua.unlocked_at DESC',
    [$user['id']]
);

// Completed levels with details
$completed_levels = DB::queryAll(
    'SELECT l.title, l.language, l.difficulty, l.type,
            up.points_earned, up.tries, up.time_spent_seconds, up.completed_at
     FROM user_progress up
     INNER JOIN levels l ON up.level_id = l.id
     WHERE up.user_id = ? AND up.status = ?
     ORDER BY up.completed_at DESC',
    [$user['id'], 'completed']
);

// Progress per difficulty
$diff_order = ['beginner', 'intermediate', 'advanced', 'senior'];
$diff_progress = [];
foreach ($diff_order as $diff) {
    $total = DB::queryOne('SELECT COUNT(*) as c FROM levels WHERE difficulty = ?', [$diff])['c'];
    $done  = DB::queryOne(
        'SELECT COUNT(*) as c FROM user_progress up
         INNER JOIN levels l ON up.level_id = l.id
         WHERE up.user_id = ? AND up.status = ? AND l.difficulty = ?',
        [$user['id'], 'completed', $diff]
    )['c'];
    $diff_progress[$diff] = ['total' => (int)$total, 'done' => (int)$done];
}

// Favorite language
$fav_lang_row = DB::queryOne(
    'SELECT l.language, COUNT(*) as cnt FROM user_progress up
     INNER JOIN levels l ON up.level_id = l.id
     WHERE up.user_id = ? AND up.status = ?
     GROUP BY l.language ORDER BY cnt DESC LIMIT 1',
    [$user['id'], 'completed']
);
$fav_lang = $fav_lang_row ? ucfirst($fav_lang_row['language']) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> — Profile</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .profile-page { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }

        /* Hero card */
        .profile-hero {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            border-radius: 12px;
            padding: 2.5rem 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }
        .hero-avatar {
            width: 100px; height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
            flex-shrink: 0;
            background: rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
        }
        .hero-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .hero-info h1 { font-size: 1.75rem; margin-bottom: 0.25rem; }
        .hero-info .bio { opacity: 0.8; font-size: 0.95rem; margin-bottom: 0.5rem; }
        .hero-info .meta { font-size: 0.8rem; opacity: 0.6; }
        .hero-badge {
            margin-left: auto;
            text-align: center;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1rem 1.5rem;
            flex-shrink: 0;
        }
        .hero-badge .rank-num { font-size: 2rem; font-weight: 700; color: #ffd700; }
        .hero-badge .rank-label { font-size: 0.75rem; opacity: 0.7; text-transform: uppercase; letter-spacing: 1px; }

        /* Stats row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            text-align: center;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border-top: 3px solid var(--primary-color);
        }
        .stat-card .val { font-size: 1.75rem; font-weight: 700; color: var(--primary-color); }
        .stat-card .lbl { font-size: 0.8rem; color: #888; margin-top: 0.25rem; }

        /* Two-column layout */
        .profile-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }

        /* Section card */
        .section-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .section-card h2 { font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; color: #333;
            text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.75rem; }

        /* Progress bars */
        .diff-row { margin-bottom: 1rem; }
        .diff-row:last-child { margin-bottom: 0; }
        .diff-label { display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.3rem; }
        .diff-label span:first-child { font-weight: 600; }
        .diff-label span:last-child { color: #888; }
        .progress-bar { height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 4px; transition: width 0.6s ease;
            background: linear-gradient(90deg, var(--primary-color), #00c6ff); }
        .progress-fill.done { background: linear-gradient(90deg, var(--success-color), #56ab2f); }

        /* Achievements */
        .ach-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
        .ach-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            border: 1px solid #eee;
            transition: transform 0.2s, border-color 0.2s;
        }
        .ach-item:hover { transform: translateY(-2px); border-color: var(--primary-color); }
        .ach-item .icon { font-size: 1.75rem; display: block; margin-bottom: 0.3rem; }
        .ach-item .name { font-size: 0.75rem; font-weight: 600; color: #444; }
        .ach-empty { color: #aaa; font-size: 0.875rem; text-align: center; padding: 1rem 0; }

        /* Completed levels table */
        .levels-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .levels-table th { text-align: left; padding: 0.5rem 0.75rem; color: #888;
            font-size: 0.75rem; text-transform: uppercase; border-bottom: 2px solid #f0f0f0; }
        .levels-table td { padding: 0.6rem 0.75rem; border-bottom: 1px solid #f5f5f5; }
        .levels-table tr:last-child td { border-bottom: none; }
        .lang-pill {
            display: inline-block; padding: 0.15rem 0.5rem; border-radius: 10px;
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
        }
        .lang-js { background: #fff3cd; color: #856404; }
        .lang-php { background: #d1ecf1; color: #0c5460; }
        .pts-badge { color: var(--success-color); font-weight: 700; }

        /* Edit section */
        .edit-section { background: white; border-radius: 10px; padding: 1.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
        .edit-section h2 { font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; color: #333;
            text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.75rem; }
        .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .edit-grid form { display: flex; flex-direction: column; gap: 0.5rem; }
        .edit-grid label { font-size: 0.8rem; font-weight: 600; color: #555; }

        @media (max-width: 768px) {
            .profile-hero { flex-direction: column; text-align: center; }
            .hero-badge { margin-left: 0; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .profile-cols { grid-template-columns: 1fr; }
            .edit-grid { grid-template-columns: 1fr; }
            .ach-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="/" class="nav-brand">🎓 Learning Platform</a>
            <div class="nav-menu">
                <a href="/">Practice</a>
                <a href="/leaderboard.php">Leaderboard</a>
                <?php if (Auth::isLoggedIn()): ?>
                    <?php if (Auth::isAdmin()): ?><a href="/admin">Admin</a><?php endif; ?>
                    <button id="logoutBtn">Logout</button>
                <?php else: ?>
                    <a href="/login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="profile-page">

        <?php if ($flash_error): ?>
            <div class="error" style="margin-bottom:1rem;"><?php echo htmlspecialchars($flash_error); ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
            <div class="success" style="margin-bottom:1rem;"><?php echo htmlspecialchars($flash_success); ?></div>
        <?php endif; ?>

        <!-- Hero -->
        <div class="profile-hero">
            <div class="hero-avatar">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="avatar">
                <?php else: ?>
                    👤
                <?php endif; ?>
            </div>
            <div class="hero-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <?php if (!empty($user['bio'])): ?>
                    <p class="bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                <?php endif; ?>
                <p class="meta">
                    Joined <?php echo date('F Y', strtotime($user['created_at'])); ?>
                    &nbsp;·&nbsp; Fav: <?php echo $fav_lang; ?>
                </p>
            </div>
            <div class="hero-badge">
                <div class="rank-num"><?php echo $rank; ?></div>
                <div class="rank-label">Global Rank</div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="val"><?php echo number_format($stats['user']['total_points']); ?></div>
                <div class="lbl">Total Points</div>
            </div>
            <div class="stat-card">
                <div class="val"><?php echo $stats['levels_completed']; ?></div>
                <div class="lbl">Levels Done</div>
            </div>
            <div class="stat-card">
                <div class="val"><?php echo $stats['achievements_count']; ?></div>
                <div class="lbl">Achievements</div>
            </div>
            <div class="stat-card">
                <div class="val"><?php echo $fav_lang; ?></div>
                <div class="lbl">Fav Language</div>
            </div>
        </div>

        <!-- Progress + Achievements -->
        <div class="profile-cols">
            <div class="section-card">
                <h2>Progress by Difficulty</h2>
                <?php foreach ($diff_order as $diff):
                    $p = $diff_progress[$diff];
                    $pct = $p['total'] > 0 ? round(($p['done'] / $p['total']) * 100) : 0;
                    $all_done = $p['total'] > 0 && $p['done'] >= $p['total'];
                ?>
                <div class="diff-row">
                    <div class="diff-label">
                        <span><?php echo ucfirst($diff); ?> <?php echo $all_done ? '✅' : ''; ?></span>
                        <span><?php echo $p['done']; ?>/<?php echo $p['total']; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $all_done ? 'done' : ''; ?>"
                             style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="section-card">
                <h2>Achievements</h2>
                <?php if (empty($achievements)): ?>
                    <p class="ach-empty">No achievements yet — complete levels to earn them!</p>
                <?php else: ?>
                <div class="ach-grid">
                    <?php foreach ($achievements as $ach): ?>
                        <div class="ach-item" title="<?php echo htmlspecialchars($ach['description']); ?>">
                            <span class="icon"><?php echo $ach['icon']; ?></span>
                            <span class="name"><?php echo htmlspecialchars($ach['name']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Completed Levels -->
        <div class="section-card" style="margin-bottom:1.5rem;">
            <h2>Completed Levels (<?php echo count($completed_levels); ?>)</h2>
            <?php if (empty($completed_levels)): ?>
                <p style="color:#aaa;text-align:center;padding:1rem 0;">No levels completed yet. <a href="/">Start practicing →</a></p>
            <?php else: ?>
            <table class="levels-table">
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Language</th>
                        <th>Difficulty</th>
                        <th>Points</th>
                        <th>Tries</th>
                        <th>Time</th>
                        <th>Completed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completed_levels as $cl): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($cl['title']); ?></td>
                        <td>
                            <span class="lang-pill <?php echo $cl['language'] === 'javascript' ? 'lang-js' : 'lang-php'; ?>">
                                <?php echo $cl['language'] === 'javascript' ? 'JS' : 'PHP'; ?>
                            </span>
                        </td>
                        <td><?php echo ucfirst($cl['difficulty']); ?></td>
                        <td class="pts-badge">+<?php echo number_format($cl['points_earned']); ?></td>
                        <td><?php echo $cl['tries']; ?></td>
                        <td><?php
                            $s = (int)$cl['time_spent_seconds'];
                            echo $s >= 60 ? floor($s/60).'m '.($s%60).'s' : $s.'s';
                        ?></td>
                        <td style="color:#888;font-size:0.8rem;"><?php echo $cl['completed_at'] ? date('M d, Y', strtotime($cl['completed_at'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Edit Profile (own profile only) -->
        <?php if ($is_own_profile): ?>
        <div class="edit-section">
            <h2>Edit Profile</h2>
            <div class="edit-grid">
                <form method="POST" enctype="multipart/form-data">
                    <label>Profile Picture</label>
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>"
                             style="width:60px;height:60px;border-radius:50%;object-fit:cover;margin-bottom:0.5rem;">
                    <?php endif; ?>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="color:#888;">JPG/PNG/GIF/WEBP · max 2MB</small>
                    <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">Upload Avatar</button>
                </form>

                <form method="POST">
                    <label>Bio</label>
                    <textarea name="bio" rows="4"
                        placeholder="Tell the community about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">Save Bio</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /profile-page -->

    <script>
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', () => window.location.href = '/logout.php');
    </script>
</body>
</html>
