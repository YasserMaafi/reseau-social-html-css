<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/User.php';

Auth::init();
Auth::requireAdmin();

$msg   = null;
$error = null;

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    // Prevent acting on yourself
    $self = Auth::getCurrentUserId();

    switch ($action) {

        case 'create':
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = in_array($_POST['role'] ?? '', ['user','admin']) ? $_POST['role'] : 'user';
            if (!$username || !$email || strlen($password) < 8) {
                $error = 'Username, email and password (min 8 chars) are required.';
            } else {
                $exists = DB::queryOne('SELECT id FROM users WHERE username=? OR email=?', [$username, $email]);
                if ($exists) {
                    $error = 'Username or email already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    DB::execute(
                        'INSERT INTO users (username, email, password_hash, role) VALUES (?,?,?,?)',
                        [$username, $email, $hash, $role]
                    );
                    $msg = "User \"$username\" created.";
                }
            }
            break;

        case 'edit':
            if ($user_id) {
                $username = trim($_POST['username'] ?? '');
                $email    = trim($_POST['email'] ?? '');
                $role     = in_array($_POST['role'] ?? '', ['user','admin']) ? $_POST['role'] : 'user';
                if (!$username || !$email) { $error = 'Username and email are required.'; break; }
                DB::execute(
                    'UPDATE users SET username=?, email=?, role=?, updated_at=CURRENT_TIMESTAMP WHERE id=?',
                    [$username, $email, $role, $user_id]
                );
                // Optional password change
                $new_pass = $_POST['new_password'] ?? '';
                if (strlen($new_pass) >= 8) {
                    $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                    DB::execute('UPDATE users SET password_hash=? WHERE id=?', [$hash, $user_id]);
                }
                $msg = 'User updated.';
            }
            break;

        case 'ban':
            if ($user_id && $user_id !== $self) { User::ban($user_id); $msg = 'User banned.'; }
            break;

        case 'unban':
            if ($user_id) { User::unban($user_id); $msg = 'User unbanned.'; }
            break;

        case 'change_role':
            $role = $_POST['role'] ?? '';
            if ($user_id && $user_id !== $self && in_array($role, ['user','admin'])) {
                User::changeRole($user_id, $role);
                $msg = 'Role updated.';
            }
            break;

        case 'reset_progress':
            if ($user_id) {
                DB::execute('DELETE FROM user_progress WHERE user_id=?', [$user_id]);
                DB::execute('DELETE FROM user_achievements WHERE user_id=?', [$user_id]);
                DB::execute('UPDATE users SET total_points=0 WHERE id=?', [$user_id]);
                $msg = 'Progress reset.';
            }
            break;

        case 'delete':
            if ($user_id && $user_id !== $self) {
                DB::execute('DELETE FROM users WHERE id=?', [$user_id]);
                $msg = 'User deleted.';
            } else {
                $error = 'Cannot delete yourself.';
            }
            break;
    }
}

// ── Edit mode ─────────────────────────────────────────────────────────────────
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_user = DB::queryOne('SELECT * FROM users WHERE id=?', [(int)$_GET['edit']]);
}

// ── Load users ────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
if ($search) {
    $users = DB::queryAll(
        "SELECT * FROM users WHERE username ILIKE ? OR email ILIKE ? ORDER BY created_at DESC",
        ["%$search%", "%$search%"]
    );
} else {
    $users = DB::queryAll('SELECT * FROM users ORDER BY created_at DESC');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — Admin</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .user-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .user-form-grid .full { grid-column:1/-1; }
        .search-bar { display:flex; gap:0.5rem; margin-bottom:1.5rem; }
        .search-bar input { flex:1; margin:0; }
        .badge-role { padding:0.2rem 0.6rem; border-radius:10px; font-size:0.75rem; font-weight:600; }
        .badge-admin { background:#fde8e8; color:#c0392b; }
        .badge-user  { background:#e8f4fd; color:#2980b9; }
        .badge-banned { background:#f5f5f5; color:#999; }
        .action-btns { display:flex; gap:0.4rem; flex-wrap:wrap; }
    </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="admin-container">

    <?php if ($msg):  ?><div class="success"><?php echo htmlspecialchars($msg);   ?></div><?php endif; ?>
    <?php if ($error):?><div class="error"  ><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Create / Edit form -->
    <div class="form-section">
        <h2><?php echo $edit_user ? '✏️ Edit User #'.$edit_user['id'] : '➕ Create New User'; ?></h2>
        <form method="POST" action="/admin/users.php">
            <input type="hidden" name="action"  value="<?php echo $edit_user ? 'edit' : 'create'; ?>">
            <?php if ($edit_user): ?>
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
            <?php endif; ?>

            <div class="user-form-grid">
                <div>
                    <label>Username</label>
                    <input type="text" name="username" required
                           value="<?php echo htmlspecialchars($edit_user['username'] ?? ''); ?>">
                </div>
                <div>
                    <label>Email</label>
                    <input type="email" name="email" required
                           value="<?php echo htmlspecialchars($edit_user['email'] ?? ''); ?>">
                </div>
                <div>
                    <label><?php echo $edit_user ? 'New Password (leave blank to keep)' : 'Password (min 8 chars)'; ?></label>
                    <input type="password" name="<?php echo $edit_user ? 'new_password' : 'password'; ?>"
                           <?php echo $edit_user ? '' : 'required'; ?> placeholder="••••••••">
                </div>
                <div>
                    <label>Role</label>
                    <select name="role">
                        <option value="user"  <?php echo ($edit_user['role'] ?? 'user') === 'user'  ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo ($edit_user['role'] ?? '')      === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="full" style="display:flex;gap:1rem;">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_user ? 'Save Changes' : 'Create User'; ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="/admin/users.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Search -->
    <form method="GET" class="search-bar">
        <input type="text" name="q" placeholder="Search by username or email…"
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search): ?>
            <a href="/admin/users.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <h2>All Users (<?php echo count($users); ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Points</th>
                <th>Joined</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr <?php echo $u['is_banned'] ? 'style="opacity:0.6"' : ''; ?>>
                <td><?php echo $u['id']; ?></td>
                <td>
                    <a href="/profile.php?username=<?php echo urlencode($u['username']); ?>" target="_blank">
                        <?php echo htmlspecialchars($u['username']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($u['email']); ?></td>
                <td>
                    <span class="badge-role badge-<?php echo $u['role']; ?>">
                        <?php echo ucfirst($u['role']); ?>
                    </span>
                </td>
                <td><?php echo number_format($u['total_points']); ?></td>
                <td style="font-size:0.8rem;color:#888;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                <td><?php echo $u['is_banned'] ? '<span class="badge-role badge-banned">Banned</span>' : '✅ Active'; ?></td>
                <td>
                    <div class="action-btns">
                        <!-- Edit -->
                        <a href="?edit=<?php echo $u['id']; ?>" class="btn btn-secondary btn-small">Edit</a>

                        <!-- Ban / Unban -->
                        <?php if ($u['id'] != Auth::getCurrentUserId()): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <?php if ($u['is_banned']): ?>
                                <button name="action" value="unban" class="btn btn-small" style="background:#28a745;color:white">Unban</button>
                            <?php else: ?>
                                <button name="action" value="ban" class="btn btn-small btn-danger"
                                        onclick="return confirm('Ban <?php echo htmlspecialchars($u['username']); ?>?')">Ban</button>
                            <?php endif; ?>
                        </form>

                        <!-- Role toggle -->
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <input type="hidden" name="action"  value="change_role">
                            <input type="hidden" name="role"    value="<?php echo $u['role']==='admin'?'user':'admin'; ?>">
                            <button type="submit" class="btn btn-small"
                                    style="background:#6c757d;color:white"
                                    onclick="return confirm('Change role to <?php echo $u['role']==='admin'?'user':'admin'; ?>?')">
                                → <?php echo $u['role']==='admin'?'User':'Admin'; ?>
                            </button>
                        </form>

                        <!-- Reset progress -->
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button name="action" value="reset_progress" class="btn btn-small btn-danger"
                                    onclick="return confirm('Reset all progress for <?php echo htmlspecialchars($u['username']); ?>?')">
                                Reset
                            </button>
                        </form>

                        <!-- Delete -->
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <button name="action" value="delete" class="btn btn-small btn-danger"
                                    onclick="return confirm('Permanently delete <?php echo htmlspecialchars($u['username']); ?>? This cannot be undone.')">
                                Delete
                            </button>
                        </form>
                        <?php else: ?>
                            <span style="font-size:0.75rem;color:#999">(you)</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="/assets/js/admin.js"></script>
</body>
</html>
