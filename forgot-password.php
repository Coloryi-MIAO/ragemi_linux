<?php
// forgot-password.php - 忘记密码
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 如果已登录，跳转到首页
if (me()) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';
$step = 'request'; // request | verify | reset
$email = '';
$code = '';
$token = '';

// 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ===== 步骤1：请求重置 =====
    if ($action === 'request') {
        $email = trim($_POST['email'] ?? '');
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        } else {
            // 检查邮箱是否存在
            $s = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $s->execute([$email]);
            $user = $s->fetch();
            
            if (!$user) {
                // 为了安全，不暴露邮箱是否存在
                $success = '如果该邮箱已注册，我们将发送重置链接';
            } else {
                // 生成重置令牌
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1小时有效
                
                $s = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $s->execute([$email]);
                
                $s = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $s->execute([$email, $token, $expires]);
                
                // 发送邮件
                $resetLink = SITE_URL . '/forgot-password?token=' . $token . '&email=' . urlencode($email);
                
                // 使用 mailer.php 发送
                require_once __DIR__ . '/mailer.php';
                $mailSent = send_password_reset_email($email, $user['username'], $resetLink);
                
                if ($mailSent) {
                    $success = '✅ 重置链接已发送到您的邮箱，请查收';
                } else {
                    $error = '邮件发送失败，请稍后再试';
                }
            }
        }
    }
    
    // ===== 步骤2：验证令牌并重置密码 =====
    if ($action === 'reset') {
        $email = trim($_POST['email'] ?? '');
        $token = trim($_POST['token'] ?? '');
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';
        
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $error = '密码至少' . PASSWORD_MIN_LENGTH . '位';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '两次密码输入不一致';
        } else {
            // 验证令牌
            $s = $pdo->prepare("SELECT id, email FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used = FALSE LIMIT 1");
            $s->execute([$email, $token]);
            $reset = $s->fetch();
            
            if (!$reset) {
                $error = '无效或已过期的重置链接';
            } else {
                // 更新密码
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $s = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                $s->execute([$hash, $email]);
                
                // 标记令牌已使用
                $s = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE email = ? AND token = ?");
                $s->execute([$email, $token]);
                
                $success = '✅ 密码已重置成功，请登录';
                $step = 'done';
            }
        }
    }
}

// ===== 检查是否有令牌参数（从URL进入重置步骤） =====
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = trim($_GET['token']);
    $email = trim($_GET['email']);
    
    // 验证令牌是否有效
    $s = $pdo->prepare("SELECT id FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used = FALSE LIMIT 1");
    $s->execute([$email, $token]);
    if ($s->fetch()) {
        $step = 'reset';
    } else {
        $error = '无效或已过期的重置链接，请重新申请';
        $step = 'request';
    }
}

$stats = getSiteStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘记密码 - <?php echo SITE_TITLE; ?></title>
    <link rel="icon" href="/s/67.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <style>
        /* ========================================
           Ragemi - 蔚蓝档案主题
           ======================================== */
        
        :root {
            --ba-blue: #7ec8e3;
            --ba-blue-dark: #4a9fc7;
            --ba-pink: #f4c2d0;
            --ba-bg: #f0f4f8;
            --ba-text: #4a6075;
            --ba-text-secondary: #5a6f84;
            --ba-text-muted: #8a9db0;
            --ba-text-light: #a0b8cc;
            --ba-accent: #7A5C2D;
            --ba-accent-light: #c9a06a;
            --ba-accent-dark: #5c3e1f;
            --ba-card-bg: rgba(255,255,255,0.55);
            --ba-card-border: rgba(255,255,255,0.8);
            --ba-card-shadow: 0 2px 12px rgba(150,170,190,0.12);
            --ba-card-shadow-hover: 0 8px 28px rgba(150,170,190,0.22);
            --ba-radius: 12px;
            --ba-radius-lg: 20px;
            --content-max-width: 680px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ba-bg: #1a1e24;
                --ba-text: #d4dce8;
                --ba-text-secondary: #b0c0d0;
                --ba-text-muted: #7a8a9a;
                --ba-text-light: #5a6a7a;
                --ba-card-bg: rgba(30,35,45,0.7);
                --ba-card-border: rgba(60,70,85,0.5);
                --ba-card-shadow: 0 2px 12px rgba(0,0,0,0.3);
                --ba-card-shadow-hover: 0 8px 28px rgba(0,0,0,0.4);
                --ba-blue: #5a9fbf;
                --ba-blue-dark: #3a7f9f;
            }
            body { background: var(--ba-bg); }
            .forgot-container { background: var(--ba-card-bg); backdrop-filter: blur(10px); }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background: var(--ba-bg);
            font-family: "Microsoft YaHei","PingFang SC","Helvetica Neue",sans-serif;
            color: var(--ba-text);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            transition: background 0.3s ease, color 0.3s ease;
        }
        a { color: var(--ba-accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ===== Top Navigation Bar（与 index 完全一致） ===== */
        #app-bar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            padding: 0 20px;
            background: var(--ba-card-bg);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-bottom: 1px solid var(--ba-card-border);
            box-shadow: 0 1px 6px rgba(150,170,190,0.1);
        }

        .app-title-link {
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .app-title-link:hover {
            text-decoration: none;
        }

        .app-logo {
            height: 38px;
            width: auto;
            display: block;
            object-fit: contain;
        }

        @media (prefers-color-scheme: dark) {
            .app-logo {
                filter: brightness(0.9);
            }
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: var(--ba-text-secondary);
            cursor: pointer;
            transition: background 0.2s;
            font-size: 20px;
        }
        .icon-btn:hover {
            background: rgba(122,92,45,0.12);
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 20px;
            border: none;
            background: var(--ba-accent);
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }
        .btn-primary:hover {
            background: var(--ba-accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(122,92,45,0.3);
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 20px;
            border: 1px solid var(--ba-accent);
            background: transparent;
            color: var(--ba-accent);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }
        .btn-secondary:hover {
            background: rgba(122,92,45,0.08);
        }

        .btn-text {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            border: none;
            background: transparent;
            color: var(--ba-text-secondary);
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            font-family: inherit;
        }
        .btn-text:hover {
            background: rgba(122,92,45,0.08);
            color: var(--ba-accent);
        }

        /* ===== Sub Navigation（与 index 完全一致） ===== */
        .sub-nav {
            display: flex;
            justify-content: center;
            gap: 4px;
            padding: 8px 16px;
            background: transparent;
            flex-wrap: wrap;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            border: none;
            background: transparent;
            color: var(--ba-text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            font-family: inherit;
            position: relative;
        }
        .nav-btn:hover {
            background: rgba(122,92,45,0.08);
            color: var(--ba-accent);
        }
        .nav-btn.active {
            background: rgba(122,92,45,0.12);
            color: var(--ba-accent);
            font-weight: 600;
        }

        /* ===== 忘记密码容器 ===== */
        .forgot-container {
            max-width: 440px;
            margin: 40px auto;
            padding: 36px 32px 32px;
            background: var(--ba-card-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--ba-radius-lg);
            border: 1px solid var(--ba-card-border);
            box-shadow: var(--ba-card-shadow);
            transition: box-shadow 0.35s ease;
        }
        .forgot-container:hover {
            box-shadow: var(--ba-card-shadow-hover);
        }

        .forgot-container .brand {
            text-align: center;
            margin-bottom: 20px;
        }
        .forgot-container .brand img {
            height: 38px;
            width: auto;
            display: inline-block;
            object-fit: contain;
        }
        .forgot-container .brand h1 {
            font-family: 'Playfair Display', serif;
            color: var(--ba-accent);
            font-size: 24px;
            margin-top: 4px;
        }
        .forgot-container .brand .subtitle {
            color: var(--ba-text-muted);
            font-size: 14px;
        }

        .field-group {
            margin-bottom: 16px;
        }
        .field-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--ba-text-secondary);
            margin-bottom: 4px;
        }
        .field-group input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid rgba(150,170,190,0.25);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background: rgba(255,255,255,0.6);
            color: var(--ba-text);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .field-group input:focus {
            border-color: var(--ba-accent);
            box-shadow: 0 0 0 3px rgba(122,92,45,0.1);
        }
        .field-group input::placeholder {
            color: var(--ba-text-light);
        }

        .field-group .password-wrapper {
            position: relative;
        }
        .field-group .password-wrapper .toggle-pwd {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--ba-text-muted);
            cursor: pointer;
            font-size: 20px;
            padding: 4px;
        }
        .field-group .password-wrapper .toggle-pwd:hover {
            color: var(--ba-accent);
        }

        .btn-full {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: var(--ba-accent);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-full:hover {
            background: var(--ba-accent-dark);
            transform: translateY(-1px);
        }
        .btn-full:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-msg {
            background: rgba(212,160,160,0.15);
            color: #c0392b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .error-msg .material-symbols-outlined {
            font-size: 20px;
        }

        .success-msg {
            background: rgba(39,174,96,0.1);
            color: #27ae60;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .success-msg .material-symbols-outlined {
            font-size: 20px;
        }

        .back-home {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
        }
        .back-home a {
            color: var(--ba-text-muted);
        }
        .back-home a:hover {
            color: var(--ba-accent);
        }

        .divider {
            border: none;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--ba-text-light), transparent);
            margin: 20px 0;
            opacity: 0.25;
        }

        /* ===== Footer ===== */
        .ragemi-footer {
            text-align: center;
            padding: 32px 16px 28px;
            border-top: 1px solid rgba(150,170,190,0.15);
            margin-top: 20px;
            max-width: var(--content-max-width);
            margin-left: auto;
            margin-right: auto;
        }

        .footer-logo {
            margin-bottom: 10px;
        }

        .footer-logo-img {
            height: 36px;
            width: auto;
            display: inline-block;
            object-fit: contain;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .footer-logo-img:hover {
            opacity: 1;
        }

        .footer-copyright {
            color: var(--ba-text-muted);
            font-size: 13px;
            letter-spacing: 1px;
        }

        @media (prefers-color-scheme: dark) {
            .footer-logo-img {
                filter: brightness(0.9);
            }
        }

        /* ===== Responsive ===== */
        @media (max-width: 500px) {
            .forgot-container { padding: 24px 20px; margin: 20px 12px; }
            .app-logo { height: 32px; }
            .sub-nav .nav-btn span:not(.material-symbols-outlined) { display: none; }
            .sub-nav .nav-btn .material-symbols-outlined { margin: 0; }
        }
    </style>
</head>
<body>

<!-- ===== Top Navigation Bar ===== -->
<header id="app-bar">
    <a href="/" class="app-title-link">
        <img src="https://ragemi.com/s/top.png" alt="Ragemi" class="app-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
        <div class="app-title" style="display:none;">Ragemi</div>
    </a>
    <div class="top-bar-right">
        <button class="icon-btn" onclick="toggleTheme()" title="切换主题">
            <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
        </button>
        <a href="/" class="btn-secondary">
            <span class="material-symbols-outlined">home</span>
            首页
        </a>
    </div>
</header>

<!-- ===== Sub Navigation ===== -->
<div class="sub-nav">
    <a href="/" class="nav-btn">
        <span class="material-symbols-outlined">home</span>
        <span>主页</span>
    </a>
    <a href="/explore" class="nav-btn">
        <span class="material-symbols-outlined">explore</span>
        <span>发现</span>
    </a>
    <a href="/login" class="nav-btn">
        <span class="material-symbols-outlined">login</span>
        <span>登录</span>
    </a>
</div>

<!-- ===== 忘记密码表单 ===== -->
<div class="forgot-container">
    <div class="brand">
        <img src="https://ragemi.com/s/top.png" alt="Ragemi" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
        <h1 style="display:none;">Ragemi</h1>
        <p class="subtitle">₍^. .^₎⟆ 找回密码</p>
    </div>

    <?php if ($error): ?>
        <div class="error-msg">
            <span class="material-symbols-outlined">error</span>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-msg">
            <span class="material-symbols-outlined">check_circle</span>
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 'done'): ?>
        <!-- ===== 完成 ===== -->
        <div style="text-align:center;padding:12px 0;">
            <span class="material-symbols-outlined" style="font-size:64px;color:#27ae60;display:block;margin-bottom:12px;">check_circle</span>
            <p style="color:var(--ba-text-secondary);font-size:15px;line-height:1.8;">密码已重置成功！</p>
            <p style="color:var(--ba-text-muted);font-size:13px;margin-top:4px;">请使用新密码登录</p>
        </div>
        <div style="margin-top:16px;">
            <a href="/login" class="btn-full">
                <span class="material-symbols-outlined">login</span>
                去登录
            </a>
        </div>

    <?php elseif ($step === 'reset'): ?>
        <!-- ===== 步骤2：重置密码 ===== -->
        <form method="POST">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="email" value="<?php echo e($email); ?>">
            <input type="hidden" name="token" value="<?php echo e($token); ?>">

            <div style="padding:12px 14px;background:rgba(122,92,45,0.06);border-radius:10px;margin-bottom:16px;font-size:13px;color:var(--ba-text-secondary);">
                <span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:4px;">info</span>
                正在为 <strong><?php echo e($email); ?></strong> 重置密码
            </div>

            <div class="field-group">
                <label>新密码（至少6位）</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="resetPwd" required minlength="6" placeholder="输入新密码">
                    <button type="button" class="toggle-pwd" onclick="toggleResetPassword()">
                        <span class="material-symbols-outlined" id="resetPwdIcon">visibility_off</span>
                    </button>
                </div>
            </div>

            <div class="field-group">
                <label>确认密码</label>
                <div class="password-wrapper">
                    <input type="password" name="password_confirm" id="resetPwdConfirm" required minlength="6" placeholder="再次输入密码">
                    <button type="button" class="toggle-pwd" onclick="toggleResetPasswordConfirm()">
                        <span class="material-symbols-outlined" id="resetPwdConfirmIcon">visibility_off</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-full">
                <span class="material-symbols-outlined">check</span>
                重置密码
            </button>
        </form>

        <div class="back-home">
            <a href="/login">← 返回登录</a>
        </div>

    <?php else: ?>
        <!-- ===== 步骤1：请求重置 ===== -->
        <form method="POST">
            <input type="hidden" name="action" value="request">

            <div style="font-size:14px;color:var(--ba-text-secondary);margin-bottom:20px;line-height:1.6;">
                请输入你注册时使用的邮箱地址，我们将发送重置密码的链接。
            </div>

            <div class="field-group">
                <label>邮箱地址</label>
                <input type="email" name="email" required placeholder="请输入注册邮箱" value="<?php echo e($email); ?>">
            </div>

            <button type="submit" class="btn-full" id="resetBtn">
                <span class="material-symbols-outlined">send</span>
                发送重置链接
            </button>
        </form>

        <hr class="divider">

        <div class="back-home">
            <a href="/login">← 返回登录</a>
            <span style="color:var(--ba-text-muted);margin:0 8px;">·</span>
            <a href="/register">注册账号</a>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Footer ===== -->
<div class="ragemi-footer">
    <div class="footer-logo">
        <img src="https://ragemi.com/s/top.png" alt="Ragemi" class="footer-logo-img">
    </div>
    <div class="footer-copyright">
        © 2026 瑞格米工作室
    </div>
</div>

<script>
// ===== 主题切换 =====
function applyTheme(dark) {
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    const icon = document.getElementById('theme-icon');
    if (icon) icon.textContent = dark ? 'light_mode' : 'dark_mode';
}
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const isDark = current === 'dark';
    const next = !isDark;
    localStorage.setItem('ragemi_theme', next ? 'dark' : 'light');
    applyTheme(next);
}

(function() {
    const saved = localStorage.getItem('ragemi_theme');
    if (saved === 'dark') applyTheme(true);
    else if (saved === 'light') applyTheme(false);
    else if (window.matchMedia('(prefers-color-scheme: dark)').matches) applyTheme(true);
})();

// ===== 密码显示切换 =====
function toggleResetPassword() {
    const pwd = document.getElementById('resetPwd');
    const icon = document.getElementById('resetPwdIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.textContent = 'visibility';
    } else {
        pwd.type = 'password';
        icon.textContent = 'visibility_off';
    }
}

function toggleResetPasswordConfirm() {
    const pwd = document.getElementById('resetPwdConfirm');
    const icon = document.getElementById('resetPwdConfirmIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.textContent = 'visibility';
    } else {
        pwd.type = 'password';
        icon.textContent = 'visibility_off';
    }
}

// ===== 防止重复提交 =====
document.querySelector('form')?.addEventListener('submit', function() {
    const btn = document.getElementById('resetBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-symbols-outlined" style="animation:spin 0.8s linear infinite;">progress_activity</span> 发送中...';
    }
});

// ===== 添加旋转动画 =====
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

</body>
</html>