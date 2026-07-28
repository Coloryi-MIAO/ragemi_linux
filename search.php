<?php
// search.php - 搜索页面
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();
$query = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$type = $_GET['type'] ?? 'all';

$posts = [];
$users = [];
$hasMore = false;

if (!empty($query)) {
    // 搜索帖子
    if ($type === 'all' || $type === 'posts') {
        $posts = searchPosts($query, $page, 20, $me['id'] ?? null);
        foreach ($posts as &$post) {
            $post['top_replies'] = getTopReplies($post['id'], 2, $me['id'] ?? null);
        }
        $totalPosts = count($posts);
        $hasMore = $totalPosts >= 20;
    }
    
    // 搜索用户
    if ($type === 'all' || $type === 'users') {
        $users = searchUsers($query, 12);
    }
}

$title = '搜索 - 瑞格米';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="robots" content="index, follow">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/search.css">
</head>
<body>
<div class="particle-bg" id="particleBg"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<header id="app-bar">
    <div style="display:flex;align-items:center;gap:10px;">
        <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()" aria-label="菜单"><span class="material-symbols-outlined">menu</span></button>
        <a href="/" class="app-title-link"><img src="https://ragemi.com/s/top.png" alt="瑞格米" class="app-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block';"><div class="app-title" style="display:none;">瑞格米</div></a>
    </div>
    <div class="top-bar-right">
        <div class="nav-auth" id="nav-auth">
            <a href="/login" class="btn-primary" id="btn-login"><span class="material-symbols-outlined">login</span> 登录</a>
        </div>
        <div class="nav-user" id="nav-user" style="display:none">
            <button class="icon-btn" id="btn-notifications" style="position:relative"><span class="material-symbols-outlined">notifications</span><span class="notif-badge" id="notif-badge" style="display:none"></span></button>
            <button class="icon-btn" id="btn-user-avatar"><img id="user-avatar-img" src="/assets/default-avatar.png" alt="" class="avatar-small"></button>
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

<div class="main-wrapper">
    <main class="main-content">
        <!-- 搜索框 -->
        <div class="search-container">
            <form class="search-form" action="/search" method="get" onsubmit="return handleSearch(event)">
                <span class="search-icon material-symbols-outlined">search</span>
                <input type="text" name="q" id="searchInput" placeholder="搜索话题、用户或内容..." value="<?php echo e($query); ?>" autocomplete="off">
                <button type="submit" class="search-btn"><span>搜索</span></button>
            </form>
        </div>

        <?php if (!empty($query)): ?>
            <!-- 搜索类型切换 -->
            <div class="search-tabs">
                <a href="/search?q=<?php echo urlencode($query); ?>&type=all" class="<?php echo $type === 'all' ? 'active' : ''; ?>">全部</a>
                <a href="/search?q=<?php echo urlencode($query); ?>&type=posts" class="<?php echo $type === 'posts' ? 'active' : ''; ?>">帖子</a>
                <a href="/search?q=<?php echo urlencode($query); ?>&type=users" class="<?php echo $type === 'users' ? 'active' : ''; ?>">用户</a>
            </div>
            
            <div class="search-results">
                <!-- 用户搜索结果 -->
                <?php if (($type === 'all' || $type === 'users') && $users): ?>
                    <div class="search-section">
                        <div class="section-title">用户</div>
                        <div class="user-grid">
                            <?php foreach ($users as $user): ?>
                                <div class="user-card" onclick="location.href='/@<?php echo e($user['subdomain']); ?>'">
                                    <img src="<?php echo getAvatarUrl($user['avatar']); ?>" class="user-avatar" onerror="this.src='/assets/default-avatar.png'">
                                    <div class="user-name"><?php echo e($user['display_name'] ?: $user['username']); ?></div>
                                    <div class="user-handle">@<?php echo e($user['subdomain']); ?></div>
                                    <div class="user-bio"><?php echo e($user['bio'] ?: '这个人很懒，什么都没写...'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- 帖子搜索结果 -->
                <?php if (($type === 'all' || $type === 'posts') && $posts): ?>
                    <div class="search-section">
                        <div class="section-title">帖子</div>
                        <div id="post-feed">
                            <?php foreach ($posts as $post): ?>
                                <?php echo renderPostCard($post, $me); ?>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($hasMore): ?>
                            <div class="loading-indicator">
                                <button class="btn-text" onclick="loadMoreSearch('<?php echo e($query); ?>', <?php echo $page + 1; ?>)">加载更多</button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 无结果 -->
                <?php if (empty($posts) && empty($users)): ?>
                    <div class="no-results">
                        <span class="material-symbols-outlined" style="font-size:48px;">search_off</span>
                        <p>没有找到与 "<strong><?php echo e($query); ?></strong>" 相关的内容</p>
                        <p style="font-size:13px;color:var(--ba-text-muted);">尝试使用不同的关键词或检查拼写</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="search-hint">
                <span class="material-symbols-outlined" style="font-size:48px;">search</span>
                <p>输入关键词搜索帖子或用户</p>
            </div>
        <?php endif; ?>

        <div class="ragemi-footer">
            <div class="footer-logo"><img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img"></div>
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

document.addEventListener('click', function(e) {
    if (window.innerWidth <= 768) {
        var sidebar = document.getElementById('sidebar');
        var toggle = document.getElementById('menuToggle');
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            if (sidebar.classList.contains('open')) { toggleSidebar(); }
        }
    }
});

// ===== 搜索 =====
function handleSearch(e) {
    var q = document.getElementById('searchInput').value.trim();
    if (!q) {
        e.preventDefault();
        showToast('请输入搜索关键词');
        return false;
    }
    return true;
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

// ===== 加载更多搜索 =====
var loadingMore = false;

function loadMoreSearch(query, page) {
    if (loadingMore) return;
    loadingMore = true;
    var status = document.querySelector('.loading-indicator');
    status.innerHTML = '<div class="spinner"></div>';
    fetch('/api/search?q=' + encodeURIComponent(query) + '&page=' + page)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            loadingMore = false;
            if (data.code === 200 && data.data.posts && data.data.posts.length > 0) {
                var feed = document.getElementById('post-feed');
                data.data.posts.forEach(function(post) {
                    var html = renderPostCardJS(post);
                    feed.insertAdjacentHTML('beforeend', html);
                });
                if (data.data.has_more) {
                    status.innerHTML = '<button class="btn-text" onclick="loadMoreSearch(\'' + query + '\', ' + (page + 1) + ')">加载更多</button>';
                } else {
                    status.innerHTML = '<div class="end-indicator">— 没有更多了 —</div>';
                }
            } else {
                status.innerHTML = '<div class="end-indicator">— 没有更多了 —</div>';
            }
        })
        .catch(function() {
            loadingMore = false;
            status.innerHTML = '<button class="btn-text" onclick="loadMoreSearch(\'' + query + '\', ' + page + ')">重试</button>';
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

function likePost(postId, btn) {
    if (!state.user) { showToast('请先登录'); return; }
    var isLiked = btn.classList.contains('liked');
    var span = btn.querySelector('.like-count');
    var count = parseInt(span.textContent);
    if (isLiked) {
        btn.classList.remove('liked');
        span.textContent = count - 1;
        btn.querySelector('.material-symbols-outlined').textContent = 'favorite_border';
    } else {
        btn.classList.add('liked');
        span.textContent = count + 1;
        btn.querySelector('.material-symbols-outlined').textContent = 'favorite';
    }
    var formData = new URLSearchParams();
    formData.append('post_id', postId);
    fetch('/api/like', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString(),
        credentials: 'include'
    })
    .then(function(response) { return response.text(); })
    .then(function(text) {
        try {
            var data = JSON.parse(text);
            if (data.code !== 200) {
                rollbackLike(btn, isLiked, count);
                showToast(data.msg || '操作失败');
            }
        } catch (e) {
            console.error('响应解析失败:', text);
            rollbackLike(btn, isLiked, count);
            showToast('服务器响应异常');
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

function openImageViewer(src) {
    var overlay = document.getElementById('imageViewerOverlay');
    if (!overlay) {
        var viewer = document.createElement('div');
        viewer.id = 'imageViewerOverlay';
        viewer.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.85);display:flex;justify-content:center;align-items:center;z-index:99999;cursor:pointer;';
        viewer.onclick = function() { this.remove(); };
        viewer.innerHTML = '<img src="' + src + '" style="max-width:90%;max-height:90%;object-fit:contain;">';
        document.body.appendChild(viewer);
    } else {
        document.getElementById('imageViewerImg').src = src;
        overlay.style.display = 'flex';
        setTimeout(function() { overlay.classList.add('open'); }, 10);
        document.body.style.overflow = 'hidden';
    }
}

// ===== 全局状态 =====
var state = {
    user: <?php echo json_encode($me); ?>
};

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

window.toggleSidebar = toggleSidebar;
window.loadMoreSearch = loadMoreSearch;
window.likePost = likePost;
window.sharePost = sharePost;
window.openImageViewer = openImageViewer;
window.showToast = showToast;
window.getAvatarUrl = getAvatarUrl;
window.timeAgo = timeAgo;
window.renderPostCardJS = renderPostCardJS;
window.expandPost = expandPost;
window.handleSearch = handleSearch;
</script>
</body>
</html>