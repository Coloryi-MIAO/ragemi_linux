<?php
// settings.php - 瑞格米设置页
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
$emailVerifyError = '';

// ============================================================
// 处理 2FA 开关
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_2fa') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        die('非法请求');
    }
    
    $enabled = (isset($_POST['two_factor_enabled']) && $_POST['two_factor_enabled'] === '1') ? 1 : 0;
    
    $s = $pdo->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
    if ($s->execute([$enabled, $me['id']])) {
        $s = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
        $s->execute([$me['id']]);
        $twoFactorEnabled = (int)$s->fetchColumn();
        $success = $twoFactorEnabled ? '二次验证已开启' : '二次验证已关闭';
        // 刷新用户数据
        $s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $s->execute([$me['id']]);
        $me = $s->fetch();
    } else {
        $error = '操作失败，请稍后再试';
    }
}

// ============================================================
// 处理邮箱修改
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_email') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        die('非法请求');
    }
    
    $newEmail = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $emailVerifyError = '邮箱格式不正确';
    } elseif (strlen($code) < 6) {
        $emailVerifyError = '请输入6位验证码';
    } elseif (!verifyCode($newEmail, $code)) {
        $emailVerifyError = '验证码错误或已过期';
    } else {
        $s = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $s->execute([$newEmail, $me['id']]);
        if ($s->fetch()) {
            $emailVerifyError = '该邮箱已被其他用户使用';
        } else {
            $s = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            if ($s->execute([$newEmail, $me['id']])) {
                $success = '邮箱已更新';
                $s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $s->execute([$me['id']]);
                $me = $s->fetch();
            } else {
                $emailVerifyError = '更新失败，请稍后再试';
            }
        }
    }
}

// ============================================================
// 处理个人资料修改
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        die('非法请求');
    }
    
    $data = [];
    if (isset($_POST['username'])) {
        $username = trim($_POST['username']);
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = '用户名只能包含英文字母、数字和下划线';
        } elseif (strlen($username) < 3) {
            $error = '用户名至少3个字符';
        } else {
            $s = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $s->execute([$username, $me['id']]);
            if ($s->fetch()) {
                $error = '该用户名已被使用';
            } else {
                $data['username'] = $username;
                $subdomain = strtolower($username);
                $s = $pdo->prepare("SELECT id FROM users WHERE subdomain = ? AND id != ?");
                $s->execute([$subdomain, $me['id']]);
                if ($s->fetch()) {
                    $subdomain = $subdomain . rand(100, 999);
                }
                $data['subdomain'] = $subdomain;
            }
        }
    }
    if (isset($_POST['display_name'])) {
        $data['display_name'] = trim($_POST['display_name']);
        if (mb_strlen($data['display_name']) < 1) {
            $error = '请输入昵称';
        } elseif (mb_strlen($data['display_name']) > 20) {
            $error = '昵称不能超过20个字符';
        }
    }
    if (isset($_POST['bio'])) {
        $data['bio'] = trim($_POST['bio']);
        if (mb_strlen($data['bio']) > BIO_MAX_LENGTH) {
            $error = '简介不能超过' . BIO_MAX_LENGTH . '个字符';
        }
    }
    if (!empty($_POST['password'])) {
        if (strlen($_POST['password']) < PASSWORD_MIN_LENGTH) {
            $error = '密码至少' . PASSWORD_MIN_LENGTH . '位';
        } else {
            $data['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
    }
    
    if (empty($error) && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $result = uploadImage($_FILES['avatar'], AVATAR_DIR);
        if (isset($result['success'])) {
            if ($me['avatar'] && file_exists(AVATAR_DIR . $me['avatar'])) {
                @unlink(AVATAR_DIR . $me['avatar']);
            }
            $data['avatar'] = $result['filename'];
        } else {
            $error = $result['error'];
        }
    }
    
    if (empty($error) && !empty($data)) {
        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $me['id'];
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $s = $pdo->prepare($sql);
        if ($s->execute($params)) {
            $success = '个人资料已更新';
            $s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $s->execute([$me['id']]);
            $me = $s->fetch();
        } else {
            $error = '更新失败，请稍后再试';
        }
    } elseif (empty($error) && empty($data)) {
        $success = '没有需要更新的内容';
    }
}

// ============================================================
// 获取最新 2FA 状态
// ============================================================
$s = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
$s->execute([$me['id']]);
$twoFactorEnabled = (int)$s->fetchColumn();

$unreadMessages = getUnreadMessageCount($me['id']);
$stats = getSiteStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设置 - 瑞格米</title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/settings.css">
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
    <main class="main-content">
        <div class="settings-card">
            <div class="card-title">设置</div>
            <div class="card-sub">管理你的个人资料和账号信息</div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- ===== 二次验证开关 ===== -->
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span class="material-symbols-outlined" style="color:var(--ba-accent);font-size:20px;">shield</span>
                    <span style="font-weight:600;color:var(--ba-text);font-size:15px;">登录二次验证</span>
                </div>
                <div style="font-size:13px;color:var(--ba-text-muted);margin-bottom:12px;">
                    开启后，登录时需要输入邮箱验证码，提高账户安全性
                </div>

                <form method="post" id="twoFactorForm">
                    <input type="hidden" name="action" value="toggle_2fa">
                    <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="two_factor_enabled" id="twoFactorHidden" value="<?php echo $twoFactorEnabled; ?>">

                    <div class="toggle-container">
                        <div class="toggle-info">
                            <span class="toggle-icon">
                                <span class="material-symbols-outlined" style="font-size:28px;">
                                    <?php echo $twoFactorEnabled ? 'verified' : 'security'; ?>
                                </span>
                            </span>
                            <div>
                                <div class="toggle-label">二次验证</div>
                                <div class="toggle-desc">
                                    <?php if ($twoFactorEnabled): ?>
                                        <span class="enabled-text">已开启</span>
                                        <span style="color:var(--ba-text-muted);"> · 登录需验证邮箱</span>
                                    <?php else: ?>
                                        <span class="disabled-text">已关闭</span>
                                        <span style="color:var(--ba-text-muted);"> · 建议开启提高安全性</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="twoFactorToggle"
                                   <?php echo $twoFactorEnabled ? 'checked' : ''; ?>
                                   onchange="toggle2FA(this)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </form>
            </div>

            <hr class="divider">

            <!-- ===== 个人资料表单 ===== -->
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">

                <!-- 头像 -->
                <label>头像</label>
                <div class="avatar-section">
                    <div class="avatar">
                        <?php if ($me['avatar']): ?>
                            <img src="<?php echo getAvatarUrl($me['avatar']); ?>" alt="头像">
                        <?php else: ?>
                            <span class="material-symbols-outlined" style="font-size:40px;">person</span>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-info">
                        <input type="file" name="avatar" accept="image/*" class="field-input">
                        <div class="file-hint">支持 JPG, PNG, GIF, WebP，最大 10MB</div>
                    </div>
                </div>

                <!-- 用户名 -->
                <label>用户名（用于登录）</label>
                <input type="text" name="username" class="field-input"
                       value="<?php echo e($me['username']); ?>"
                       required pattern="[a-zA-Z0-9_]+"
                       title="只能包含英文字母、数字和下划线"
                       placeholder="仅英文、数字、下划线">
                <div style="font-size:12px;color:var(--ba-text-muted);margin-top:4px;">修改用户名后，个人空间地址将同步更新</div>

                <!-- 昵称 -->
                <label>昵称（显示名称）</label>
                <input type="text" name="display_name" class="field-input"
                       value="<?php echo e($me['display_name']); ?>" required
                       placeholder="输入你的显示名称">

                <!-- 个人空间地址 -->
                <label>个人空间地址</label>
                <div class="info-text">
                    <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">link</span>
                    <strong>ragemi.com/@<?php echo e($me['subdomain']); ?></strong>
                    <span style="color:var(--ba-text-muted);font-size:12px;margin-left:8px;">（与用户名同步）</span>
                </div>

                <!-- 简介 -->
                <label>个人简介</label>
                <textarea name="bio" class="field-input" rows="3" placeholder="介绍一下你自己..."><?php echo e($me['bio']); ?></textarea>

                <!-- 密码 -->
                <label>新密码（留空则不修改）</label>
                <input type="password" name="password" class="field-input"
                       placeholder="输入新密码（至少 <?php echo PASSWORD_MIN_LENGTH; ?> 位）"
                       minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">

                <!-- 按钮 -->
                <div class="btn-group">
                    <button type="button" class="btn-secondary" onclick="location.href='/'">
                        <span class="material-symbols-outlined">close</span> 取消
                    </button>
                    <button type="submit" class="btn-primary">
                        <span class="material-symbols-outlined">save</span> 保存资料
                    </button>
                </div>
            </form>

            <!-- ===== 邮箱修改 ===== -->
            <hr class="divider">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--ba-text);margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:20px;">email</span> 邮箱
                </div>

                <?php if ($emailVerifyError): ?>
                    <div class="alert alert-error"><?php echo $emailVerifyError; ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="action" value="update_email">
                    <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">

                    <div style="margin-bottom:12px;">
                        <div class="info-text" style="margin-bottom:12px;">
                            当前邮箱：<strong><?php echo e($me['email']); ?></strong>
                        </div>
                        <label style="margin-top:0;">新邮箱</label>
                        <input type="email" name="email" class="field-input"
                               placeholder="输入新邮箱地址" required
                               value="<?php echo e($me['email']); ?>">
                    </div>

                    <div>
                        <label>验证码</label>
                        <div class="code-row">
                            <input type="text" name="code" class="field-input" required
                                   placeholder="6位验证码" maxlength="6" pattern="[0-9]{6}" autocomplete="off">
                            <button type="button" class="send-btn" id="sendEmailCodeBtn">发送验证码</button>
                        </div>
                        <div id="emailCodeMsg" class="code-hint"></div>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <button type="submit" class="btn-primary">
                            <span class="material-symbols-outlined">check</span> 更新邮箱
                        </button>
                    </div>
                </form>
            </div>

            <!-- ===== 退出登录 ===== -->
            <hr class="divider">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--ba-text);margin-bottom:4px;display:flex;align-items:center;gap:6px;">
                    <span class="material-symbols-outlined" style="font-size:20px;">logout</span> 账号管理
                </div>
                <div style="font-size:13px;color:var(--ba-text-muted);margin-bottom:12px;">
                    退出当前登录账号
                </div>
                <div class="btn-group">
                    <a href="/logout" class="btn-secondary" style="border-color:#e74c3c;color:#e74c3c;" onclick="return confirm('确认退出登录？')">
                        <span class="material-symbols-outlined">logout</span> 退出登录
                    </a>
                </div>
            </div>

            <!-- ===== 危险操作 ===== -->
            <hr class="divider">
            <div class="danger-zone">
                <div class="danger-title">
                    <span class="material-symbols-outlined" style="font-size:20px;">warning</span> 危险操作
                </div>
                <div class="danger-desc">以下操作不可撤销，请谨慎操作</div>
                <div class="btn-group">
                    <button class="btn-danger" onclick="if(confirm('确认删除账号？此操作不可撤销！')) alert('功能开发中')">
                        <span class="material-symbols-outlined">delete_forever</span> 删除账号
                    </button>
                </div>
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

// ===== 2FA 开关 =====
function toggle2FA(checkbox) {
    var hidden = document.getElementById('twoFactorHidden');
    var form = document.getElementById('twoFactorForm');
    
    // ✅ 设置 hidden 值
    hidden.value = checkbox.checked ? '1' : '0';
    
    // ✅ 提交表单
    form.submit();
}

// 页面加载完成后，如果有成功消息，2秒后刷新页面（保持状态同步）
<?php if ($success && strpos($success, '二次验证') !== false): ?>
setTimeout(function() {
    location.reload();
}, 1500);
<?php endif; ?>

// ===== 邮箱验证码 =====
var sendBtn = document.getElementById('sendEmailCodeBtn');
var emailInput = document.querySelector('input[name="email"]');
var codeMsg = document.getElementById('emailCodeMsg');
var countdown = 0;
var timer = null;

if (sendBtn) {
    sendBtn.addEventListener('click', function() {
        var email = emailInput.value.trim();
        if (!email) {
            codeMsg.innerHTML = '<span class="error">请输入邮箱地址</span>';
            emailInput.focus();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            codeMsg.innerHTML = '<span class="error">请输入有效的邮箱地址</span>';
            emailInput.focus();
            return;
        }
        codeMsg.innerHTML = '<span class="warning">发送中...</span>';
        var formData = new URLSearchParams();
        formData.append('email', email);
        fetch('/api/send_verification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.code === 200) {
                codeMsg.innerHTML = '<span class="success">' + data.msg + '</span>';
                startCountdown(60);
            } else {
                codeMsg.innerHTML = '<span class="error">' + data.msg + '</span>';
            }
        })
        .catch(function() {
            codeMsg.innerHTML = '<span class="error">网络错误，请重试</span>';
        });
    });
}

function startCountdown(seconds) {
    countdown = seconds;
    sendBtn.disabled = true;
    if (timer) clearInterval(timer);
    timer = setInterval(function() {
        countdown--;
        if (countdown <= 0) {
            clearInterval(timer);
            sendBtn.disabled = false;
            sendBtn.textContent = '重新发送';
        } else {
            sendBtn.textContent = countdown + 's';
        }
    }, 1000);
}

// 验证码只允许数字
var codeInput = document.querySelector('input[name="code"]');
if (codeInput) {
    codeInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');
    });
}

// 用户名输入限制
var usernameInput = document.querySelector('input[name="username"]');
if (usernameInput) {
    usernameInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
    });
}

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
    <?php if ($me): ?>
    document.getElementById('nav-auth').style.display = 'none';
    document.getElementById('nav-user').style.display = 'flex';
    document.getElementById('nav-profile').style.display = '';
    document.getElementById('nav-messages').style.display = '';
    document.getElementById('user-avatar-img').src = getAvatarUrl('<?php echo e($me['avatar']); ?>');
    <?php else: ?>
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
    <?php endif; ?>
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

// ===== 全局暴露 =====
window.logout = logout;
window.toggleSidebar = toggleSidebar;
window.toggle2FA = toggle2FA;
</script>
</body>
</html>