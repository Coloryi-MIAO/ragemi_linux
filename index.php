<?php
// index.php - 瑞格米首页
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();
$sub = getSubdomain();

if ($sub !== 'www' && $sub !== 'ragemi') {
    header('Location: /@' . $sub);
    exit;
}

// 发帖处理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me && isset($_POST['content'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('非法请求');
    $content = trim($_POST['content']);
    $images = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if (isset($_FILES['images']['error'][$i]) && $_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $file = ['name' => $_FILES['images']['name'][$i], 'tmp_name' => $tmp, 'size' => $_FILES['images']['size'][$i], 'error' => $_FILES['images']['error'][$i]];
                $result = uploadImage($file, IMAGE_DIR);
                if (isset($result['success'])) $images[] = $result['filename'];
            }
        }
    }
    if ($content !== '' || !empty($images)) {
        createPost($me['id'], $content, !empty($images) ? $images : null);
    }
    header('Location: /');
    exit;
}

$page = max(1, intval($_GET['p'] ?? 1));
$posts = getPosts(null, $page, PAGE_SIZE, $me['id'] ?? null);

foreach ($posts as &$post) {
    $post['top_replies'] = getTopReplies($post['id'], 2, $me['id'] ?? null);
}

$stmt = $pdo->query("SELECT COUNT(*) FROM posts WHERE status='normal' AND parent_id IS NULL");
$hasNext = ($page * PAGE_SIZE) < $stmt->fetchColumn();

$recommendUsers = $me ? getPersonalizedRecommendations($me['id'], 3) : [];
$trendingHashtags = getTrendingHashtags(8);
$unreadMessages = $me ? getUnreadMessageCount($me['id']) : 0;
$stats = getSiteStats();
$lastPostId = !empty($posts) ? $posts[0]['id'] : 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>瑞格米 · 二次元同好分享社区</title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    
    <!-- ===== SEO ===== -->
    <meta name="description" content="瑞格米是一个二次元同好分享社区，用户可以在这里发布动漫、游戏、绘画、COS等日常动态，与同好互动交流。">
    <meta name="keywords" content="二次元,动漫,游戏,绘画,COS,分享社区,瑞格米">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo SITE_URL; ?>">
    
    <!-- ===== Open Graph ===== -->
    <meta property="og:title" content="瑞格米 · 二次元同好分享社区" />
    <meta property="og:description" content="瑞格米是一个二次元同好分享社区，用户可以在这里发布动漫、游戏、绘画、COS等日常动态，与同好互动交流。" />
    <meta property="og:image" content="<?php echo SEO_OG_IMAGE; ?>" />
    <meta property="og:url" content="<?php echo SITE_URL; ?>" />
    <meta property="og:type" content="website" />
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="瑞格米 · 二次元同好分享社区">
    <meta name="twitter:description" content="瑞格米是一个二次元同好分享社区，用户可以在这里发布动漫、游戏、绘画、COS等日常动态，与同好互动交流。">
    <meta name="twitter:image" content="<?php echo SEO_OG_IMAGE; ?>">
    
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/home.css">
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

<nav class="sidebar" id="sidebar">
    <div class="nav-section">
        <a href="/" class="nav-item active" data-page="home">
            <span class="material-symbols-outlined">home</span>
            <span class="nav-label">主页</span>
        </a>
        <a href="/explore" class="nav-item" data-page="explore">
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
        <!-- 开发者平台（所有用户可见，但内部有权限控制） -->
        <a href="/openplatform" class="nav-item" data-page="openplatform">
            <span class="material-symbols-outlined">developer_mode</span>
            <span class="nav-label">开发者平台</span>
        </a>
    </div>
    <div class="sidebar-footer">
        <hr class="sidebar-divider">
        <!-- ===== 新增：ICP备案链接（放在设置上面） ===== -->
        <a href="https://icp.gov.moe/?keyword=20260493" target="_blank" rel="noopener" class="nav-item" data-page="icp">
            <span class="material-symbols-outlined">verified</span>
            <span class="nav-label">萌ICP备20260493号</span>
        </a>
        <!-- ===== 原有设置 ===== -->
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
        
        <!-- ===== 品牌横幅（OAuth 验证用） ===== -->
        <div style="text-align:center;padding:20px 16px;background:var(--ba-card-bg);border-radius:var(--ba-radius-lg);margin-bottom:20px;border:1px solid var(--ba-card-border);">
            <h1 style="font-size:24px;font-family:'Playfair Display',serif;color:var(--ba-accent);margin:0;">瑞格米</h1>
            <p style="color:var(--ba-text-secondary);margin:4px 0 0;font-size:14px;">二次元同好分享社区 · 动漫 · 游戏 · 绘画 · COS</p>
        </div>
        
        <!-- 搜索框 -->
        <div class="search-container">
            <form class="search-form" action="/search" method="get" onsubmit="return handleSearch(event)">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text" name="q" id="searchInput" placeholder="搜索话题、用户或内容..." autocomplete="off">
                <button type="submit" class="search-btn"><span>搜索</span></button>
            </form>
        </div>

        <!-- 问候横幅 -->
        <?php if ($me): ?>
        <div class="greeting-banner">
            <span class="greeting-icon"><?php 
                $hour = date('H');
                if ($hour >= 5 && $hour < 12) echo '🌅';
                elseif ($hour >= 12 && $hour < 18) echo '☀️';
                elseif ($hour >= 18 && $hour < 22) echo '🌇';
                else echo '🌙';
            ?></span>
            <div class="greeting-text">
                <div class="time-greeting">
                    <?php 
                        $hour = date('H');
                        if ($hour >= 5 && $hour < 12) echo '早上好';
                        elseif ($hour >= 12 && $hour < 18) echo '下午好';
                        elseif ($hour >= 18 && $hour < 22) echo '晚上好';
                        else echo '夜深了';
                    ?>，<?php echo e($me['display_name'] ?: $me['username']); ?>
                </div>
                <div class="sub-greeting">
                    <?php 
                        $greetings = [
                            '今天想分享什么二次元内容？',
                            '动漫、游戏、COS、绘画...都在这里 ✨',
                            '二次元同好聚集地 🌍',
                            '分享你的二次元日常 🌸',
                            '你的二次元故事值得被听见 📖'
                        ];
                        echo $greetings[array_rand($greetings)];
                    ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 发帖区域 -->
        <?php if ($me): ?>
        <div class="composer-card" id="composer-card">
            <div class="composer-header">
                <img src="<?php echo getAvatarUrl($me['avatar']); ?>" class="post-avatar" onerror="this.src='/assets/default-avatar.png'">
                <span class="composer-label">有什么新鲜事？</span>
            </div>
            <form method="post" enctype="multipart/form-data" id="postForm">
                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                <textarea name="content" id="composerContent" placeholder="说点什么..." rows="2" maxlength="2000"></textarea>
                <div id="imagePreviewArea" class="image-preview-area"></div>
                <div id="uploadProgressContainer">
                    <div class="progress-info">
                        <span id="uploadProgressText">上传中...</span>
                        <span id="uploadProgressPercent">0%</span>
                    </div>
                    <div class="progress-track">
                        <div id="uploadProgressBar" class="progress-bar"></div>
                    </div>
                </div>
                <div class="composer-toolbar">
                    <div class="left-actions">
                        <button type="button" class="icon-btn" id="btnAddImage" title="添加图片">
                            <span class="material-symbols-outlined">add_photo_alternate</span>
                        </button>
                        <input type="file" id="composerImagesInput" accept="image/*" multiple style="display:none">
                        <span id="imageCount" style="font-size:13px;color:var(--ba-text-muted);"></span>
                    </div>
                    <div class="right-actions">
                        <span class="char-count" id="charCount">0 / 2000</span>
                        <button type="submit" class="submit-btn" id="composerSubmit">发布</button>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="composer-card" style="text-align:center;padding:20px;color:var(--ba-text-muted);">
            <span style="font-size:14px;">登录后可以发布动态</span>
            <a href="/login" class="btn-primary" style="display:inline-flex;margin-top:8px;">立即登录</a>
        </div>
        <?php endif; ?>

        <!-- 动态列表 -->
        <div class="section-title">最新动态</div>
        <div id="post-feed">
            <?php if ($posts): ?>
                <?php foreach ($posts as $post): ?>
                    <?php echo renderPostCard($post, $me); ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="end-indicator">还没有人发声，来做第一个吧</div>
            <?php endif; ?>
        </div>
        <div id="feed-status">
            <?php if ($hasNext): ?>
                <div class="loading-indicator" id="load-more-btn">
                    <button class="btn-text" onclick="loadMore()">加载更多</button>
                </div>
            <?php elseif (!empty($posts)): ?>
                <div class="end-indicator">— 没有更多了 —</div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="ragemi-footer">
            <div class="footer-logo">
                <img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img">
            </div>
            <div class="footer-links">
                <a href="/privacy">隐私政策</a>
                <span class="sep">|</span>
                <a href="/terms">服务条款</a>
            </div>
            <div class="footer-copyright">© <?php echo date('Y'); ?> 瑞格米 · 二次元同好分享社区</div>
        </div>
    </main>
</div>

<!-- ===== 通知模态框 ===== -->
<div class="modal-overlay" id="notif-overlay">
    <div class="modal">
        <div class="modal-headline">通知</div>
        <div class="modal-content"><div id="notif-list" class="notif-list"></div></div>
        <div class="modal-actions">
            <button class="btn-text" id="notif-read-all">全部已读</button>
            <button class="btn-text" id="notif-close">关闭</button>
        </div>
    </div>
</div>

<!-- ===== 图片查看器 ===== -->
<div id="imageViewerOverlay" onclick="closeImageViewer()">
    <img id="imageViewerImg" src="" alt="">
</div>

<script nonce="<?php echo getCSPNonce(); ?>">
// ===== 粒子背景生成 =====
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

// ===== 全局状态 =====
var state = {
    user: <?php echo json_encode($me); ?>,
    posts: <?php echo json_encode($posts); ?>,
    page: <?php echo $page; ?>,
    hasMore: <?php echo $hasNext ? 'true' : 'false'; ?>,
    loading: false,
    currentPage: 'home',
    selectedImages: [],
};

// ===== DOM 引用 =====
var $ = function(sel) { return document.querySelector(sel); };
var $$ = function(sel) { return document.querySelectorAll(sel); };

var dom = {
    main: $('#main-content'),
    btnLogin: $('#btn-login'),
    navAuth: $('#nav-auth'),
    navUser: $('#nav-user'),
    navProfile: $('#nav-profile'),
    navMessages: $('#nav-messages'),
    userAvatarImg: $('#user-avatar-img'),
    btnNotifications: $('#btn-notifications'),
    notifBadge: $('#notif-badge'),
    notifOverlay: $('#notif-overlay'),
    notifList: $('#notif-list'),
    notifReadAll: $('#notif-read-all'),
    notifClose: $('#notif-close'),
    msgBadge: $('#msg-badge'),
    imageViewerOverlay: $('#imageViewerOverlay'),
    imageViewerImg: $('#imageViewerImg'),
    composerContent: $('#composerContent'),
    composerSubmit: $('#composerSubmit'),
    postForm: $('#postForm'),
    btnAddImage: $('#btnAddImage'),
    composerImagesInput: $('#composerImagesInput'),
    imagePreviewArea: $('#imagePreviewArea'),
    imageCount: $('#imageCount'),
    charCount: $('#charCount'),
    uploadProgressContainer: $('#uploadProgressContainer'),
    uploadProgressBar: $('#uploadProgressBar'),
    uploadProgressText: $('#uploadProgressText'),
    uploadProgressPercent: $('#uploadProgressPercent'),
};

// ===== 工具函数 =====
function getAvatarUrl(avatar) {
    if (!avatar || avatar === '/assets/default-avatar.png') return '/assets/default-avatar.png';
    return '/uploads/avatars/' + avatar;
}

function timeAgo(datetime) {
    var timestamp = new Date(datetime).getTime();
    var diff = Math.floor((Date.now() - timestamp) / 1000);
    if (diff < 60) return '刚刚';
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
    if (diff < 604800) return Math.floor(diff / 86400) + '天前';
    if (diff < 2592000) return Math.floor(diff / 604800) + '周前';
    return new Date(datetime).toLocaleDateString();
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

function dialogOpen(el) {
    if (el) { el.classList.add('open'); document.body.classList.add('dialog-open'); }
}
function dialogClose(el) {
    if (el) { el.classList.remove('open'); document.body.classList.remove('dialog-open'); }
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

// ===== 导航 =====
$$('.nav-item').forEach(function(btn) {
    btn.addEventListener('click', function() { navigateTo(btn.dataset.page); });
});
document.getElementById('btn-user-avatar')?.addEventListener('click', function() { navigateTo('profile'); });

function navigateTo(page) {
    state.currentPage = page;
    $$('.nav-item').forEach(function(b) { b.classList.remove('active'); });
    var activeBtn = document.querySelector('.nav-item[data-page="' + page + '"]');
    if (activeBtn) activeBtn.classList.add('active');
    switch(page) {
        case 'home': location.reload(); break;
        case 'explore': window.location.href = '/explore'; break;
        case 'messages': renderMessages(); break;
        case 'profile': window.location.href = '/settings'; break;
        case 'admin': window.location.href = '/admin'; break;
        default: break;
    }
}

// ===== 加载更多 =====
var loadingMore = false;
var currentPage = <?php echo $page; ?>;

function loadMore() {
    if (loadingMore || !state.hasMore) return;
    loadingMore = true;
    currentPage++;
    var status = document.getElementById('feed-status');
    status.innerHTML = '<div class="loading-indicator"><div class="spinner"></div></div>';
    fetch('/api/timeline?page=' + currentPage)
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function(data) {
            loadingMore = false;
            if (data.code === 200 && data.data.posts && data.data.posts.length > 0) {
                var feed = document.getElementById('post-feed');
                data.data.posts.forEach(function(post) {
                    var html = renderPostCardJS(post);
                    feed.insertAdjacentHTML('beforeend', html);
                });
                state.hasMore = data.data.has_more || false;
                if (state.hasMore) {
                    status.innerHTML = '<div class="loading-indicator" id="load-more-btn"><button class="btn-text" onclick="loadMore()">加载更多</button></div>';
                } else {
                    status.innerHTML = '<div class="end-indicator">— 没有更多了 —</div>';
                }
            } else {
                state.hasMore = false;
                status.innerHTML = '<div class="end-indicator">— 没有更多了 —</div>';
            }
        })
        .catch(function(err) {
            loadingMore = false;
            console.error('加载失败:', err);
            status.innerHTML = '<div class="loading-indicator"><button class="btn-text" onclick="loadMore()">重试</button></div>';
            showToast('加载失败，请重试');
        });
}

// ===== 渲染帖子卡片 (JS) =====
function renderPostCardJS(post) {
    var displayName = post.display_name || post.username;
    var contentHtml = post.content_html || post.content || '';
    var imagesArr = post.images_arr || [];
    var likedByMe = post.liked_by_me === 1 || post.liked_by_me === true;
    var isLong = contentHtml.length > 600;
    var html = '<div class="post-card" data-post-id="' + post.id + '">';
    html += '<div class="post-header">';
    html += '<img src="' + getAvatarUrl(post.avatar) + '" class="post-avatar" onclick="location.href=\'/@' + post.subdomain + '\'" onerror="this.src=\'/assets/default-avatar.png\'">';
    html += '<span class="post-author" onclick="location.href=\'/@' + post.subdomain + '\'">' + displayName + '</span>';
    html += '<span class="post-badge">#' + post.id + '</span>';
    html += '<span class="post-time">' + timeAgo(post.created_at) + '</span>';
    html += '</div>';
    html += '<div class="post-content">';
    if (isLong) {
        html += contentHtml.substring(0, 600) + '...';
        html += '<span class="read-more" onclick="expandPost(this)"> 查看更多 →</span>';
    } else {
        html += contentHtml;
    }
    html += '</div>';
    if (imagesArr.length > 0) {
        var cls = imagesArr.length === 1 ? 'post-images single' : 'post-images';
        html += '<div class="' + cls + '">';
        imagesArr.forEach(function(img) {
            html += '<img src="/uploads/images/' + img + '" alt="图片" loading="lazy" onclick="openImageViewer(this.src)">';
        });
        html += '</div>';
    }
    html += '<div class="post-actions">';
    html += '<button class="action-btn like-btn ' + (likedByMe ? 'liked' : '') + '" onclick="likePost(' + post.id + ', this)">';
    html += '<span class="material-symbols-outlined">' + (likedByMe ? 'favorite' : 'favorite_border') + '</span>';
    html += ' <span class="like-count">' + post.like_count + '</span>';
    html += '</button>';
    html += '<button class="action-btn comment-btn" onclick="location.href=\'/post/' + post.id + '\'">';
    html += '<span class="material-symbols-outlined">chat_bubble_outline</span>';
    html += ' <span class="comment-count">' + post.reply_count + '</span>';
    html += '</button>';
    html += '<button class="action-btn share-btn" onclick="sharePost(' + post.id + ')">';
    html += '<span class="material-symbols-outlined">share</span><span>分享</span>';
    html += '</button>';
    html += '</div>';
    html += '</div>';
    return html;
}

function expandPost(el) {
    var parent = el.closest('.post-content');
    if (parent) {
        parent.innerHTML = parent.innerHTML.replace('...', '');
        el.style.display = 'none';
    }
}

// ============================================================
// ★★★ 点赞（修复版） ★★★
// ============================================================
function likePost(postId, btn) {
    // 检查是否登录
    if (!state.user || !state.user.id) {
        showToast('请先登录');
        return;
    }
    
    var isLiked = btn.classList.contains('liked');
    var span = btn.querySelector('.like-count');
    var count = parseInt(span.textContent) || 0;
    
    // 乐观更新
    if (isLiked) {
        btn.classList.remove('liked');
        span.textContent = count - 1;
        btn.querySelector('.material-symbols-outlined').textContent = 'favorite_border';
    } else {
        btn.classList.add('liked');
        span.textContent = count + 1;
        btn.querySelector('.material-symbols-outlined').textContent = 'favorite';
    }
    
    // 发送请求
    var formData = new URLSearchParams();
    formData.append('post_id', postId);
    
    fetch('/api/like', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString(),
        credentials: 'include'
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(function(data) {
        if (data.code !== 200) {
            // 回滚
            rollbackLike(btn, isLiked, count);
            showToast(data.msg || '操作失败');
        }
    })
    .catch(function(err) {
        console.error('点赞请求失败:', err);
        rollbackLike(btn, isLiked, count);
        showToast('网络错误，请重试');
    });
}

function rollbackLike(btn, wasLiked, originalCount) {
    if (wasLiked) {
        btn.classList.add('liked');
        btn.querySelector('.like-count').textContent = originalCount;
        btn.querySelector('.material-symbols-outlined').textContent = 'favorite';
    } else {
        btn.classList.remove('liked');
        btn.querySelector('.like-count').textContent = originalCount;
        btn.querySelector('.material-symbols-outlined').textContent = 'favorite_border';
    }
}

// ===== 分享 =====
function sharePost(postId) {
    var url = location.origin + '/post/' + postId;
    if (navigator.share) {
        navigator.share({ title: '查看帖子', url: url });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() { showToast('链接已复制！'); });
    } else {
        prompt('复制链接：', url);
    }
}

// ===== 撤回 =====
function recallPost(postId) {
    if (!confirm('确定撤回这条动态吗？')) return;
    var formData = new URLSearchParams();
    formData.append('post_id', postId);
    fetch('/api/post_delete', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString(),
        credentials: 'include'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.code === 200) {
            showToast('已撤回');
            var card = document.querySelector('.post-card[data-post-id="' + postId + '"]');
            if (card) card.remove();
        } else {
            showToast(data.msg || '撤回失败');
        }
    })
    .catch(function(e) { showToast(e.message || '网络错误'); });
}

// ===== 图片查看器 =====
function openImageViewer(src) {
    dom.imageViewerImg.src = src;
    dom.imageViewerOverlay.style.display = 'flex';
    setTimeout(function() { dom.imageViewerOverlay.classList.add('open'); }, 10);
    document.body.style.overflow = 'hidden';
}
function closeImageViewer() {
    dom.imageViewerOverlay.classList.remove('open');
    setTimeout(function() {
        dom.imageViewerOverlay.style.display = 'none';
        document.body.style.overflow = '';
    }, 300);
}

// ===== 发帖功能 =====
dom.composerContent.addEventListener('input', function() {
    var len = this.value.length;
    var max = 2000;
    dom.charCount.textContent = len + ' / ' + max;
    dom.charCount.className = 'char-count' + (len > max * 0.9 ? ' warning' : '') + (len >= max ? ' danger' : '');
});
dom.btnAddImage.addEventListener('click', function(e) {
    e.preventDefault();
    dom.composerImagesInput.click();
});
dom.composerImagesInput.addEventListener('change', function() {
    var remain = 9 - (state.selectedImages || []).length;
    if (remain <= 0) { showToast('最多选择 9 张图片'); this.value = ''; return; }
    var files = Array.from(this.files).slice(0, remain);
    state.selectedImages = (state.selectedImages || []).concat(files);
    var countEl = dom.imageCount;
    countEl.textContent = state.selectedImages.length > 0 ? state.selectedImages.length + ' 张图片' : '';
    var startIdx = state.selectedImages.length - files.length;
    files.forEach(function(f, i) {
        var url = URL.createObjectURL(f);
        var idx = startIdx + i;
        var div = document.createElement('div');
        div.className = 'preview-item';
        div.dataset.idx = idx;
        div.innerHTML = '<img src="' + url + '" alt="图片"><button class="remove-btn" type="button">×</button>';
        div.querySelector('.remove-btn').addEventListener('click', function(e) {
            e.stopPropagation();
            var idx2 = parseInt(div.dataset.idx);
            state.selectedImages.splice(idx2, 1);
            div.remove();
            var countEl2 = dom.imageCount;
            countEl2.textContent = state.selectedImages.length > 0 ? state.selectedImages.length + ' 张图片' : '';
            document.querySelectorAll('.preview-item').forEach(function(el, i) { el.dataset.idx = i; });
        });
        dom.imagePreviewArea.appendChild(div);
    });
    this.value = '';
});
dom.composerContent.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        submitPostWithProgress();
    }
});
dom.postForm.addEventListener('submit', function(e) {
    e.preventDefault();
    submitPostWithProgress();
});

function submitPostWithProgress() {
    if (!state.user) { showToast('请先登录'); return; }
    var content = dom.composerContent.value.trim();
    if (!content && state.selectedImages.length === 0) { showToast('请输入内容或添加图片'); dom.composerContent.focus(); return; }
    if (content.length > 2000) { showToast('内容不能超过2000字'); return; }
    var formData = new FormData();
    formData.append('content', content);
    state.selectedImages.forEach(function(f) { formData.append('images[]', f); });
    formData.append('csrf', document.querySelector('input[name="csrf"]').value);
    dom.composerSubmit.textContent = '发布中...';
    dom.composerSubmit.disabled = true;
    dom.uploadProgressContainer.style.display = 'block';
    dom.uploadProgressBar.style.width = '0%';
    dom.uploadProgressPercent.textContent = '0%';
    dom.uploadProgressText.textContent = '准备上传...';
    var xhr = new XMLHttpRequest();
    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            dom.uploadProgressBar.style.width = percent + '%';
            dom.uploadProgressPercent.textContent = percent + '%';
            if (percent < 30) dom.uploadProgressText.textContent = '上传图片中...';
            else if (percent < 70) dom.uploadProgressText.textContent = '上传中，请稍候...';
            else dom.uploadProgressText.textContent = '即将完成...';
        }
    });
    xhr.addEventListener('load', function() {
        dom.uploadProgressBar.style.width = '100%';
        dom.uploadProgressPercent.textContent = '100%';
        dom.uploadProgressText.textContent = '处理中...';
        try {
            var data = JSON.parse(xhr.responseText);
            if (data.code === 200) {
                dom.composerContent.value = '';
                state.selectedImages = [];
                dom.imagePreviewArea.innerHTML = '';
                dom.imageCount.textContent = '';
                dom.charCount.textContent = '0 / 2000';
                dom.charCount.className = 'char-count';
                showToast('发布成功');
                setTimeout(function() { dom.uploadProgressContainer.style.display = 'none'; }, 500);
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showToast(data.msg || '发布失败');
                dom.composerSubmit.textContent = '发布';
                dom.composerSubmit.disabled = false;
                setTimeout(function() { dom.uploadProgressContainer.style.display = 'none'; }, 1500);
            }
        } catch (e) {
            showToast('服务器响应异常，请重试');
            dom.composerSubmit.textContent = '发布';
            dom.composerSubmit.disabled = false;
            setTimeout(function() { dom.uploadProgressContainer.style.display = 'none'; }, 1500);
        }
    });
    xhr.addEventListener('error', function() {
        showToast('网络错误，请重试');
        dom.composerSubmit.textContent = '发布';
        dom.composerSubmit.disabled = false;
        setTimeout(function() { dom.uploadProgressContainer.style.display = 'none'; }, 1500);
    });
    xhr.addEventListener('timeout', function() {
        showToast('上传超时，请重试');
        dom.composerSubmit.textContent = '发布';
        dom.composerSubmit.disabled = false;
        setTimeout(function() { dom.uploadProgressContainer.style.display = 'none'; }, 1500);
    });
    xhr.open('POST', '/api/post_create');
    xhr.withCredentials = true;
    xhr.timeout = 60000;
    xhr.send(formData);
}

// ===== 私信页 =====
function renderMessages() {
    dom.main.innerHTML = `
        <div class="section-title">私信</div>
        <div style="text-align:center;padding:40px 0;color:var(--ba-text-muted)">
            <span class="material-symbols-outlined" style="font-size:48px;display:block;margin-bottom:12px">chat</span>
            <p>私信功能开发中...</p>
        </div>
    `;
}

// ===== 退出登录 =====
function logout() {
    fetch('/api/logout', { method: 'POST', credentials: 'include' })
        .then(function() {})
        .catch(function() {});
    localStorage.removeItem('ragemi-token');
    document.cookie = 'ragemi-token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + location.hostname;
    location.reload();
}

// ===== 通知 =====
document.getElementById('btn-notifications')?.addEventListener('click', function() {
    if (!state.user) { showToast('请先登录'); return; }
    dialogOpen(dom.notifOverlay);
    dom.notifList.innerHTML = '<div class="loading-indicator"><div class="spinner"></div></div>';
    fetch('/api/notifications', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.code === 200 && data.data.list && data.data.list.length > 0) {
                dom.notifList.innerHTML = data.data.list.map(function(n) {
                    var text = n.type === 'like' ? '赞了你的动态' : n.type === 'comment' ? '评论了你的动态' : '回复了你的评论';
                    return '<div class="notif-item ' + (n.is_read ? '' : 'unread') + '">' +
                        '<div class="notif-text"><strong>' + (n.from_display || n.from_username) + '</strong> ' + text + '</div>' +
                        '<div class="notif-time">' + timeAgo(n.created_at) + '</div></div>';
                }).join('');
            } else {
                dom.notifList.innerHTML = '<div class="notif-empty">暂无通知</div>';
            }
        })
        .catch(function() {
            dom.notifList.innerHTML = '<div class="notif-empty">加载失败</div>';
        });
});
document.getElementById('notif-read-all')?.addEventListener('click', function() {
    fetch('/api/notifications_read', { method: 'PUT', credentials: 'include' })
        .then(function() { showToast('已全部标记已读'); })
        .catch(function() {});
});
document.getElementById('notif-close')?.addEventListener('click', function() { dialogClose(dom.notifOverlay); });

// ============================================================
// ★★★ 双击帖子跳转到看帖页（未登录也可跳转） ★★★
// ============================================================
document.addEventListener('dblclick', function(e) {
    var card = e.target.closest('.post-card');
    if (card) {
        var postId = card.dataset.postId;
        if (postId) {
            window.location.href = '/post/' + postId;
        }
    }
});

// ===== 永久登录 =====
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
                        document.getElementById('nav-auth').style.display = 'none';
                        document.getElementById('nav-user').style.display = 'flex';
                        document.getElementById('nav-profile').style.display = '';
                        document.getElementById('nav-messages').style.display = '';
                        document.getElementById('user-avatar-img').src = getAvatarUrl(state.user.avatar);
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

// ============================================================
// ★★★ 全局暴露函数（确保所有事件可用） ★★★
// ============================================================
window.state = state;
window.likePost = likePost;
window.sharePost = sharePost;
window.recallPost = recallPost;
window.loadMore = loadMore;
window.openImageViewer = openImageViewer;
window.closeImageViewer = closeImageViewer;
window.expandPost = expandPost;
window.navigateTo = navigateTo;
window.renderMessages = renderMessages;
window.logout = logout;
window.getAvatarUrl = getAvatarUrl;
window.timeAgo = timeAgo;
window.renderPostCardJS = renderPostCardJS;
window.dialogOpen = dialogOpen;
window.dialogClose = dialogClose;
window.dom = dom;
window.showToast = showToast;
window.toggleSidebar = toggleSidebar;
window.handleSearch = handleSearch;
</script>

<!-- ===== 新增：BASpark-Core 粒子特效 ===== -->
<script src="https://cdn.jsdelivr.net/gh/LGCR837/BASpark-Core@main/baspark-core.js"></script>

</body>
</html>