<?php
// Admin shared navbar — include at top of every admin page body
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="admin-navbar">
    <div class="nav-container">
        <a href="/admin" style="color:white;text-decoration:none;font-size:1.1rem;font-weight:700;">🔐 Admin</a>
        <div class="nav-menu">
            <a href="/admin" <?php echo $current==='index.php'?'style="background:rgba(255,255,255,0.15)"':''; ?>>Dashboard</a>
            <a href="/admin/levels.php" <?php echo $current==='levels.php'?'style="background:rgba(255,255,255,0.15)"':''; ?>>Levels</a>
            <a href="/admin/users.php" <?php echo $current==='users.php'?'style="background:rgba(255,255,255,0.15)"':''; ?>>Users</a>
            <a href="/admin/submissions.php" <?php echo $current==='submissions.php'?'style="background:rgba(255,255,255,0.15)"':''; ?>>Submissions</a>
            <a href="/" style="opacity:0.7;">← Site</a>
            <button id="logoutBtn">Logout</button>
        </div>
    </div>
</nav>
