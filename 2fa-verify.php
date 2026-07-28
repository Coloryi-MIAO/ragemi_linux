<?php
// 2fa-verify.php - 二次验证页面
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

// 检查是否有2FA会话
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: /login');
    exit;
}

// 如果已经登录，跳转到首页
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

$error = '';
$resendMsg = '';

// 获取用户信息
$userId = $_SESSION['2fa_user_id'];
$s = $pdo->prepare("SELECT id, username, display_name, email, two_factor_enabled FROM users WHERE id = ?");
$s->execute([$userId]);
$user = $s->fetch();

if (!$user || !($user['two_factor_enabled'] ?? 0)) {
    unset($_SESSION['2fa_user_id']);
    header('Location: /login');
    exit;
}

// 处理验证码提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify') {
        $code = trim($_POST['code'] ?? '');
        
        if (strlen($code) < 6) {
            $error = '请输入6位验证码';
        } elseif (!verifyCode($user['email'], $code)) {
            $error = '验证码错误或已过期，请重新获取';
        } else {
            // ✅ 验证成功，登录用户 - 重新生成 Session ID
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH / 2));
            unset($_SESSION['2fa_user_id']);
            
            // 记录登录
            $s = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $s->execute([$user['id']]);
            
            // 如果有记住我 Token，更新
            if (isset($_COOKIE['ragemi-token'])) {
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
        }
    } elseif ($action === 'resend') {
        $code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
        storeVerificationCode($user['email'], $code);
        if (sendVerificationCode($user['email'], $code)) {
            $resendMsg = '✅ 验证码已重新发送到您的邮箱';
        } else {
            $error = '发送失败，请稍后再试';
        }
    }
}

// 检查是否有未过期的验证码
$s = $pdo->prepare("SELECT id FROM email_verifications WHERE email = ? AND used = FALSE AND expires_at > NOW() LIMIT 1");
$s->execute([$user['email']]);
$hasValidCode = (bool)$s->fetch();

if (!$hasValidCode) {
    $code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
    storeVerificationCode($user['email'], $code);
    sendVerificationCode($user['email'], $code);
    $resendMsg = '📧 验证码已发送到您的邮箱';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>二次验证 - 瑞格米</title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="robots" content="noindex, follow">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
<!-- ===== 粒子背景 ===== -->
<div class="particle-bg" id="particleBg"></div>

<!-- ===== 顶部导航栏 ===== -->
<header id="app-bar">
    <div style="display:flex;align-items:center;gap:10px;">
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

<!-- ===== 主内容区 ===== -->
<div class="main-wrapper" style="display:flex;align-items:center;justify-content:center;">
    <div class="auth-container">
        <div class="brand">
            <img src="https://ragemi.com/s/top.png" alt="瑞格米" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
            <h1 style="display:none;">瑞格米</h1>
        </div>

        <div class="verify-icon-wrapper">
            <span class="shield-icon">
                <span class="material-symbols-outlined">shield_lock</span>
            </span>
        </div>

        <div class="verify-title">二次验证</div>
        <div class="verify-subtitle">
            为了您的账户安全，请输入发送到 <strong><?php echo e($user['email']); ?></strong> 的验证码
        </div>

        <?php if ($error): ?>
        <div class="auth-alert auth-alert-error">
            <span class="material-symbols-outlined">error</span>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <?php if ($resendMsg): ?>
        <div class="auth-alert auth-alert-info">
            <span class="material-symbols-outlined">info</span>
            <span><?php echo $resendMsg; ?></span>
        </div>
        <?php endif; ?>

        <form method="post" id="verifyForm">
            <input type="hidden" name="action" value="verify">
            <div class="auth-field">
                <label>验证码</label>
                <input type="text" name="code" id="codeInput" 
                       class="verify-code-input"
                       placeholder="123456" maxlength="6" 
                       pattern="[0-9]{6}" inputmode="numeric"
                       autocomplete="one-time-code" required autofocus>
            </div>
            <button type="submit" class="auth-btn" id="verifyBtn">
                <span class="material-symbols-outlined">verified</span> 验证并登录
            </button>
        </form>

        <div class="verify-footer-links">
            <div>
                <button class="resend-btn" id="resendBtn" onclick="resendCode()">重新发送验证码</button>
                <span class="verify-resend-timer" id="resendTimer"></span>
            </div>
            <a href="/login" class="back-link">← 返回登录</a>
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

// ===== 自动聚焦 =====
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('codeInput').focus();
});

// ===== 验证码输入自动跳转 =====
var codeInput = document.getElementById('codeInput');
codeInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
    if (this.value.length === 6) {
        document.getElementById('verifyForm').submit();
    }
});

// ===== 回车提交 =====
codeInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('verifyForm').submit();
    }
});

// ===== 重新发送验证码 =====
var resendCountdown = 0;
var resendTimer = null;

function resendCode() {
    var btn = document.getElementById('resendBtn');
    var timerEl = document.getElementById('resendTimer');
    if (btn.disabled) return;
    
    btn.disabled = true;
    btn.textContent = '发送中...';
    
    fetch('/api/resend_2fa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'user_id=<?php echo $userId; ?>'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.code === 200) {
            var alert = document.createElement('div');
            alert.className = 'auth-alert auth-alert-success';
            alert.innerHTML = '<span class="material-symbols-outlined">check_circle</span><span>✅ 验证码已重新发送</span>';
            document.querySelector('.auth-container').insertBefore(alert, document.querySelector('form'));
            setTimeout(function() { alert.remove(); }, 5000);
            startResendCountdown(60);
        } else {
            var alert = document.createElement('div');
            alert.className = 'auth-alert auth-alert-error';
            alert.innerHTML = '<span class="material-symbols-outlined">error</span><span>❌ ' + (data.msg || '发送失败') + '</span>';
            document.querySelector('.auth-container').insertBefore(alert, document.querySelector('form'));
            setTimeout(function() { alert.remove(); }, 5000);
            btn.disabled = false;
            btn.textContent = '重新发送验证码';
        }
    })
    .catch(function() {
        var alert = document.createElement('div');
        alert.className = 'auth-alert auth-alert-error';
        alert.innerHTML = '<span class="material-symbols-outlined">error</span><span>❌ 网络错误，请重试</span>';
        document.querySelector('.auth-container').insertBefore(alert, document.querySelector('form'));
        setTimeout(function() { alert.remove(); }, 5000);
        btn.disabled = false;
        btn.textContent = '重新发送验证码';
    });
}

function startResendCountdown(seconds) {
    resendCountdown = seconds;
    var btn = document.getElementById('resendBtn');
    var timerEl = document.getElementById('resendTimer');
    btn.disabled = true;
    btn.textContent = '重新发送';
    timerEl.textContent = seconds + 's';
    if (resendTimer) clearInterval(resendTimer);
    resendTimer = setInterval(function() {
        resendCountdown--;
        if (resendCountdown <= 0) {
            clearInterval(resendTimer);
            btn.disabled = false;
            btn.textContent = '重新发送验证码';
            timerEl.textContent = '';
        } else {
            timerEl.textContent = resendCountdown + 's';
        }
    }, 1000);
}

<?php if ($resendMsg): ?>
startResendCountdown(60);
<?php endif; ?>
</script>
</body>
</html>