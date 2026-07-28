<?php
// login.php - 瑞格米登录页面
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

// 如果已登录，跳转到首页
if (me()) {
    header('Location: /');
    exit;
}

$error = '';
$mode = $_GET['mode'] ?? 'login';
$username = '';
$remember = false;

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码';
    } else {
        $s = $pdo->prepare("SELECT id, password_hash, email, two_factor_enabled, username, display_name, status FROM users WHERE username = ? OR email = ?");
        $s->execute([$username, $username]);
        $user = $s->fetch();
        
        if (!$user) {
            $error = '用户名或密码错误';
        } elseif ($user['status'] === 'banned') {
            $error = '账号已被封禁，请联系管理员';
        } elseif (password_verify($password, $user['password_hash'])) {
            // 检查是否开启二次验证
            if (isset($user['two_factor_enabled']) && $user['two_factor_enabled']) {
                $code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
                storeVerificationCode($user['email'], $code);
                sendVerificationCode($user['email'], $code);
                
                $_SESSION['2fa_user_id'] = $user['id'];
                $_SESSION['2fa_username'] = $user['display_name'] ?: $user['username'];
                $_SESSION['2fa_email'] = $user['email'];
                
                header('Location: /2fa-verify');
                exit;
            }
            
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH / 2));
            
            $s = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $s->execute([$user['id']]);
            
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
                $s = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
                $s->execute([$user['id'], $token, $expires, $token, $expires]);
                setcookie('ragemi-token', $token, [
                    'expires' => time() + 7 * 24 * 3600,
                    'path' => '/',
                    'domain' => COOKIE_DOMAIN,
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                ]);
            }
            
            header('Location: /');
            exit;
        } else {
            $error = '用户名或密码错误';
        }
    }
}

$stats = getSiteStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 瑞格米</title>
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
        <a href="/register" class="nav-item" data-page="register">
            <span class="material-symbols-outlined">person_add</span>
            <span class="nav-label">注册</span>
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
            <a href="?mode=login" class="<?php echo $mode === 'login' ? 'active' : ''; ?>">登录</a>
            <a href="/register">注册</a>
        </div>
        
        <?php if ($error): ?>
        <div class="auth-error">
            <span class="material-symbols-outlined">error</span>
            <?php echo e($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="auth-field">
                <label>用户名 / 邮箱</label>
                <input type="text" name="username" id="loginUsername" 
                       placeholder="请输入用户名或邮箱" 
                       value="<?php echo e($username); ?>" required autofocus>
            </div>
            <div class="auth-field">
                <label>密码</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="loginPassword" 
                           placeholder="请输入密码" required>
                    <button type="button" class="toggle-pwd" onclick="togglePassword()">
                        <span class="material-symbols-outlined" id="pwdIcon">visibility_off</span>
                    </button>
                </div>
            </div>
            
            <div class="remember-row">
                <label>
                    <input type="checkbox" name="remember" id="rememberMe">
                    记住我
                </label>
                <a href="/forgot-password" class="forgot-link">忘记密码？</a>
            </div>
            
            <button type="submit" class="auth-btn" id="loginBtn">
                <span class="material-symbols-outlined">login</span> 登 录
            </button>
        </form>

        <!-- Google 登录 -->
        <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid rgba(150,170,190,0.1);">
            <p style="font-size:13px;color:var(--ba-text-muted);margin-bottom:12px;">或使用以下方式登录</p>
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
            还没有账号？<a href="/register">立即注册</a>
        </div>
        <div class="auth-back">
            <a href="/">← 返回首页</a>
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
function togglePassword() {
    var pwd = document.getElementById('loginPassword');
    var icon = document.getElementById('pwdIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.textContent = 'visibility';
    } else {
        pwd.type = 'password';
        icon.textContent = 'visibility_off';
    }
}

// ===== 登录按钮防重复提交 =====
document.querySelector('form')?.addEventListener('submit', function() {
    var btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite;">progress_activity</span> 登录中...';
});

// ===== 添加旋转动画 =====
var style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);

window.toggleSidebar = toggleSidebar;
</script>
</body>
</html>