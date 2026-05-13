<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/DB.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Level.php';

Auth::init();
Auth::requireAdmin();

$msg   = null;
$error = null;

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Image upload helper
    $upload_image = function($field = 'image') {
        if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
        $ext  = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allowed)) return null;
        $dir  = __DIR__ . '/../assets/uploads/level-images/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = 'level_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        return move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $name)
            ? '/assets/uploads/level-images/' . $name
            : null;
    };

    if ($action === 'create') {
        $title    = trim($_POST['title'] ?? '');
        $lang     = $_POST['language'] ?? '';
        $diff     = $_POST['difficulty'] ?? '';
        $type     = $_POST['type'] ?? '';
        $order    = (int)($_POST['order_index'] ?? 0);
        $desc     = trim($_POST['description'] ?? '');
        $expected = trim($_POST['expected_output'] ?? '');
        $hint     = trim($_POST['hint'] ?? '');

        if (!$title || !$lang || !$diff || !$type || !$desc) {
            $error = 'Title, language, difficulty, type and description are required.';
        } else {
            $image_path = $upload_image();
            try {
                Level::create([
                    'title'           => $title,
                    'language'        => $lang,
                    'difficulty'      => $diff,
                    'order_index'     => $order,
                    'type'            => $type,
                    'description'     => $desc,
                    'expected_output' => $expected ?: null,
                    'image_path'      => $image_path,
                ]);
                $level_id = DB::lastInsertId();
                if ($hint && $level_id) {
                    DB::execute(
                        'INSERT INTO questions (level_id, prompt, hint, expected_output) VALUES (?,?,?,?)',
                        [$level_id, $desc, $hint, $expected]
                    );
                }
                $msg = "Level \"$title\" created.";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), '23505') !== false) {
                    $error = "A " . ucfirst($diff) . " " . ucfirst($lang) . " level with order index $order already exists. Use a different order index.";
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'update') {
        $level_id = (int)($_POST['level_id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $expected = trim($_POST['expected_output'] ?? '');
        $order    = (int)($_POST['order_index'] ?? 0);
        $hint     = trim($_POST['hint'] ?? '');

        if (!$title || !$desc) { $error = 'Title and description are required.'; }
        else {
            $data = [
                'title'           => $title,
                'description'     => $desc,
                'expected_output' => $expected ?: null,
                'order_index'     => $order,
            ];
            $new_image = $upload_image();
            if ($new_image) $data['image_path'] = $new_image;

            try {
                Level::update($level_id, $data);

                if ($hint) {
                    $q = DB::queryOne('SELECT id FROM questions WHERE level_id=? LIMIT 1', [$level_id]);
                    if ($q) {
                        DB::execute('UPDATE questions SET hint=?, expected_output=? WHERE level_id=?',
                            [$hint, $expected, $level_id]);
                    } else {
                        DB::execute('INSERT INTO questions (level_id, prompt, hint, expected_output) VALUES (?,?,?,?)',
                            [$level_id, $desc, $hint, $expected]);
                    }
                }
                $msg = 'Level updated.';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), '23505') !== false) {
                    $error = "Order index $order is already taken for this difficulty and language. Use a different order index.";
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'delete') {
        $level_id = (int)($_POST['level_id'] ?? 0);
        if ($level_id) {
            Level::delete($level_id);
            $msg = 'Level deleted.';
        }
    }
}

// ── Edit mode ─────────────────────────────────────────────────────────────────
$edit_level = null;
$edit_hint  = '';
if (isset($_GET['edit'])) {
    $edit_level = Level::getById((int)$_GET['edit']);
    if ($edit_level) {
        $q = DB::queryOne('SELECT hint FROM questions WHERE level_id=? LIMIT 1', [$edit_level['id']]);
        $edit_hint = $q['hint'] ?? '';
    }
}

// ── Filter & load ─────────────────────────────────────────────────────────────
$filter_lang = $_GET['lang'] ?? '';
$filter_diff = $_GET['diff'] ?? '';
$filter_q    = trim($_GET['q'] ?? '');

$sql    = 'SELECT * FROM levels WHERE 1=1';
$params = [];
if ($filter_lang) { $sql .= ' AND language=?';   $params[] = $filter_lang; }
if ($filter_diff) { $sql .= ' AND difficulty=?';  $params[] = $filter_diff; }
if ($filter_q)    { $sql .= ' AND title ILIKE ?'; $params[] = "%$filter_q%"; }
$sql .= ' ORDER BY CASE difficulty WHEN \'beginner\' THEN 1 WHEN \'intermediate\' THEN 2 WHEN \'advanced\' THEN 3 WHEN \'senior\' THEN 4 END, order_index';
$levels = DB::queryAll($sql, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Levels — Admin</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .level-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
        .level-form-grid .full { grid-column:1/-1; }
        .filter-bar { display:flex; gap:0.5rem; margin-bottom:1.5rem; flex-wrap:wrap; align-items:center; }
        .filter-bar input, .filter-bar select { margin:0; width:auto; flex:1; min-width:120px; }
        .thumb { max-height:50px; border-radius:4px; border:1px solid #ddd; vertical-align:middle; }
        .diff-badge { padding:0.15rem 0.5rem; border-radius:8px; font-size:0.7rem; font-weight:700; text-transform:uppercase; }
        .diff-beginner    { background:#d4edda; color:#155724; }
        .diff-intermediate{ background:#fff3cd; color:#856404; }
        .diff-advanced    { background:#fde8e8; color:#c0392b; }
        .diff-senior      { background:#e8d5f5; color:#6c3483; }
        .lang-js  { background:#fff3cd; color:#856404; }
        .lang-php { background:#d1ecf1; color:#0c5460; }
        .lang-badge { padding:0.15rem 0.5rem; border-radius:8px; font-size:0.7rem; font-weight:700; }
    </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="admin-container">

    <?php if ($msg):  ?><div class="success"><?php echo htmlspecialchars($msg);   ?></div><?php endif; ?>
    <?php if ($error):?><div class="error"  ><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <!-- Create / Edit form -->
    <div class="form-section">
        <h2><?php echo $edit_level ? '✏️ Edit: '.htmlspecialchars($edit_level['title']) : '➕ Create New Level'; ?></h2>
        <form method="POST" enctype="multipart/form-data"
              action="/admin/levels.php">
            <input type="hidden" name="action" value="<?php echo $edit_level ? 'update' : 'create'; ?>">
            <?php if ($edit_level): ?>
                <input type="hidden" name="level_id" value="<?php echo $edit_level['id']; ?>">
            <?php endif; ?>

            <div class="level-form-grid">
                <div class="full">
                    <label>Title *</label>
                    <input type="text" name="title" required
                           value="<?php echo htmlspecialchars($edit_level['title'] ?? ''); ?>">
                </div>

                <?php if (!$edit_level): ?>
                <div>
                    <label>Language *</label>
                    <select name="language" required>
                        <option value="">— select —</option>
                        <option value="javascript">JavaScript</option>
                        <option value="php">PHP</option>
                    </select>
                </div>
                <div>
                    <label>Difficulty *</label>
                    <select name="difficulty" required>
                        <option value="">— select —</option>
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                        <option value="senior">Senior</option>
                    </select>
                </div>
                <div>
                    <label>Type *</label>
                    <select name="type" required>
                        <option value="">— select —</option>
                        <option value="code_challenge">Code Challenge</option>
                        <option value="page_recreation">Page Recreation</option>
                    </select>
                </div>
                <?php else: ?>
                <div>
                    <label>Language</label>
                    <input type="text" value="<?php echo ucfirst($edit_level['language']); ?>" disabled>
                    <input type="hidden" name="language" value="<?php echo $edit_level['language']; ?>">
                </div>
                <div>
                    <label>Difficulty</label>
                    <input type="text" value="<?php echo ucfirst($edit_level['difficulty']); ?>" disabled>
                    <input type="hidden" name="difficulty" value="<?php echo $edit_level['difficulty']; ?>">
                </div>
                <div>
                    <label>Type</label>
                    <input type="text" value="<?php echo $edit_level['type'] === 'code_challenge' ? 'Code Challenge' : 'Page Recreation'; ?>" disabled>
                    <input type="hidden" name="type" value="<?php echo $edit_level['type']; ?>">
                </div>
                <?php endif; ?>

                <div>
                    <label>Order Index *</label>
                    <input type="number" name="order_index" min="1" required
                           value="<?php echo $edit_level['order_index'] ?? 1; ?>">
                </div>

                <div class="full">
                    <label>Description / Prompt *</label>
                    <textarea name="description" rows="3" required><?php echo htmlspecialchars($edit_level['description'] ?? ''); ?></textarea>
                </div>

                <div class="full">
                    <label>Expected Output <small style="color:#888">(exact string the code must produce — case-insensitive match)</small></label>
                    <textarea name="expected_output" rows="2"
                              placeholder="e.g. Hello, World!"><?php echo htmlspecialchars($edit_level['expected_output'] ?? ''); ?></textarea>
                </div>

                <div class="full">
                    <label>Hint <small style="color:#888">(shown when user clicks Hint — costs 100 pts)</small></label>
                    <input type="text" name="hint"
                           value="<?php echo htmlspecialchars($edit_hint); ?>"
                           placeholder="e.g. Use console.log() to print text">
                </div>

                <div class="full">
                    <label>Reference Image <small style="color:#888">(for page recreation levels)</small></label>
                    <?php if (!empty($edit_level['image_path'])): ?>
                        <div style="margin-bottom:0.5rem;">
                            <img src="<?php echo htmlspecialchars($edit_level['image_path']); ?>"
                                 style="max-height:80px;border-radius:4px;border:1px solid #ddd;">
                            <small style="color:#888;display:block;margin-top:0.25rem;">Current image — upload a new one to replace</small>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                </div>

                <div class="full" style="display:flex;gap:1rem;">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $edit_level ? '💾 Save Changes' : '➕ Create Level'; ?>
                    </button>
                    <?php if ($edit_level): ?>
                        <a href="/admin/levels.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Filter bar -->
    <form method="GET" class="filter-bar">
        <input type="text" name="q" placeholder="Search title…"
               value="<?php echo htmlspecialchars($filter_q); ?>">
        <select name="lang">
            <option value="">All Languages</option>
            <option value="javascript" <?php echo $filter_lang==='javascript'?'selected':''; ?>>JavaScript</option>
            <option value="php"        <?php echo $filter_lang==='php'?'selected':''; ?>>PHP</option>
        </select>
        <select name="diff">
            <option value="">All Difficulties</option>
            <option value="beginner"     <?php echo $filter_diff==='beginner'?'selected':''; ?>>Beginner</option>
            <option value="intermediate" <?php echo $filter_diff==='intermediate'?'selected':''; ?>>Intermediate</option>
            <option value="advanced"     <?php echo $filter_diff==='advanced'?'selected':''; ?>>Advanced</option>
            <option value="senior"       <?php echo $filter_diff==='senior'?'selected':''; ?>>Senior</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($filter_lang || $filter_diff || $filter_q): ?>
            <a href="/admin/levels.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>

    <h2>Levels (<?php echo count($levels); ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Lang</th>
                <th>Difficulty</th>
                <th>Type</th>
                <th>Order</th>
                <th>Expected Output</th>
                <th>Image</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($levels as $lvl): ?>
            <tr>
                <td><?php echo $lvl['id']; ?></td>
                <td><strong><?php echo htmlspecialchars($lvl['title']); ?></strong></td>
                <td>
                    <span class="lang-badge <?php echo $lvl['language']==='javascript'?'lang-js':'lang-php'; ?>">
                        <?php echo $lvl['language']==='javascript'?'JS':'PHP'; ?>
                    </span>
                </td>
                <td>
                    <span class="diff-badge diff-<?php echo $lvl['difficulty']; ?>">
                        <?php echo ucfirst($lvl['difficulty']); ?>
                    </span>
                </td>
                <td><?php echo $lvl['type']==='code_challenge'?'💻 Code':'🎨 Page'; ?></td>
                <td><?php echo $lvl['order_index']; ?></td>
                <td style="font-family:monospace;font-size:0.8rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?php echo htmlspecialchars($lvl['expected_output'] ?? ''); ?>">
                    <?php echo htmlspecialchars($lvl['expected_output'] ?? '—'); ?>
                </td>
                <td>
                    <?php if (!empty($lvl['image_path'])): ?>
                        <img src="<?php echo htmlspecialchars($lvl['image_path']); ?>" class="thumb">
                    <?php else: ?>
                        <span style="color:#ccc">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                        <a href="/?id=<?php echo $lvl['id']; ?>" target="_blank"
                           class="btn btn-small" style="background:#17a2b8;color:white">Preview</a>
                        <a href="?edit=<?php echo $lvl['id']; ?>"
                           class="btn btn-secondary btn-small">Edit</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action"   value="delete">
                            <input type="hidden" name="level_id" value="<?php echo $lvl['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-small"
                                    onclick="return confirm('Delete &quot;<?php echo htmlspecialchars($lvl['title']); ?>&quot;? All user progress for this level will also be deleted.')">
                                Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($levels)): ?>
            <tr><td colspan="9" style="text-align:center;padding:2rem;">No levels found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="/assets/js/admin.js"></script>
</body>
</html>
