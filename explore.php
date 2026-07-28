<?php
// explore.php - 发现页
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();

// 获取热门话题
$trendingHashtags = getTrendingHashtags(12);

// 推荐用户
$s = $pdo->prepare("
    SELECT u.id, u.username, u.display_name, u.subdomain, u.avatar, u.bio,
    (SELECT COUNT(*) FROM follows WHERE followee_id = u.id) as follower_count,
    (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND status='normal') as post_count
    FROM users u
    WHERE u.status = 'active'
    ORDER BY RAND()
    LIMIT 8
");
$s->execute();
$recommendUsers = $s->fetchAll();

$stats = getSiteStats();
$title = '发现 - 瑞格米';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="description" content="<?php echo SEO_DESCRIPTION; ?>">
    <meta name="keywords" content="<?php echo SEO_KEYWORDS; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo SITE_URL; ?>/explore">
    <meta property="og:title" content="发现 - 瑞格米">
    <meta property="og:description" content="<?php echo SEO_DESCRIPTION; ?>">
    <meta property="og:image" content="<?php echo SEO_OG_IMAGE; ?>">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/explore">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/explore.css">
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
        <div class="nav-auth" id="nav-auth">
            <a href="/login" class="btn-primary" id="btn-login">
                <span class="material-symbols-outlined">login</span> 登录
            </a>
        </div>
        <div class="nav-user" id="nav-user" style="display:none">
            <button class="icon-btn" id="btn-notifications" style="position:relative">
                <span class="material-symbols-outlined">notifications</span>
                <span class="notif-badge" id="notif-badge" style="display:none"></span>
            </button>
            <button class="icon-btn" id="btn-user-avatar">
                <img id="user-avatar-img" src="/assets/default-avatar.png" alt="" class="avatar-small">
            </button>
        </div>
    </div>
</header>

<!-- ===== 左侧边栏 ===== -->
<nav class="sidebar" id="sidebar">
    <div class="nav-section">
        <a href="/" class="nav-item" data-page="home">
            <span class="material-symbols-outlined">home</span>
            <span class="nav-label">主页</span>
        </a>
        <a href="/explore" class="nav-item active" data-page="explore">
            <span class="material-symbols-outlined">explore</span>
            <span class="nav-label">发现</span>
        </a>
        <a href="/messages" class="nav-item" data-page="messages" id="nav-messages" style="display:none;">
            <span class="material-symbols-outlined">chat</span>
            <span class="nav-label">私信</span>
            <span class="badge" id="msg-badge" style="display:none;">0</span>
        </a>
        <a href="/settings" class="nav-item" data-page="profile" id="nav-profile" style="display:none;">
            <span class="material-symbols-outlined">person</span>
            <span class="nav-label">我的</span>
        </a>
        <?php if (isAdmin($me)): ?>
        <a href="/admin" class="nav-item" data-page="admin" style="color:var(--ba-accent-dark);">
            <span class="material-symbols-outlined">admin_panel_settings</span>
            <span class="nav-label">管理后台</span>
        </a>
        <?php endif; ?>
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
    <main class="main-content" id="main-content">
        <!-- 搜索框 -->
        <div class="search-container">
            <form class="search-form" action="/search" method="get" onsubmit="return handleSearch(event)">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text" name="q" id="searchInput" placeholder="搜索话题、用户或内容..." autocomplete="off">
                <button type="submit" class="search-btn"><span>搜索</span></button>
            </form>
        </div>

        <!-- 热门话题 -->
        <div class="section-title">热门话题</div>
        <div class="tags-grid">
            <?php if ($trendingHashtags): ?>
                <?php foreach ($trendingHashtags as $tag): ?>
                    <a href="/search?q=#<?php echo urlencode($tag); ?>" class="tag-item">
                        <span class="tag-icon">#</span><?php echo e($tag); ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <span style="color:var(--ba-text-muted);font-size:14px;">暂无热门话题</span>
            <?php endif; ?>
        </div>

        <!-- 推荐用户 -->
        <div class="section-title">推荐用户</div>
        <div class="user-grid">
            <?php if ($recommendUsers): ?>
                <?php foreach ($recommendUsers as $user): ?>
                    <div class="user-card" onclick="location.href='/@<?php echo e($user['subdomain']); ?>'">
                        <img src="<?php echo getAvatarUrl($user['avatar']); ?>" class="user-avatar" onerror="this.src='/assets/default-avatar.png'">
                        <div class="user-name"><?php echo e($user['display_name'] ?: $user['username']); ?></div>
                        <div class="user-handle">@<?php echo e($user['subdomain']); ?></div>
                        <div class="user-bio"><?php echo e($user['bio'] ?: '这个人很懒，什么都没写...'); ?></div>
                        <div class="user-stats">
                            <span>📝 <?php echo isset($user['post_count']) ? $user['post_count'] : 0; ?></span>
                            <span>👥 <?php echo isset($user['follower_count']) ? $user['follower_count'] : 0; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color:var(--ba-text-muted);font-size:14px;grid-column:1/-1;text-align:center;padding:20px;">暂无推荐用户</div>
            <?php endif; ?>
        </div>

        <!-- 关于站点 -->
        <div class="section-title">关于瑞格米</div>
        <div class="stats-card">
            <div class="stat-item">
                <div class="stat-num"><?php echo $stats['users']; ?></div>
                <div class="stat-label">用户</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?php echo $stats['posts']; ?></div>
                <div class="stat-label">帖子</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?php echo $stats['likes']; ?></div>
                <div class="stat-label">点赞</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="ragemi-footer">
            <div class="footer-logo">
                <img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img">
            </div>
            <div class="footer-copyright">© <?php echo date('Y'); ?> 瑞格米 · 二次元帖子分享站</div>
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

// ===== 边栏切换 =====
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

// ===== 搜索 =====
function handleSearch(e) {
    var q = document.getElementById('searchInput').value.trim();
    if (!q) {
        e.preventDefault();
        showToast('请输入搜索关键词');
        return false;
    }
    window.location.href = '/search?q=' + encodeURIComponent(q);
    return false;
}

function showToast(msg) {
    var old = document.querySelector('.error-toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'error-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, 3000);
}

// ===== 全局状态 =====
var state = {
    user: <?php echo json_encode($me); ?>
};

// ===== 恢复登录 =====
function restoreSession() {
    var token = localStorage.getItem('ragemi-token');
    if (token) {
        var expires = new Date();
        expires.setTime(expires.getTime() + 30 * 24 * 60 * 60 * 1000);
        document.cookie = 'ragemi-token=' + token + '; expires=' + expires.toUTCString() + '; path=/; domain=' + location.hostname;
        return token;
    }
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        var parts = cookie.split('=');
        if (parts[0] === 'ragemi-token') {
            var value = parts[1] || '';
            if (value) {
                localStorage.setItem('ragemi-token', value);
                return value;
            }
        }
    }
    return null;
}

document.addEventListener('DOMContentLoaded', function() {
    if (state.user) {
        document.getElementById('nav-auth').style.display = 'none';
        document.getElementById('nav-user').style.display = 'flex';
        document.getElementById('nav-profile').style.display = '';
        document.getElementById('nav-messages').style.display = '';
        document.getElementById('user-avatar-img').src = getAvatarUrl(state.user.avatar);
        return;
    }
    var token = restoreSession();
    if (token) {
        fetch('/api/user_me', { credentials: 'include' })
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function(text) {
                try {
                    var data = JSON.parse(text);
                    if (data.code === 200 && data.data) {
                        state.user = data.data;
                        location.reload();
                    } else {
                        localStorage.removeItem('ragemi-token');
                        document.cookie = 'ragemi-token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + location.hostname;
                    }
                } catch (e) {
                    console.error('解析响应失败:', text);
                    localStorage.removeItem('ragemi-token');
                    document.cookie = 'ragemi-token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + location.hostname;
                }
            })
            .catch(function(err) { console.error('自动登录失败:', err); });
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
});

function getAvatarUrl(avatar) {
    if (!avatar || avatar === '/assets/default-avatar.png') return '/assets/default-avatar.png';
    return '/uploads/avatars/' + avatar;
}

// ===== 全局暴露 =====
window.toggleSidebar = toggleSidebar;
window.handleSearch = handleSearch;
window.showToast = showToast;
window.getAvatarUrl = getAvatarUrl;
</script>
</body>
</html>