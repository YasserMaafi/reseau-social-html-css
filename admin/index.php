<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::init();
Auth::requireAdmin();

$total_users = DB::queryOne('SELECT COUNT(*) as count FROM users')['count'];
$active_today = DB::queryOne(
    "SELECT COUNT(*) as count FROM submissions WHERE created_at >= CURRENT_TIMESTAMP - INTERVAL '1 day'"
)['count'];
$most_failed = DB::queryOne(
    "SELECT l.title, COUNT(*) as failed_count FROM submissions s
     INNER JOIN levels l ON s.level_id = l.id
     WHERE s.passed = FALSE
     GROUP BY l.id, l.title
     ORDER BY failed_count DESC LIMIT 1"
);
$top_scorer = DB::queryOne(
    "SELECT username, total_points FROM users ORDER BY total_points DESC LIMIT 1"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Learning Platform</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

    <div class="admin-container">
        <h1>Dashboard</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <p class="stat-value"><?php echo $total_users; ?></p>
            </div>
            <div class="stat-card">
                <h3>Active Today</h3>
                <p class="stat-value"><?php echo $active_today; ?></p>
            </div>
            <div class="stat-card">
                <h3>Most Failed Level</h3>
                <p class="stat-value"><?php echo $most_failed['title'] ?? 'N/A'; ?></p>
                <p class="stat-desc"><?php echo $most_failed['failed_count'] ?? 0; ?> failures</p>
            </div>
            <div class="stat-card">
                <h3>Top Scorer</h3>
                <p class="stat-value"><?php echo htmlspecialchars($top_scorer['username'] ?? 'N/A'); ?></p>
                <p class="stat-desc"><?php echo $top_scorer['total_points'] ?? 0; ?> points</p>
            </div>
        </div>

        <div class="admin-section">
            <h2>Recent Submissions</h2>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Level</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $recent = DB::queryAll(
                        "SELECT s.*, u.username, l.title FROM submissions s
                         INNER JOIN users u ON s.user_id = u.id
                         INNER JOIN levels l ON s.level_id = l.id
                         ORDER BY s.created_at DESC LIMIT 10"
                    );
                    foreach ($recent as $sub):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sub['username']); ?></td>
                            <td><?php echo htmlspecialchars($sub['title']); ?></td>
                            <td><?php echo $sub['passed'] ? '✅ Passed' : '❌ Failed'; ?></td>
                            <td><?php echo date('M d, H:i', strtotime($sub['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="/assets/js/admin.js"></script>
</body>
</html>
