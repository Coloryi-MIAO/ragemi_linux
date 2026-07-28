<?php
// register.php - 瑞格米注册页面
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

if (me()) {
    header('Location: /');
    exit;
}

$error = '';
$username = '';
$nickname = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $code = trim($_POST['code'] ?? '');
    
    $result = createUser($username, $nickname, $email, $password, $code);
    if (isset($result['success'])) {
        $s = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $s->execute([$username]);
        $user = $s->fetch();
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH / 2));
            header('Location: /');
            exit;
        }
        $error = '注册成功，请登录';
    } else {
        $error = $result['error'] ?? '注册失败';
    }
}

$stats = getSiteStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - 瑞格米</title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="robots" content="noindex, follow">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/auth.css">
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
        <button class="icon-btn" onclick="toggleTheme()" title="切换主题">
            <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
        </button>
        <a href="/" class="btn-secondary">
            <span class="material-symbols-outlined">home</span> 首页
        </a>
    </div>
</header>

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
        <a href="/login" class="nav-item" data-page="login">
            <span class="material-symbols-outlined">login</span>
            <span class="nav-label">登录</span>
        </a>
    </div>
    <div class="sidebar-footer">
        <hr class="sidebar-divider">
        <a href="/settings" class="nav-item" data-page="settings">
            <span class="material-symbols-outlined">settings</span>
            <span class="nav-label">设置</span>
        </a>
    </div>
</nav>

<div class="main-wrapper" style="display:flex;align-items:center;justify-content:center;">
    <div class="auth-container">
        <div class="brand">
            <img src="https://ragemi.com/s/top.png" alt="瑞格米" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
            <h1 style="display:none;">瑞格米</h1>
            <p class="subtitle">二次元同好聚集地</p>
        </div>
        
        <div class="auth-tabs">
            <a href="/login">登录</a>
            <a href="?mode=register" class="active">注册</a>
        </div>
        
        <?php if ($error): ?>
        <div class="auth-error">
            <span class="material-symbols-outlined">error</span>
            <?php echo e($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="post" id="registerForm">
            <div class="auth-field">
                <label>用户名（用于登录）</label>
                <input type="text" name="username" required minlength="3" maxlength="20" 
                       placeholder="仅英文、数字、下划线" 
                       value="<?php echo e($username); ?>"
                       pattern="[a-zA-Z0-9_]+"
                       title="只能包含英文字母、数字和下划线">
            </div>
            
            <div class="auth-field">
                <label>昵称（显示名称）</label>
                <input type="text" name="nickname" required maxlength="20" 
                       placeholder="输入你的昵称（支持中文）" 
                       value="<?php echo e($nickname); ?>">
            </div>
            
            <div class="auth-field">
                <label>邮箱</label>
                <input type="email" name="email" id="registerEmail" required 
                       placeholder="输入邮箱" 
                       value="<?php echo e($email); ?>">
            </div>
            
            <div class="auth-field">
                <label>验证码</label>
                <div class="code-row">
                    <input type="text" name="code" required placeholder="6位验证码" 
                           maxlength="6" pattern="[0-9]{6}" autocomplete="off">
                    <button type="button" class="send-btn" id="sendCodeBtn">发送验证码</button>
                </div>
                <div id="codeMsg" class="code-hint"></div>
            </div>
            
            <div class="auth-field">
                <label>密码（至少6位）</label>
                <div class="password-wrapper">
                    <input type="password" name="password" required minlength="6" 
                           placeholder="输入密码">
                    <button type="button" class="toggle-pwd" onclick="toggleRegisterPassword()">
                        <span class="material-symbols-outlined" id="regPwdIcon">visibility_off</span>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="auth-btn" id="registerBtn">
                <span class="material-symbols-outlined">person_add</span> 注 册
            </button>
        </form>

        <!-- ===== Google 登录 ===== -->
        <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid rgba(150,170,190,0.1);">
            <p style="font-size:13px;color:var(--ba-text-muted);margin-bottom:12px;">或使用以下方式注册</p>
            <div id="g_id_onload"
                 data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                 data-context="signin"
                 data-ux_mode="redirect"
                 data-login_uri="<?php echo GOOGLE_REDIRECT_URI; ?>"
                 data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="outline"
                 data-text="signin_with"
                 data-size="large"
                 data-width="320">
            </div>
        </div>
        
        <div class="auth-footer">
            已有账号？<a href="/login">立即登录</a>
        </div>
        <div class="auth-back">
            <a href="/">← 返回首页</a>
        </div>
    </div>
</div>

<!-- ===== Footer ===== -->
<div class="ragemi-footer">
    <div class="footer-logo">
        <img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img">
    </div>
    <div class="footer-copyright">© <?php echo date('Y'); ?> 瑞格米 · 二次元帖子分享站</div>
</div>

<!-- Google 登录 SDK -->
<script src="https://accounts.google.com/gsi/client" async defer></script>

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

// ===== 主题切换 =====
function applyTheme(dark) {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    var icon = document.getElementById('theme-icon');
    if (icon) icon.textContent = dark ? 'light_mode' : 'dark_mode';
}
function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme');
    var isDark = current === 'dark';
    var next = !isDark;
    localStorage.setItem('ragemi_theme', next ? 'dark' : 'light');
    applyTheme(next);
}
(function() {
    var saved = localStorage.getItem('ragemi_theme');
    if (saved === 'dark') applyTheme(true);
    else if (saved === 'light') applyTheme(false);
    else if (window.matchMedia('(prefers-color-scheme: dark)').matches) applyTheme(true);
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

// ===== 密码显示切换 =====
function toggleRegisterPassword() {
    var pwd = document.querySelector('#registerForm input[name="password"]');
    var icon = document.getElementById('regPwdIcon');
    if (!pwd) return;
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.textContent = 'visibility';
    } else {
        pwd.type = 'password';
        icon.textContent = 'visibility_off';
    }
}

// ===== 发送验证码 =====
var sendBtn = document.getElementById('sendCodeBtn');
var emailInput = document.getElementById('registerEmail');
var codeMsg = document.getElementById('codeMsg');
var countdown = 0;
var timer = null;

if (sendBtn) {
    sendBtn.addEventListener('click', function() {
        var email = emailInput.value.trim();
        if (!email) {
            codeMsg.innerHTML = '<span class="error">⚠️ 请输入邮箱地址</span>';
            emailInput.focus();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            codeMsg.innerHTML = '<span class="error">⚠️ 请输入有效的邮箱地址</span>';
            emailInput.focus();
            return;
        }
        codeMsg.innerHTML = '<span class="warning">⏳ 发送中...</span>';
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
                codeMsg.innerHTML = '<span class="success">✅ ' + data.msg + '</span>';
                startCountdown(60);
            } else {
                codeMsg.innerHTML = '<span class="error">❌ ' + data.msg + '</span>';
            }
        })
        .catch(function() {
            codeMsg.innerHTML = '<span class="error">❌ 网络错误，请重试</span>';
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

var codeInput = document.querySelector('#registerForm input[name="code"]');
if (codeInput) {
    codeInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');
    });
}

var usernameInput = document.querySelector('#registerForm input[name="username"]');
if (usernameInput) {
    usernameInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
    });
}

document.querySelector('form')?.addEventListener('submit', function() {
    var btn = document.getElementById('registerBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite;">progress_activity</span> 注册中...';
});

var style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);

window.toggleSidebar = toggleSidebar;
</script>
</body>
</html>