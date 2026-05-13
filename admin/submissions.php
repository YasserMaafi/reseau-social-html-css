<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/User.php';

Auth::init();
Auth::requireAdmin();

$message = null;
$error   = null;

// ── Award points for a page recreation submission ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['award_points'])) {
    $sub_id   = (int)$_POST['sub_id'];
    $user_id  = (int)$_POST['user_id'];
    $level_id = (int)$_POST['level_id'];
    $points   = max(0, min(1000, (int)$_POST['points']));

    // Mark submission as reviewed
    DB::execute(
        "UPDATE submissions SET passed = TRUE, review_status = 'reviewed' WHERE id = ?",
        [$sub_id]
    );

    // Upsert user_progress as completed
    $progress = DB::queryOne(
        'SELECT * FROM user_progress WHERE user_id = ? AND level_id = ?',
        [$user_id, $level_id]
    );
    if ($progress) {
        $diff = $points - (int)$progress['points_earned'];
        DB::execute(
            "UPDATE user_progress SET status='completed', points_earned=?, completed_at=COALESCE(completed_at, CURRENT_TIMESTAMP) WHERE user_id=? AND level_id=?",
            [$points, $user_id, $level_id]
        );
        if ($diff > 0) User::updateTotalPoints($user_id, $diff);
    } else {
        DB::execute(
            "INSERT INTO user_progress (user_id, level_id, status, points_earned, tries, time_spent_seconds, completed_at) VALUES (?,?,'completed',?,1,0,CURRENT_TIMESTAMP)",
            [$user_id, $level_id, $points]
        );
        User::updateTotalPoints($user_id, $points);
    }

    // Mark all other pending submissions for this user+level as reviewed too
    DB::execute(
        "UPDATE submissions SET review_status='reviewed' WHERE user_id=? AND level_id=? AND review_status='pending'",
        [$user_id, $level_id]
    );

    $message = "Awarded $points points to user #$user_id for level #$level_id.";
}

// ── Manual point override (code challenges) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['override_points'])) {
    $user_id  = (int)$_POST['user_id'];
    $level_id = (int)$_POST['level_id'];
    $points   = max(0, (int)$_POST['points']);

    $progress = DB::queryOne(
        'SELECT * FROM user_progress WHERE user_id = ? AND level_id = ?',
        [$user_id, $level_id]
    );
    if ($progress) {
        $diff = $points - (int)$progress['points_earned'];
        DB::execute(
            "UPDATE user_progress SET points_earned=?, status='completed', completed_at=COALESCE(completed_at,CURRENT_TIMESTAMP) WHERE user_id=? AND level_id=?",
            [$points, $user_id, $level_id]
        );
        if ($diff !== 0) User::updateTotalPoints($user_id, $diff);
    } else {
        DB::execute(
            "INSERT INTO user_progress (user_id, level_id, status, points_earned, tries, time_spent_seconds, completed_at) VALUES (?,?,'completed',?,1,0,CURRENT_TIMESTAMP)",
            [$user_id, $level_id, $points]
        );
        User::updateTotalPoints($user_id, $points);
    }
    $message = "Points overridden to $points for user #$user_id on level #$level_id.";
}

// ── Pending reviews ───────────────────────────────────────────────────────────
$pending = DB::queryAll(
    "SELECT s.*, u.username, l.title as level_title, l.image_path
     FROM submissions s
     INNER JOIN users u ON s.user_id = u.id
     INNER JOIN levels l ON s.level_id = l.id
     WHERE s.review_status = 'pending'
     ORDER BY s.created_at ASC"
);

// ── All submissions (paginated) ───────────────────────────────────────────────
$tab    = $_GET['tab'] ?? 'pending';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;

$all_submissions = DB::queryAll(
    'SELECT s.*, u.username, l.title as level_title, l.type as level_type
     FROM submissions s
     INNER JOIN users u ON s.user_id = u.id
     INNER JOIN levels l ON s.level_id = l.id
     ORDER BY s.created_at DESC LIMIT ? OFFSET ?',
    [$limit, $offset]
);
$total       = DB::queryOne('SELECT COUNT(*) as c FROM submissions')['c'];
$total_pages = max(1, ceil($total / $limit));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions — Admin</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .tabs { display:flex; gap:0; border-bottom:2px solid #dee2e6; margin-bottom:1.5rem; }
        .tab-link { padding:0.75rem 1.5rem; text-decoration:none; color:#666; font-weight:600;
                    border-bottom:3px solid transparent; margin-bottom:-2px; transition:all 0.2s; }
        .tab-link:hover { color:#333; }
        .tab-link.active { color:#007bff; border-bottom-color:#007bff; }
        .pending-badge { background:#dc3545; color:white; border-radius:10px;
                         padding:0.1rem 0.5rem; font-size:0.75rem; margin-left:0.4rem; }

        /* Review card */
        .review-card {
            background:white; border-radius:10px; padding:1.5rem;
            box-shadow:0 1px 4px rgba(0,0,0,0.1); margin-bottom:1.5rem;
            border-left:4px solid #007bff;
        }
        .review-card-header { display:flex; justify-content:space-between; align-items:flex-start;
                               margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem; }
        .review-card-header h3 { margin:0; font-size:1rem; }
        .review-meta { font-size:0.8rem; color:#888; }
        .review-body { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .review-body.single { grid-template-columns:1fr; }
        .code-block { background:#1e1e2e; color:#cdd6f4; padding:1rem; border-radius:6px;
                      font-family:monospace; font-size:0.82rem; overflow:auto; max-height:300px;
                      white-space:pre-wrap; word-break:break-all; }
        .ref-image { width:100%; border-radius:6px; border:1px solid #ddd; max-height:300px; object-fit:contain; }
        .award-form { display:flex; gap:0.5rem; align-items:center; margin-top:1rem; flex-wrap:wrap; }
        .award-form input[type=number] { width:100px; margin:0; }
        .award-form label { font-weight:600; font-size:0.875rem; }
        .pts-presets { display:flex; gap:0.4rem; margin-top:0.5rem; }
        .pts-presets button { padding:0.25rem 0.6rem; font-size:0.8rem; background:#f0f0f0;
                              border:1px solid #ddd; border-radius:4px; cursor:pointer; }
        .pts-presets button:hover { background:#e0e0e0; }

        /* All submissions table */
        .code-preview { max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
                        font-family:monospace; font-size:0.8rem; color:#555; cursor:help; }
        .override-form { display:flex; gap:0.4rem; align-items:center; }
        .override-form input[type=number] { width:70px; margin:0; }
        .badge-pending  { background:#fff3cd; color:#856404; padding:0.15rem 0.5rem; border-radius:8px; font-size:0.75rem; }
        .badge-reviewed { background:#d4edda; color:#155724; padding:0.15rem 0.5rem; border-radius:8px; font-size:0.75rem; }
        .badge-auto     { background:#e2e3e5; color:#383d41; padding:0.15rem 0.5rem; border-radius:8px; font-size:0.75rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="admin-container">

    <?php if ($message): ?><div class="success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="error"  ><?php echo htmlspecialchars($error);   ?></div><?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <a href="?tab=pending" class="tab-link <?php echo $tab==='pending'?'active':''; ?>">
            🎨 Pending Review
            <?php if (count($pending) > 0): ?>
                <span class="pending-badge"><?php echo count($pending); ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=all" class="tab-link <?php echo $tab==='all'?'active':''; ?>">
            📋 All Submissions
        </a>
    </div>

    <?php if ($tab === 'pending'): ?>
    <!-- ── Pending Review Tab ─────────────────────────────────────────────── -->
    <h2>Page Recreation — Awaiting Review (<?php echo count($pending); ?>)</h2>

    <?php if (empty($pending)): ?>
        <div style="text-align:center;padding:3rem;color:#aaa;">
            <p style="font-size:2rem;">✅</p>
            <p>No submissions pending review.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($pending as $sub): ?>
    <div class="review-card">
        <div class="review-card-header">
            <div>
                <h3>
                    <a href="/profile.php?username=<?php echo urlencode($sub['username']); ?>" target="_blank">
                        <?php echo htmlspecialchars($sub['username']); ?>
                    </a>
                    — <?php echo htmlspecialchars($sub['level_title']); ?>
                </h3>
                <div class="review-meta">
                    Submitted <?php echo date('M d, Y H:i', strtotime($sub['created_at'])); ?>
                    · Attempt #<?php echo $sub['tries']; ?>
                    · Time: <?php $s=(int)$sub['time_spent_seconds']; echo $s>=60?floor($s/60).'m '.($s%60).'s':$s.'s'; ?>
                </div>
            </div>
            <span class="badge-pending">Pending Review</span>
        </div>

        <div class="review-body <?php echo empty($sub['image_path'])?'single':''; ?>">
            <div>
                <div style="font-size:0.75rem;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:0.5rem;">
                    User's Code
                </div>
                <div class="code-block"><?php echo htmlspecialchars($sub['code_submitted']); ?></div>
            </div>
            <?php if (!empty($sub['image_path'])): ?>
            <div>
                <div style="font-size:0.75rem;font-weight:700;color:#888;text-transform:uppercase;margin-bottom:0.5rem;">
                    Reference Image
                </div>
                <img src="<?php echo htmlspecialchars($sub['image_path']); ?>" class="ref-image" alt="Reference">
            </div>
            <?php endif; ?>
        </div>

        <!-- Award points form -->
        <form method="POST">
            <input type="hidden" name="sub_id"   value="<?php echo $sub['id']; ?>">
            <input type="hidden" name="user_id"  value="<?php echo $sub['user_id']; ?>">
            <input type="hidden" name="level_id" value="<?php echo $sub['level_id']; ?>">
            <div class="award-form">
                <label>Award Points:</label>
                <input type="number" name="points" id="pts_<?php echo $sub['id']; ?>"
                       min="0" max="1000" value="1000" required>
                <button type="submit" name="award_points" value="1" class="btn btn-primary">
                    ✅ Award & Mark Complete
                </button>
                <a href="/?id=<?php echo $sub['level_id']; ?>" target="_blank"
                   class="btn btn-secondary" style="font-size:0.875rem;">Preview Level</a>
            </div>
            <div class="pts-presets">
                <span style="font-size:0.75rem;color:#888;align-self:center;">Quick:</span>
                <?php foreach ([1000,800,600,400,200,100] as $p): ?>
                    <button type="button"
                            onclick="document.getElementById('pts_<?php echo $sub['id']; ?>').value=<?php echo $p; ?>">
                        <?php echo $p; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
    <?php endforeach; ?>

    <?php else: ?>
    <!-- ── All Submissions Tab ────────────────────────────────────────────── -->
    <p style="color:#666;margin-bottom:1rem;">Total: <?php echo number_format($total); ?> submissions</p>

    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Level</th>
                <th>Type</th>
                <th>Status</th>
                <th>Review</th>
                <th>Code</th>
                <th>Override Points</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($all_submissions as $sub): ?>
            <tr>
                <td style="white-space:nowrap;font-size:0.8rem;">
                    <?php echo date('M d H:i', strtotime($sub['created_at'])); ?>
                </td>
                <td>
                    <a href="/profile.php?username=<?php echo urlencode($sub['username']); ?>">
                        <?php echo htmlspecialchars($sub['username']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($sub['level_title']); ?></td>
                <td><?php echo $sub['level_type']==='page_recreation'?'🎨 Page':'💻 Code'; ?></td>
                <td><?php echo $sub['passed'] ? '✅ Pass' : '❌ Fail'; ?></td>
                <td>
                    <?php
                    $rs = $sub['review_status'] ?? 'auto';
                    if ($rs === 'pending')  echo '<span class="badge-pending">Pending</span>';
                    elseif ($rs === 'reviewed') echo '<span class="badge-reviewed">Reviewed</span>';
                    else echo '<span class="badge-auto">Auto</span>';
                    ?>
                </td>
                <td>
                    <span class="code-preview" title="<?php echo htmlspecialchars($sub['code_submitted']); ?>">
                        <?php echo htmlspecialchars(substr($sub['code_submitted'], 0, 60)); ?>
                    </span>
                </td>
                <td>
                    <form method="POST" class="override-form">
                        <input type="hidden" name="user_id"  value="<?php echo $sub['user_id']; ?>">
                        <input type="hidden" name="level_id" value="<?php echo $sub['level_id']; ?>">
                        <input type="number" name="points" min="0" max="1000" placeholder="pts" required>
                        <button type="submit" name="override_points" value="1"
                                class="btn btn-small btn-primary">Set</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($all_submissions)): ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;">No submissions yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination" style="margin-top:1.5rem;">
        <?php if ($page > 1): ?>
            <a href="?tab=all&page=<?php echo $page-1; ?>">← Previous</a>
        <?php endif; ?>
        <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?tab=all&page=<?php echo $page+1; ?>">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<script src="/assets/js/admin.js"></script>
</body>
</html>
