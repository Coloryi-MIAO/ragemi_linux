<?php
// admin/posts.php - 帖子管理
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();

if (!$me || !isAdmin($me)) {
    header('Location: /login');
    exit;
}

$error = '';
$success = '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT p.*, u.username, u.display_name, u.subdomain FROM posts p JOIN users u ON p.user_id = u.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND p.content LIKE ?";
    $params[] = "%$search%";
}
if ($statusFilter) {
    $sql .= " AND p.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$s = $pdo->prepare($sql);
$s->execute($params);
$posts = $s->fetchAll();

// 总数
$countSql = "SELECT COUNT(*) FROM posts p WHERE 1=1";
$countParams = [];
if ($search) { $countSql .= " AND p.content LIKE ?"; $countParams[] = "%$search%"; }
if ($statusFilter) { $countSql .= " AND p.status = ?"; $countParams[] = $statusFilter; }
$s = $pdo->prepare($countSql);
$s->execute($countParams);
$total = $s->fetchColumn();
$totalPages = ceil($total / $limit);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('非法请求');
    $action = $_POST['action'] ?? '';
    $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
    if ($postId <= 0) { $error = '无效的帖子'; }
    else {
        switch ($action) {
            case 'hide': if (moderatePost($me['id'], $postId, 'hide')) $success = '帖子已隐藏'; else $error = '操作失败'; break;
            case 'unhide': if (moderatePost($me['id'], $postId, 'unhide')) $success = '帖子已恢复'; else $error = '操作失败'; break;
            case 'delete': if (moderatePost($me['id'], $postId, 'delete')) $success = '帖子已删除'; else $error = '操作失败'; break;
            default: $error = '未知操作';
        }
    }
}

$title = '帖子管理 - 瑞格米';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/admin/css/admin.css">
    <style>
        .admin-table .post-content {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .admin-table .status-badge {
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .status-badge.normal { background: rgba(39,174,96,0.1); color: #27ae60; }
        .status-badge.hidden { background: rgba(243,156,18,0.1); color: #f39c12; }
        .status-badge.deleted { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .action-btn.hide { background: rgba(243,156,18,0.1); color: #f39c12; }
        .action-btn.hide:hover { background: rgba(243,156,18,0.2); }
        .action-btn.unhide { background: rgba(39,174,96,0.1); color: #27ae60; }
        .action-btn.unhide:hover { background: rgba(39,174,96,0.2); }
        .action-btn.delete { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .action-btn.delete:hover { background: rgba(231,76,60,0.2); }
        .filter-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-bar select {
            padding: 8px 14px;
            border: 2px solid rgba(150,170,190,0.25);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: rgba(255,255,255,0.5);
            color: var(--ba-text);
            outline: none;
            cursor: pointer;
        }
        .filter-bar select:focus { border-color: var(--ba-accent); }
        @media (max-width: 768px) {
            .admin-table .post-content { max-width: 120px; }
            .admin-table th, .admin-table td { padding: 6px 6px; font-size: 12px; }
        }
    </style>
</head>
<body>
<div class="particle-bg" id="particleBg"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<header id="app-bar">
    <div style="display:flex;align-items:center;gap:10px;">
        <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()" aria-label="菜单">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <a href="/" class="app-title-link">
            <img src="https://ragemi.com/s/top.png" alt="瑞格米" class="app-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
            <div class="app-title" style="display:none;">瑞格米</div>
        </a>
    </div>
    <div class="top-bar-right">
        <a href="/" class="btn-text"><span class="material-symbols-outlined">home</span> 首页</a>
        <button class="icon-btn" onclick="location.href='/@<?php echo e($me['subdomain']); ?>'">
            <img src="<?php echo getAvatarUrl($me['avatar']); ?>" class="avatar-small" onerror="this.src='/assets/default-avatar.png'">
        </button>
    </div>
</header>

<nav class="sidebar" id="sidebar">
    <div class="nav-section">
        <a href="/" class="nav-item" data-page="home"><span class="material-symbols-outlined">home</span><span class="nav-label">主页</span></a>
        <a href="/explore" class="nav-item" data-page="explore"><span class="material-symbols-outlined">explore</span><span class="nav-label">发现</span></a>
        <a href="/admin" class="nav-item" data-page="admin" style="color:var(--ba-accent-dark);"><span class="material-symbols-outlined">admin_panel_settings</span><span class="nav-label">管理后台</span></a>
        <a href="/admin/users.php" class="nav-item" data-page="users" style="color:var(--ba-accent-dark);"><span class="material-symbols-outlined">people</span><span class="nav-label">用户管理</span></a>
        <a href="/admin/posts.php" class="nav-item active" data-page="posts" style="color:var(--ba-accent-dark);"><span class="material-symbols-outlined">article</span><span class="nav-label">帖子管理</span></a>
        <a href="/admin/oauth.php" class="nav-item" data-page="oauth" style="color:var(--ba-accent-dark);"><span class="material-symbols-outlined">vpn_key</span><span class="nav-label">OAuth 应用</span></a>
        <a href="/admin/bots.php" class="nav-item" data-page="bots" style="color:var(--ba-accent-dark);"><span class="material-symbols-outlined">smart_toy</span><span class="nav-label">Bot 管理</span></a>
    </div>
    <div class="sidebar-footer">
        <hr class="sidebar-divider">
        <a href="/settings" class="nav-item" data-page="settings"><span class="material-symbols-outlined">settings</span><span class="nav-label">设置</span></a>
        <a href="/logout" class="nav-item" onclick="if(!confirm('确认退出登录？'))return false;" style="color:var(--ba-text-muted);"><span class="material-symbols-outlined">logout</span><span class="nav-label">退出</span></a>
    </div>
</nav>

<div class="main-wrapper">
    <main class="main-content">
        <div class="admin-header">
            <h1>帖子管理</h1>
            <p class="admin-sub">管理所有帖子</p>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:16px;">
            <form method="get" style="display:flex;flex:1;gap:10px;">
                <input type="text" name="search" placeholder="搜索帖子内容..." value="<?php echo e($search); ?>" style="flex:1;padding:8px 14px;border:2px solid rgba(150,170,190,0.25);border-radius:8px;font-size:14px;font-family:inherit;background:rgba(255,255,255,0.5);color:var(--ba-text);outline:none;">
                <button type="submit" style="padding:8px 20px;border-radius:8px;border:none;background:var(--ba-accent);color:#fff;font-size:14px;font-weight:500;cursor:pointer;font-family:inherit;">搜索</button>
                <?php if ($search || $statusFilter): ?>
                    <a href="/admin/posts.php" class="btn-text" style="align-self:center;">清除</a>
                <?php endif; ?>
            </form>
            <form method="get" class="filter-bar">
                <select name="status" onchange="this.form.submit()">
                    <option value="">全部状态</option>
                    <option value="normal" <?php echo $statusFilter === 'normal' ? 'selected' : ''; ?>>正常</option>
                    <option value="hidden" <?php echo $statusFilter === 'hidden' ? 'selected' : ''; ?>>已隐藏</option>
                    <option value="deleted" <?php echo $statusFilter === 'deleted' ? 'selected' : ''; ?>>已删除</option>
                </select>
                <?php if ($search): ?>
                    <input type="hidden" name="search" value="<?php echo e($search); ?>">
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>帖子</th><th>作者</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php if ($posts): foreach ($posts as $post): ?>
                        <tr>
                            <td class="post-content"><a href="/post/<?php echo $post['id']; ?>" target="_blank" style="color:var(--ba-accent);"><?php echo e(mb_substr(strip_tags($post['content']), 0, 50)); ?></a></td>
                            <td><a href="/@<?php echo e($post['subdomain']); ?>" target="_blank" style="color:var(--ba-accent);"><?php echo e($post['display_name'] ?: $post['username']); ?></a></td>
                            <td><span class="status-badge <?php echo $post['status']; ?>"><?php echo $post['status']; ?></span></td>
                            <td style="font-size:12px;color:var(--ba-text-muted);"><?php echo timeAgo($post['created_at']); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <?php if ($post['status'] === 'normal'): ?>
                                        <button type="submit" name="action" value="hide" class="action-btn hide">隐藏</button>
                                    <?php elseif ($post['status'] === 'hidden'): ?>
                                        <button type="submit" name="action" value="unhide" class="action-btn unhide">恢复</button>
                                    <?php endif; ?>
                                    <?php if ($post['status'] !== 'deleted'): ?>
                                        <button type="submit" name="action" value="delete" class="action-btn delete" onclick="return confirm('确认删除此帖子？')">删除</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--ba-text-muted);">暂无帖子</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?><span class="current"><?php echo $i; ?></span>
                    <?php else: ?><a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $statusFilter ? '&status=' . urlencode($statusFilter) : ''; ?>"><?php echo $i; ?></a><?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <div class="ragemi-footer">
            <div class="footer-logo"><img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img"></div>
            <div class="footer-copyright">© <?php echo date('Y'); ?> 瑞格米 · 管理后台</div>
        </div>
    </main>
</div>

<script nonce="<?php echo getCSPNonce(); ?>">
(function() {
    var container = document.getElementById('particleBg'); if (!container) return;
    for (var i = 0; i < 20; i++) {
        var particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.width = (Math.random() * 4 + 2) + 'px';
        particle.style.height = particle.style.width;
        particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
        particle.style.animationDelay = (Math.random() * 20) + 's';
        particle.style.opacity = Math.random() * 0.15 + 0.05;
        container.appendChild(particle);
    }
})();
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}
document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
        var sidebar = document.getElementById('sidebar');
        var toggle = document.getElementById('menuToggle');
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            if (sidebar.classList.contains('open')) { toggleSidebar(); }
        }
    }
});
window.toggleSidebar = toggleSidebar;
</script>
</body>
</html>