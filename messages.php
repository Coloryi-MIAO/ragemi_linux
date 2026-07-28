<?php
// messages.php - 私信页面
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

$me = me();
if (!$me) {
    header('Location: /login');
    exit;
}

$error = '';
$success = '';
$toUser = null;
$messages = [];
$to = trim($_GET['to'] ?? '');

if ($to) {
    $toUser = userByUsername($to);
    if (!$toUser) {
        $error = '用户不存在';
    } elseif ($toUser['id'] == $me['id']) {
        $error = '不能给自己发消息';
    } else {
        $messages = getMessages($me['id'], $toUser['id'], 50);
    }
}

// 获取联系人列表
$s = $pdo->prepare("
    SELECT DISTINCT 
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as user_id,
        u.username, u.display_name, u.subdomain, u.avatar, u.status,
        m.content as last_message,
        m.created_at as last_time
    FROM messages m
    JOIN users u ON (u.id = m.sender_id OR u.id = m.receiver_id)
    WHERE (m.sender_id = ? OR m.receiver_id = ?)
    AND u.id != ?
    ORDER BY m.created_at DESC
    LIMIT 50
");
$s->execute([$me['id'], $me['id'], $me['id'], $me['id']]);
$contacts = $s->fetchAll();

// 去重：保留每个对话的最新一条
$uniqueContacts = [];
foreach ($contacts as $contact) {
    $key = $contact['user_id'];
    if (!isset($uniqueContacts[$key])) {
        $uniqueContacts[$key] = $contact;
    }
}
$contacts = array_values($uniqueContacts);

// 处理发送消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        die('非法请求');
    }
    $receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
    $content = trim($_POST['content'] ?? '');
    
    if ($receiverId <= 0 || $receiverId == $me['id']) {
        $error = '无效的接收者';
    } elseif (empty($content)) {
        $error = '请输入消息内容';
    } elseif (mb_strlen($content) > 500) {
        $error = '消息不能超过500字';
    } else {
        $result = sendMessage($me['id'], $receiverId, $content);
        if ($result) {
            $success = '发送成功';
            // 刷新消息列表
            $messages = getMessages($me['id'], $receiverId, 50);
            // 更新联系人列表
            $toUser = userById($receiverId);
        } else {
            $error = '发送失败，请稍后再试';
        }
    }
}

$title = '私信 - 瑞格米';
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
    <link rel="stylesheet" href="/css/messages.css">
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
        <div class="nav-auth" id="nav-auth" style="display:none;"></div>
        <div class="nav-user" id="nav-user" style="display:flex;">
            <button class="icon-btn" id="btn-notifications" style="position:relative"><span class="material-symbols-outlined">notifications</span><span class="notif-badge" id="notif-badge" style="display:none"></span></button>
            <button class="icon-btn" id="btn-user-avatar"><img id="user-avatar-img" src="<?php echo getAvatarUrl($me['avatar']); ?>" alt="" class="avatar-small" onerror="this.src='/assets/default-avatar.png'"></button>
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
        <div class="messages-container">
            <!-- 联系人列表 -->
            <div class="contacts-sidebar">
                <div class="contacts-header">
                    <h2>私信</h2>
                    <span class="contacts-count"><?php echo count($contacts); ?></span>
                </div>
                <div class="contacts-list">
                    <?php if ($contacts): ?>
                        <?php foreach ($contacts as $contact): ?>
                            <a href="/messages?to=<?php echo e($contact['username']); ?>" class="contact-item <?php echo ($toUser && $toUser['id'] == $contact['user_id']) ? 'active' : ''; ?>">
                                <img src="<?php echo getAvatarUrl($contact['avatar']); ?>" class="contact-avatar" onerror="this.src='/assets/default-avatar.png'">
                                <div class="contact-info">
                                    <div class="contact-name"><?php echo e($contact['display_name'] ?: $contact['username']); ?></div>
                                    <div class="contact-handle">@<?php echo e($contact['subdomain']); ?></div>
                                    <div class="contact-preview"><?php echo e(mb_substr($contact['last_message'] ?? '', 0, 30)); ?></div>
                                </div>
                                <div class="contact-time"><?php echo isset($contact['last_time']) ? timeAgo($contact['last_time']) : ''; ?></div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="contacts-empty">暂无对话</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 对话区域 -->
            <div class="chat-area">
                <?php if ($toUser): ?>
                    <div class="chat-header">
                        <img src="<?php echo getAvatarUrl($toUser['avatar']); ?>" class="chat-avatar" onerror="this.src='/assets/default-avatar.png'">
                        <div class="chat-user-info">
                            <div class="chat-user-name"><?php echo e($toUser['display_name'] ?: $toUser['username']); ?></div>
                            <div class="chat-user-handle">@<?php echo e($toUser['subdomain']); ?></div>
                        </div>
                        <a href="/@<?php echo e($toUser['subdomain']); ?>" class="chat-profile-link" target="_blank">查看主页</a>
                    </div>

                    <div class="messages-list" id="messagesList">
                        <?php if ($messages): ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-item <?php echo $msg['sender_id'] == $me['id'] ? 'sent' : 'received'; ?>">
                                    <div class="message-bubble">
                                        <?php echo e($msg['content']); ?>
                                    </div>
                                    <div class="message-time"><?php echo timeAgo($msg['created_at']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="messages-empty">还没有消息，打个招呼吧</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <div class="message-composer">
                        <form method="post" id="messageForm">
                            <input type="hidden" name="action" value="send">
                            <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="receiver_id" value="<?php echo $toUser['id']; ?>">
                            <textarea name="content" id="messageInput" placeholder="输入消息..." rows="2" maxlength="500"></textarea>
                            <button type="submit" class="send-btn">
                                <span class="material-symbols-outlined">send</span>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="chat-placeholder">
                        <span class="material-symbols-outlined" style="font-size:64px;">chat</span>
                        <p>选择一个对话开始聊天</p>
                        <?php if ($error): ?>
                            <div class="alert alert-error"><?php echo $error; ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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

// ===== 消息发送 =====
var messageInput = document.getElementById('messageInput');
var messageForm = document.getElementById('messageForm');

if (messageForm) {
    messageForm.addEventListener('submit', function(e) {
        var content = messageInput.value.trim();
        if (!content) {
            e.preventDefault();
            showToast('请输入消息内容');
            return;
        }
        // 禁用提交按钮防止重复
        var btn = this.querySelector('.send-btn');
        btn.disabled = true;
    });
}

// Ctrl+Enter 发送
if (messageInput) {
    messageInput.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            var form = document.getElementById('messageForm');
            if (form) {
                form.submit();
            }
        }
    });
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

// ===== 自动滚动到底部 =====
var messagesList = document.getElementById('messagesList');
if (messagesList) {
    messagesList.scrollTop = messagesList.scrollHeight;
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
});

window.toggleSidebar = toggleSidebar;
window.showToast = showToast;
</script>
</body>
</html>