<?php
// admin/users.php - 用户管理
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();

// 检查管理员权限
if (!$me || !isAdmin($me)) {
    header('Location: /login');
    exit;
}

$error = '';
$success = '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 搜索
$search = trim($_GET['search'] ?? '');
$searchSql = '';
$searchParams = [];

if ($search) {
    $searchSql = " AND (username LIKE ? OR display_name LIKE ? OR email LIKE ?)";
    $searchParams = ["%$search%", "%$search%", "%$search%"];
}

// 获取用户列表
$sql = "SELECT id, username, display_name, subdomain, avatar, role, status, email, created_at FROM users WHERE 1=1 $searchSql ORDER BY id DESC LIMIT ? OFFSET ?";
$params = array_merge($searchParams, [$limit, $offset]);
$s = $pdo->prepare($sql);
$s->execute($params);
$users = $s->fetchAll();

// 获取总数
$countSql = "SELECT COUNT(*) FROM users WHERE 1=1 $searchSql";
$s = $pdo->prepare($countSql);
$s->execute($searchParams);
$total = $s->fetchColumn();
$totalPages = ceil($total / $limit);

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        die('非法请求');
    }
    
    $action = $_POST['action'] ?? '';
    $targetId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($targetId <= 0) {
        $error = '无效的用户';
    } elseif ($targetId == 1) {
        $error = '不能操作超级管理员';
    } elseif ($targetId == $me['id']) {
        $error = '不能操作自己';
    } else {
        switch ($action) {
            case 'ban':
                if (banUser($me['id'], $targetId)) {
                    $success = '用户已封禁';
                } else {
                    $error = '封禁失败';
                }
                break;
            case 'unban':
                if (unbanUser($me['id'], $targetId)) {
                    $success = '用户已解封';
                } else {
                    $error = '解封失败';
                }
                break;
            case 'set_admin':
                if (setUserRole($me['id'], $targetId, 'admin')) {
                    $success = '已设为管理员';
                } else {
                    $error = '操作失败';
                }
                break;
            case 'set_moderator':
                if (setUserRole($me['id'], $targetId, 'moderator')) {
                    $success = '已设为版主';
                } else {
                    $error = '操作失败';
                }
                break;
            case 'set_user':
                if (setUserRole($me['id'], $targetId, 'user')) {
                    $success = '已设为普通用户';
                } else {
                    $error = '操作失败';
                }
                break;
            default:
                $error = '未知操作';
        }
    }
}

$title = '用户管理 - 瑞格米';
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
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .admin-table th {
            text-align: left;
            padding: 10px 12px;
            background: rgba(150,170,190,0.08);
            color: var(--ba-text-secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--ba-card-border);
        }
        .admin-table td {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(150,170,190,0.08);
            vertical-align: middle;
        }
        .admin-table .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            background: #e0e0e0;
        }
        .admin-table .role-badge {
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .role-badge.admin {
            background: rgba(122,92,45,0.15);
            color: var(--ba-accent);
        }
        .role-badge.moderator {
            background: rgba(52,152,219,0.15);
            color: #2980b9;
        }
        .role-badge.user {
            background: rgba(150,170,190,0.15);
            color: var(--ba-text-muted);
        }
        .status-badge.active {
            color: #27ae60;
        }
        .status-badge.banned {
            color: #e74c3c;
        }
        .admin-table .action-btn {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s;
            margin: 2px 4px 2px 0;
        }
        .action-btn.ban {
            background: rgba(231,76,60,0.1);
            color: #e74c3c;
        }
        .action-btn.ban:hover {
            background: rgba(231,76,60,0.2);
        }
        .action-btn.unban {
            background: rgba(39,174,96,0.1);
            color: #27ae60;
        }
        .action-btn.unban:hover {
            background: rgba(39,174,96,0.2);
        }
        .action-btn.promote {
            background: rgba(122,92,45,0.1);
            color: var(--ba-accent);
        }
        .action-btn.promote:hover {
            background: rgba(122,92,45,0.2);
        }
        .admin-table .action-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 14px;
            text-decoration: none;
            border: 1px solid var(--ba-card-border);
            color: var(--ba-text-secondary);
            transition: all 0.2s;
        }
        .pagination a:hover {
            background: var(--ba-accent);
            color: #fff;
            border-color: var(--ba-accent);
            text-decoration: none;
        }
        .pagination .current {
            background: var(--ba-accent);
            color: #fff;
            border-color: var(--ba-accent);
        }
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }
        .search-box input {
            flex: 1;
            padding: 8px 14px;
            border: 2px solid rgba(150,170,190,0.25);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            background: rgba(255,255,255,0.5);
            color: var(--ba-text);
            outline: none;
        }
        .search-box input:focus {
            border-color: var(--ba-accent);
        }
        .search-box button {
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            background: var(--ba-accent);
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
        }
        .search-box button:hover {
            background: var(--ba-accent-dark);
        }
        .admin-table-wrap {
            overflow-x: auto;
        }
        @media (max-width: 768px) {
            .admin-table th, .admin-table td {
                padding: 6px 8px;
                font-size: 12px;
            }
            .search-box {
                flex-wrap: wrap;
            }
            .search-box button {
                flex: 1;
            }
        }
    </style>
</head>
<body>
<!-- ===== 粒子背景 ===== -->
<div class="particle-bg" id="particleBg"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ===== 顶部导航栏 ===== -->
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
        <a href="/" class="btn-text">
            <span class="material-symbols-outlined">home</span> 首页
        </a>
        <button class="icon-btn" onclick="location.href='/@<?php echo e($me['subdomain']); ?>'">
            <img src="<?php echo getAvatarUrl($me['avatar']); ?>" class="avatar-small" onerror="this.src='/assets/default-avatar.png'">
        </button>
    </div>
</header>

<!-- ===== 左侧边栏 ===== -->
<nav class="sidebar" id="sidebar">
    <div class="nav-section">
        <a href="/" class="nav-item" data-page="home">
            <span class="material-symbols-outlined">home</span><span class="nav-label">主页</span>
        </a>
        <a href="/explore" class="nav-item" data-page="explore">
            <span class="material-symbols-outlined">explore</span><span class="nav-label">发现</span>
        </a>
        <a href="/admin" class="nav-item" data-page="admin" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">admin_panel_settings</span><span class="nav-label">管理后台</span>
        </a>
        <a href="/admin/users.php" class="nav-item active" data-page="users" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">people</span><span class="nav-label">用户管理</span>
        </a>
        <a href="/admin/posts.php" class="nav-item" data-page="posts" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">article</span><span class="nav-label">帖子管理</span>
        </a>
        <a href="/admin/oauth.php" class="nav-item" data-page="oauth" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">vpn_key</span><span class="nav-label">OAuth 应用</span>
        </a>
        <a href="/admin/bots.php" class="nav-item" data-page="bots" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">smart_toy</span><span class="nav-label">Bot 管理</span>
        </a>
    </div>
    <div class="sidebar-footer">
        <hr class="sidebar-divider">
        <a href="/settings" class="nav-item" data-page="settings">
            <span class="material-symbols-outlined">settings</span><span class="nav-label">设置</span>
        </a>
        <a href="/logout" class="nav-item" onclick="if(!confirm('确认退出登录？'))return false;" style="color:var(--ba-text-muted);">
            <span class="material-symbols-outlined">logout</span><span class="nav-label">退出</span>
        </a>
    </div>
</nav>

<!-- ===== 主内容区 ===== -->
<div class="main-wrapper">
    <main class="main-content">
        <div class="admin-header">
            <h1>用户管理</h1>
            <p class="admin-sub">管理所有注册用户</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- 搜索 -->
        <div class="search-box">
            <form method="get" style="display:flex;width:100%;gap:10px;">
                <input type="text" name="search" placeholder="搜索用户名、昵称、邮箱..." value="<?php echo e($search); ?>">
                <button type="submit">搜索</button>
                <?php if ($search): ?>
                    <a href="/admin/users.php" class="btn-text" style="align-self:center;">清除</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- 用户列表 -->
        <div class="admin-card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>用户</th>
                            <th>角色</th>
                            <th>状态</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            <img src="<?php echo getAvatarUrl($user['avatar']); ?>" class="user-avatar" onerror="this.src='/assets/default-avatar.png'">
                                            <div>
                                                <div style="font-weight:500;"><?php echo e($user['display_name'] ?: $user['username']); ?></div>
                                                <div style="font-size:12px;color:var(--ba-text-muted);">@<?php echo e($user['subdomain']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge <?php echo $user['role'] ?? 'user'; ?>">
                                            <?php echo $user['role'] ?? 'user'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['status'] === 'banned' ? 'banned' : 'active'; ?>">
                                            <?php echo $user['status'] === 'banned' ? '已封禁' : '正常'; ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:var(--ba-text-muted);">
                                        <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <?php if ($user['status'] === 'banned'): ?>
                                                <button type="submit" name="action" value="unban" class="action-btn unban">解封</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="ban" class="action-btn ban">封禁</button>
                                            <?php endif; ?>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <button type="submit" name="action" value="set_user" class="action-btn promote">降级</button>
                                            <?php elseif ($user['role'] === 'moderator'): ?>
                                                <button type="submit" name="action" value="set_admin" class="action-btn promote">升管理员</button>
                                                <button type="submit" name="action" value="set_user" class="action-btn promote">降级</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="set_admin" class="action-btn promote">设管理员</button>
                                                <button type="submit" name="action" value="set_moderator" class="action-btn promote">设版主</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--ba-text-muted);">暂无用户</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="ragemi-footer">
            <div class="footer-logo">
                <img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img">
            </div>
            <div class="footer-copyright">© <?php echo date('Y'); ?> 瑞格米 · 管理后台</div>
        </div>
    </main>
</div>

<script nonce="<?php echo getCSPNonce(); ?>">
// ===== 粒子背景 =====
(function() {
    var container = document.getElementById('particleBg');
    if (!container) return;
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