<?php
// post.php - 帖子详情页
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();

// 获取帖子ID
$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($postId <= 0) {
    header('Location: /');
    exit;
}

// 获取帖子详情
$post = getPostById($postId, $me['id'] ?? null);
if (!$post) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404</title></head><body style="text-align:center;padding:60px 20px;font-family:sans-serif;color:#4a3f35;background:#f5f0eb;"><h1>帖子不存在</h1><p>该帖子可能已被删除或不存在</p><a href="/" style="color:#7A5C2D;text-decoration:none;border:1px solid #7A5C2D;padding:8px 24px;border-radius:30px;display:inline-block;margin-top:16px;">返回首页</a></body></html>';
    exit;
}

// 获取回复列表
$replies = getPostReplies($postId, 100, $me['id'] ?? null);

// 获取点赞用户
$likeUsers = getPostLikeUsers($postId, 12);

// 判断是否已关注作者
$isFollowing = $me ? getFollowStatus($me['id'], $post['user_id']) === 'following' : false;

$pageTitle = e($post['display_name'] ?: $post['username']) . ' 的帖子 - 瑞格米';
$unreadMessages = $me ? getUnreadMessageCount($me['id']) : 0;
$stats = getSiteStats();

// 为帖子添加 top_replies
$post['top_replies'] = getTopReplies($post['id'], 2, $me['id'] ?? null);

$recommendUsers = $me ? getPersonalizedRecommendations($me['id'], 3) : [];
$trendingHashtags = getTrendingHashtags(8);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="description" content="<?php echo strip_tags(mb_substr($post['content'], 0, 200)); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo SITE_URL; ?>/post/<?php echo $postId; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo strip_tags(mb_substr($post['content'], 0, 200)); ?>">
    <meta property="og:image" content="<?php echo SEO_OG_IMAGE; ?>">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/post/<?php echo $postId; ?>">
    <meta property="og:type" content="article">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo strip_tags(mb_substr($post['content'], 0, 200)); ?>">
    <meta name="twitter:image" content="<?php echo SEO_OG_IMAGE; ?>">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/post.css">
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

        <!-- 返回按钮 -->
        <a href="/" class="back-btn">
            <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
            返回主页
        </a>

        <!-- 主帖卡片 -->
        <div class="post-detail-card" id="postCard">
            <div class="post-author-row">
                <img src="<?php echo getAvatarUrl($post['avatar']); ?>"
                     class="post-author-avatar"
                     onclick="location.href='/@<?php echo e($post['subdomain']); ?>'"
                     onerror="this.src='/assets/default-avatar.png'">
                <div>
                    <div class="post-author-name" onclick="location.href='/@<?php echo e($post['subdomain']); ?>'">
                        <?php echo e($post['display_name'] ?: $post['username']); ?>
                    </div>
                    <div class="post-author-handle">@<?php echo e($post['subdomain']); ?></div>
                </div>
                <span class="post-author-time"><?php echo timeAgo($post['created_at']); ?></span>
                <?php if ($me && $me['id'] != $post['user_id']): ?>
                    <button class="follow-btn <?php echo $isFollowing ? 'following' : ''; ?>"
                            id="followBtn"
                            onclick="toggleFollow(<?php echo $post['user_id']; ?>, this)">
                        <?php echo $isFollowing ? '已关注' : '关注'; ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="post-content">
                <?php echo $post['content_html']; ?>
            </div>

            <?php if (!empty($post['images_arr'])): ?>
                <?php
                    $count = count($post['images_arr']);
                    $cls = 'post-images';
                    if ($count === 1) $cls .= ' single';
                ?>
                <div class="<?php echo $cls; ?>">
                    <?php foreach ($post['images_arr'] as $img): ?>
                        <img src="/uploads/images/<?php echo e($img); ?>"
                             alt="图片"
                             loading="lazy"
                             onclick="openImageViewer(this.src)">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="post-actions">
                <button class="action-btn like-btn <?php echo ($post['liked_by_me'] ?? false) ? 'liked' : ''; ?>"
                        onclick="likePost(<?php echo $post['id']; ?>, this)">
                    <span class="material-symbols-outlined"><?php echo ($post['liked_by_me'] ?? false) ? 'favorite' : 'favorite_border'; ?></span>
                    <span class="like-count"><?php echo $post['like_count']; ?></span>
                </button>
                <button class="action-btn" onclick="document.getElementById('replyInput').focus()">
                    <span class="material-symbols-outlined">chat_bubble_outline</span>
                    <span><?php echo $post['reply_count']; ?></span>
                </button>
                <button class="action-btn" onclick="sharePost(<?php echo $post['id']; ?>)">
                    <span class="material-symbols-outlined">share</span>
                    <span>分享</span>
                </button>
                <?php if ($me && $me['id'] == $post['user_id'] && time() - strtotime($post['created_at']) < 86400): ?>
                    <button class="action-btn" onclick="recallPost(<?php echo $post['id']; ?>)">
                        <span class="material-symbols-outlined">undo</span>
                        <span>撤回</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($likeUsers)): ?>
                <div class="like-avatars">
                    <?php foreach (array_slice($likeUsers, 0, 8) as $user): ?>
                        <img src="<?php echo getAvatarUrl($user['avatar']); ?>"
                             onclick="location.href='/@<?php echo e($user['subdomain']); ?>'"
                             onerror="this.src='/assets/default-avatar.png'"
                             title="<?php echo e($user['display_name'] ?: $user['username']); ?>">
                    <?php endforeach; ?>
                    <?php if (count($likeUsers) > 8): ?>
                        <span class="more-text">+<?php echo count($likeUsers) - 8; ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 回复区域 -->
        <div class="reply-section" id="replySection">
            <div class="section-title">
                <span class="material-symbols-outlined" style="font-size:22px;">chat</span>
                全部回复 · <?php echo $post['reply_count']; ?>
            </div>

            <div id="replyList">
                <?php if ($replies): ?>
                    <?php foreach ($replies as $reply): ?>
                        <div class="reply-card" id="reply-<?php echo $reply['id']; ?>">
                            <div class="reply-header">
                                <img src="<?php echo getAvatarUrl($reply['avatar']); ?>"
                                     class="reply-avatar"
                                     onclick="location.href='/@<?php echo e($reply['subdomain']); ?>'"
                                     onerror="this.src='/assets/default-avatar.png'">
                                <span class="reply-author" onclick="location.href='/@<?php echo e($reply['subdomain']); ?>'">
                                    <?php echo e($reply['display_name'] ?: $reply['username']); ?>
                                </span>
                                <span class="reply-time"><?php echo timeAgo($reply['created_at']); ?></span>
                            </div>
                            <div class="reply-content"><?php echo $reply['content_html']; ?></div>
                            <div class="reply-actions">
                                <button onclick="likePost(<?php echo $reply['id']; ?>, this)"
                                        class="<?php echo ($reply['liked_by_me'] ?? false) ? 'liked' : ''; ?>">
                                    <span class="material-symbols-outlined" style="font-size:16px;">
                                        <?php echo ($reply['liked_by_me'] ?? false) ? 'favorite' : 'favorite_border'; ?>
                                    </span>
                                    <span class="like-count"><?php echo $reply['like_count']; ?></span>
                                </button>
                                <button onclick="replyTo(<?php echo $reply['id']; ?>, '<?php echo e($reply['username']); ?>')">
                                    <span class="material-symbols-outlined" style="font-size:16px;">reply</span>
                                    回复
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-replies">还没有回复，来写下第一条吧</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 发回复框 -->
        <div class="reply-composer">
            <div class="composer-row">
                <img src="<?php echo $me ? getAvatarUrl($me['avatar']) : '/assets/default-avatar.png'; ?>"
                     class="composer-avatar"
                     onerror="this.src='/assets/default-avatar.png'">
                <textarea id="replyInput"
                          placeholder="<?php echo $me ? '写下你的回复...' : '请先登录再回复'; ?>"
                          rows="2"
                          <?php echo $me ? '' : 'disabled'; ?>></textarea>
            </div>
            <div class="composer-actions">
                <span class="reply-hint" id="replyHint"></span>
                <button class="submit-btn" id="replySubmit" <?php echo $me ? '' : 'disabled'; ?>>
                    发送回复
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="ragemi-footer">
            <div class="footer-logo">
                <img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img">
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

// ===== 全局状态 =====
var state = {
    user: <?php echo json_encode($me); ?>,
    page: 1,
    hasMore: false,
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

// ===== 点赞 =====
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

// ===== 关注 =====
function toggleFollow(userId, btn) {
    if (!state.user) { showToast('请先登录'); return; }
    var isFollowing = btn.classList.contains('following');
    btn.disabled = true;
    fetch('/api/follow', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'user_id=' + userId,
        credentials: 'include'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btn.disabled = false;
        if (data.code === 200) {
            if (isFollowing) {
                btn.classList.remove('following');
                btn.textContent = '关注';
            } else {
                btn.classList.add('following');
                btn.textContent = '已关注';
            }
        } else {
            showToast(data.msg || '操作失败');
        }
    })
    .catch(function() {
        btn.disabled = false;
        showToast('网络错误');
    });
}

// ===== 回复 =====
function replyTo(replyId, username) {
    var input = document.getElementById('replyInput');
    input.value = '@' + username + ' ';
    input.focus();
    document.getElementById('replyHint').textContent = '正在回复 @' + username;
    document.getElementById('replyHint').style.display = 'block';
    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.getElementById('replySubmit').addEventListener('click', function() {
    var input = document.getElementById('replyInput');
    var content = input.value.trim();
    if (!content) { showToast('请输入回复内容'); return; }
    if (!state.user) { showToast('请先登录'); return; }
    this.disabled = true;
    this.textContent = '发送中...';
    fetch('/api/comment_create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + <?php echo $post['id']; ?> + '&content=' + encodeURIComponent(content),
        credentials: 'include'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        document.getElementById('replySubmit').disabled = false;
        document.getElementById('replySubmit').textContent = '发送回复';
        if (data.code === 200) {
            input.value = '';
            document.getElementById('replyHint').style.display = 'none';
            showToast('回复成功');
            setTimeout(function() { location.reload(); }, 800);
        } else {
            showToast(data.msg || '回复失败');
        }
    })
    .catch(function() {
        document.getElementById('replySubmit').disabled = false;
        document.getElementById('replySubmit').textContent = '发送回复';
        showToast('网络错误');
    });
});

document.getElementById('replyInput').addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('replySubmit').click();
    }
});

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
            setTimeout(function() { location.href = '/'; }, 600);
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

// ESC关闭图片查看器
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeImageViewer(); }
});

// ===== 全局暴露 =====
window.state = state;
window.likePost = likePost;
window.sharePost = sharePost;
window.recallPost = recallPost;
window.openImageViewer = openImageViewer;
window.closeImageViewer = closeImageViewer;
window.navigateTo = navigateTo;
window.logout = logout;
window.getAvatarUrl = getAvatarUrl;
window.timeAgo = timeAgo;
window.dialogOpen = dialogOpen;
window.dialogClose = dialogClose;
window.dom = dom;
window.showToast = showToast;
window.toggleSidebar = toggleSidebar;
window.handleSearch = handleSearch;
window.toggleFollow = toggleFollow;
window.replyTo = replyTo;
</script>
</body>
</html>