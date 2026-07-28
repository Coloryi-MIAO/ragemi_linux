<?php
// 确保用户已登录且是管理员
if (!isset($me) || !isAdmin($me)) {
    return;
}

// 获取待审核数量（用于显示角标）
$pendingDevelopers = 0;
$pendingOauth = 0;
$pendingBots = 0;
if (isset($pdo)) {
    $s = $pdo->query("SELECT COUNT(*) FROM developer_applications WHERE status = 'pending'");
    $pendingDevelopers = $s->fetchColumn();
    $s = $pdo->query("SELECT COUNT(*) FROM oauth_apps WHERE status = 'pending'");
    $pendingOauth = $s->fetchColumn();
    $s = $pdo->query("SELECT COUNT(*) FROM bots WHERE status = 'pending'");
    $pendingBots = $s->fetchColumn();
}
$totalPending = $pendingDevelopers + $pendingOauth + $pendingBots;
?>
<!-- ===== 左侧边栏（后台专用） ===== -->
<nav class="sidebar" id="sidebar">
    <div class="nav-section">
        <a href="/admin" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" data-page="admin">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="nav-label">仪表盘</span>
        </a>
        <a href="/admin/users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>" data-page="users">
            <span class="material-symbols-outlined">people</span>
            <span class="nav-label">用户管理</span>
        </a>
        <a href="/admin/posts.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'posts.php' ? 'active' : ''; ?>" data-page="posts">
            <span class="material-symbols-outlined">article</span>
            <span class="nav-label">帖子管理</span>
        </a>
        <a href="/admin/oauth.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'oauth.php' ? 'active' : ''; ?>" data-page="oauth">
            <span class="material-symbols-outlined">vpn_key</span>
            <span class="nav-label">OAuth 应用</span>
        </a>
        <a href="/admin/bots.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'bots.php' ? 'active' : ''; ?>" data-page="bots">
            <span class="material-symbols-outlined">smart_toy</span>
            <span class="nav-label">Bot 管理</span>
        </a>
        <a href="/admin/review.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'review.php' ? 'active' : ''; ?>" data-page="review">
            <span class="material-symbols-outlined">fact_check</span>
            <span class="nav-label">审核队列</span>
            <?php if ($totalPending > 0): ?>
                <span class="badge" style="background:#e74c3c;color:#fff;border-radius:12px;padding:0 6px;font-size:11px;margin-left:auto;"><?php echo $totalPending; ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="sidebar-footer">
        <hr class="sidebar-divider">
        <a href="/settings" class="nav-item" data-page="settings">
            <span class="material-symbols-outlined">settings</span>
            <span class="nav-label">设置</span>
        </a>
        <a href="/logout" class="nav-item" onclick="if(!confirm('确认退出登录？'))return false;" style="color:var(--ba-text-muted);">
            <span class="material-symbols-outlined">logout</span>
            <span class="nav-label">退出</span>
        </a>
    </div>
</nav>