<?php
// admin/index.php - 管理后台首页
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();
if (!$me || !isAdmin($me)) {
    header('Location: /login');
    exit;
}

// 统计
$stats = getSiteStats();

// 待审核数量
$s = $pdo->query("SELECT COUNT(*) FROM developer_applications WHERE status = 'pending'");
$pendingDevelopers = $s->fetchColumn();

$s = $pdo->query("SELECT COUNT(*) FROM oauth_apps WHERE status = 'pending'");
$pendingOauth = $s->fetchColumn();

$s = $pdo->query("SELECT COUNT(*) FROM bots WHERE status = 'pending'");
$pendingBots = $s->fetchColumn();

$s = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'hidden'");
$hiddenPosts = $s->fetchColumn();

$title = '管理后台 - 瑞格米';
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 16px; margin: 20px 0; }
        .stat-card { background: var(--ba-card-bg); border: 1px solid var(--ba-card-border); border-radius: 12px; padding: 16px; text-align: center; }
        .stat-number { font-size: 28px; font-weight: 700; color: var(--ba-accent); }
        .stat-label { font-size: 13px; color: var(--ba-text-muted); }
        .stat-card .badge { background: #e74c3c; color: #fff; border-radius: 12px; padding: 0 8px; font-size: 12px; margin-left: 4px; }
        .quick-links { display: flex; flex-wrap: wrap; gap: 12px; margin: 20px 0; }
        .quick-link { padding: 10px 20px; background: var(--ba-card-bg); border: 1px solid var(--ba-card-border); border-radius: 8px; text-decoration: none; color: var(--ba-text); transition: all 0.2s; }
        .quick-link:hover { background: var(--ba-accent); color: #fff; border-color: var(--ba-accent); }
    </style>
</head>
<body>
<!-- ===== 粒子背景 ===== -->
<div class="particle-bg" id="particleBg"></div>
<!-- ===== 移动端遮罩 ===== -->
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
        <button class="icon-btn" onclick="location.href='/@<?php echo e($me['subdomain']); ?>'" title="个人空间">
            <img src="<?php echo getAvatarUrl($me['avatar']); ?>" class="avatar-small" onerror="this.src='/assets/default-avatar.png'">
        </button>
    </div>
</header>

<!-- ===== 左侧边栏 ===== -->
<nav class="sidebar" id="sidebar">
    <div class="nav-section">
        <a href="/" class="nav-item" data-page="home">
            <span class="material-symbols-outlined">home</span>
            <span class="nav-label">主页</span>
        </a>
        <a href="/explore" class="nav-item" data-page="explore">
            <span class="material-symbols-outlined">explore</span>
            <span class="nav-label">发现</span>
        </a>
        <a href="/admin" class="nav-item active" data-page="admin" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">admin_panel_settings</span>
            <span class="nav-label">管理后台</span>
        </a>
        <a href="/admin/users.php" class="nav-item" data-page="users" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">people</span>
            <span class="nav-label">用户管理</span>
        </a>
        <a href="/admin/posts.php" class="nav-item" data-page="posts" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">article</span>
            <span class="nav-label">帖子管理</span>
        </a>
        <a href="/admin/oauth.php" class="nav-item" data-page="oauth" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">vpn_key</span>
            <span class="nav-label">OAuth 应用</span>
        </a>
        <a href="/admin/bots.php" class="nav-item" data-page="bots" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">smart_toy</span>
            <span class="nav-label">Bot 管理</span>
        </a>
        <a href="/admin/review.php" class="nav-item" data-page="review" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">fact_check</span>
            <span class="nav-label">审核队列</span>
            <?php if ($pendingDevelopers + $pendingOauth + $pendingBots > 0): ?>
                <span class="badge" style="background:#e74c3c;color:#fff;border-radius:12px;padding:0 6px;font-size:11px;margin-left:auto;"><?php echo $pendingDevelopers + $pendingOauth + $pendingBots; ?></span>
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

<!-- ===== 主内容区 ===== -->
<div class="main-wrapper">
    <main class="main-content">
        <div class="admin-header">
            <h1>管理后台</h1>
            <p class="admin-sub">欢迎回来，<?php echo e($me['display_name'] ?: $me['username']); ?></p>
        </div>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?php echo $stats['users']; ?></div><div class="stat-label">总用户</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['posts']; ?></div><div class="stat-label">总帖子</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $stats['likes']; ?></div><div class="stat-label">总点赞</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $pendingDevelopers; ?></div><div class="stat-label">待审核开发者</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $pendingOauth; ?></div><div class="stat-label">待审核 OAuth 应用</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $pendingBots; ?></div><div class="stat-label">待审核 Bot</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $hiddenPosts; ?></div><div class="stat-label">已隐藏帖子</div></div>
        </div>

        <!-- 快捷操作 -->
        <div style="margin-top:20px;">
            <h2 style="font-size:18px;font-weight:600;margin-bottom:12px;">快捷操作</h2>
            <div class="quick-links">
                <a href="/admin/review.php" class="quick-link">
                    <span class="material-symbols-outlined">fact_check</span> 审核队列
                    <?php if ($pendingDevelopers + $pendingOauth + $pendingBots > 0): ?>
                        <span class="badge" style="background:#e74c3c;color:#fff;border-radius:12px;padding:0 8px;font-size:12px;margin-left:4px;"><?php echo $pendingDevelopers + $pendingOauth + $pendingBots; ?></span>
                    <?php endif; ?>
                </a>
                <a href="/admin/users.php" class="quick-link"><span class="material-symbols-outlined">people</span> 用户管理</a>
                <a href="/admin/posts.php" class="quick-link"><span class="material-symbols-outlined">article</span> 帖子管理</a>
                <a href="/admin/oauth.php" class="quick-link"><span class="material-symbols-outlined">vpn_key</span> OAuth 应用</a>
                <a href="/admin/bots.php" class="quick-link"><span class="material-symbols-outlined">smart_toy</span> Bot 管理</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="ragemi-footer">
            <div class="footer-logo"><img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img"></div>
            <div class="footer-copyright">© <?php echo date('Y'); ?> 瑞格米 · 管理后台</div>
        </div>
    </main>
</div>

<script nonce="<?php echo getCSPNonce(); ?>">
// ===== 粒子背景 =====
(function(){var c=document.getElementById('particleBg');if(!c)return;for(var i=0;i<20;i++){var p=document.createElement('div');p.className='particle';p.style.left=Math.random()*100+'%';p.style.width=(Math.random()*4+2)+'px';p.style.height=p.style.width;p.style.animationDuration=(Math.random()*20+15)+'s';p.style.animationDelay=(Math.random()*20)+'s';p.style.opacity=Math.random()*0.15+0.05;c.appendChild(p);}})();
function toggleSidebar(){var s=document.getElementById('sidebar'),o=document.getElementById('sidebarOverlay');s.classList.toggle('open');o.classList.toggle('active');}
document.addEventListener('click',function(e){if(window.innerWidth<=768){var s=document.getElementById('sidebar'),t=document.getElementById('menuToggle');if(!s.contains(e.target)&&!t.contains(e.target)){if(s.classList.contains('open')){toggleSidebar();}}}});
window.toggleSidebar=toggleSidebar;
</script>
</body>
</html>