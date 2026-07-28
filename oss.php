<?php
// oss.php - OAuth 2.0 授权入口（简洁风格，类似 Google 登录页）
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 只设置基本安全头，不设置 CSP（避免干扰表单提交）
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

$me = me();

// 获取请求参数
$client_id = $_GET['client_id'] ?? '';
$redirect_uri = $_GET['redirect_uri'] ?? '';
$response_type = $_GET['response_type'] ?? 'code';
$scope = $_GET['scope'] ?? 'basic';
$state = $_GET['state'] ?? '';

// 验证 client_id
if (empty($client_id)) {
    die('缺少 client_id 参数');
}

// 查找应用（仅审核通过的）
$s = $pdo->prepare("SELECT * FROM oauth_apps WHERE client_id = ? AND status = 'approved'");
$s->execute([$client_id]);
$app = $s->fetch();

if (!$app) {
    die('应用不存在或未审核通过');
}

// 验证 redirect_uri
if (empty($redirect_uri)) {
    $redirect_uri = $app['redirect_uri'];
} elseif ($redirect_uri !== $app['redirect_uri']) {
    die('redirect_uri 不匹配');
}

// 如果用户未登录，跳转到登录页
if (!$me) {
    $_SESSION['oauth_return_url'] = $_SERVER['REQUEST_URI'];
    header('Location: /login');
    exit;
}

// 处理用户授权决策
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = $_POST['decision'] ?? '';
    if ($decision === 'approve') {
        // 生成授权码
        $code = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 300);
        $s = $pdo->prepare("INSERT INTO oauth_codes (code, client_id, user_id, redirect_uri, expires_at) VALUES (?, ?, ?, ?, ?)");
        $s->execute([$code, $client_id, $me['id'], $redirect_uri, $expires]);

        $params = ['code' => $code, 'state' => $state];
        $url = $redirect_uri . (strpos($redirect_uri, '?') === false ? '?' : '&') . http_build_query($params);
        header('Location: ' . $url);
        exit;
    } else {
        $params = ['error' => 'access_denied', 'state' => $state];
        $url = $redirect_uri . (strpos($redirect_uri, '?') === false ? '?' : '&') . http_build_query($params);
        header('Location: ' . $url);
        exit;
    }
}

// 获取站点信息
$app_name = htmlspecialchars($app['name']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授权确认 · 瑞格米</title>
    <style>
        /* ============================================================
           简洁卡片风格（类似 Google 登录页）
           主题色跟随 index（使用 CSS 变量，由 config.php 定义）
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Microsoft YaHei", "PingFang SC", "Helvetica Neue", sans-serif;
            background: var(--ba-bg, #f0f4f8);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            color: var(--ba-text, #4a6075);
        }
        .oss-container {
            max-width: 400px;
            width: 100%;
            background: var(--ba-card-bg, rgba(255,255,255,0.55));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 40px 32px 32px;
            box-shadow: var(--ba-card-shadow, 0 2px 12px rgba(150,170,190,0.12));
            border: 1px solid var(--ba-card-border, rgba(255,255,255,0.8));
            transition: box-shadow 0.3s ease;
        }
        .oss-container:hover {
            box-shadow: var(--ba-card-shadow-hover, 0 8px 28px rgba(150,170,190,0.22));
        }
        .oss-logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .oss-logo img {
            height: 40px;
            width: auto;
            display: inline-block;
            object-fit: contain;
        }
        .oss-title {
            text-align: center;
            font-size: 22px;
            font-weight: 500;
            color: var(--ba-text, #4a6075);
            margin-bottom: 4px;
        }
        .oss-sub {
            text-align: center;
            font-size: 14px;
            color: var(--ba-text-muted, #8a9db0);
            margin-bottom: 24px;
        }
        .oss-app-name {
            text-align: center;
            font-size: 16px;
            font-weight: 500;
            color: var(--ba-accent, #7A5C2D);
            background: rgba(122,92,45,0.06);
            padding: 8px 16px;
            border-radius: 12px;
            display: inline-block;
            margin: 0 auto 20px;
            display: table;
            border: 1px solid rgba(122,92,45,0.08);
        }
        .oss-permission-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
        }
        .oss-permission-list li {
            padding: 6px 0;
            font-size: 14px;
            color: var(--ba-text-secondary, #5a6f84);
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(150,170,190,0.06);
        }
        .oss-permission-list li:last-child {
            border-bottom: none;
        }
        .oss-permission-list li::before {
            content: "✓";
            color: var(--ba-accent, #7A5C2D);
            font-weight: 700;
            font-size: 16px;
        }
        .oss-user {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255,255,255,0.4);
            border-radius: 16px;
            padding: 10px 16px;
            margin-bottom: 20px;
            border: 1px solid rgba(200,180,160,0.1);
        }
        .oss-user .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #e0d8d0;
            flex-shrink: 0;
            border: 2px solid var(--ba-accent-light, #c9a06a);
        }
        .oss-user .info {
            flex: 1;
        }
        .oss-user .name {
            font-weight: 500;
            font-size: 15px;
            color: var(--ba-text, #4a6075);
        }
        .oss-user .email {
            font-size: 12px;
            color: var(--ba-text-muted, #8a9db0);
        }
        .oss-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn-approve {
            width: 100%;
            padding: 12px 0;
            border: none;
            border-radius: 30px;
            background: var(--ba-accent, #7A5C2D);
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            font-family: inherit;
            box-shadow: 0 2px 8px rgba(122,92,45,0.15);
        }
        .btn-approve:hover {
            background: var(--ba-accent-dark, #5c3e1f);
            transform: translateY(-1px);
        }
        .btn-approve:active {
            transform: scale(0.98);
        }
        .btn-deny {
            width: 100%;
            padding: 10px 0;
            border: none;
            background: transparent;
            color: var(--ba-text-muted, #8a9db0);
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
            transition: color 0.2s;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .btn-deny:hover {
            color: var(--ba-accent, #7A5C2D);
        }
        .oss-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--ba-text-muted, #8a9db0);
            border-top: 1px solid rgba(150,170,190,0.08);
            padding-top: 16px;
        }
        .error-box {
            background: rgba(212,160,160,0.1);
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            text-align: center;
            margin-bottom: 16px;
            border: 1px solid rgba(212,160,160,0.15);
        }
        .error-box a {
            color: var(--ba-accent, #7A5C2D);
            font-weight: 500;
        }
        @media (max-width: 480px) {
            .oss-container { padding: 28px 18px 24px; }
        }
    </style>
</head>
<body>

<div class="oss-container">
    <div class="oss-logo">
        <img src="https://ragemi.com/s/top.png" alt="瑞格米">
    </div>
    <div class="oss-title">授权确认</div>
    <div class="oss-sub">允许第三方应用访问您的瑞格米账号</div>

    <?php if ($error ?? false): ?>
        <div class="error-box">
            <?php echo htmlspecialchars($error); ?>
            <br><a href="/login">去登录</a> · <a href="/register">注册</a>
        </div>
    <?php else: ?>
        <div style="text-align:center;">
            <span class="oss-app-name"><?php echo $app_name; ?></span>
        </div>

        <ul class="oss-permission-list">
            <li>查看您的公开个人信息（昵称、头像、用户名）</li>
            <li>查看您的邮箱地址（即将支持）</li>
            <?php if (strpos($scope, 'post') !== false): ?>
                <li>以您的名义发布内容</li>
            <?php endif; ?>
        </ul>

        <div class="oss-user">
            <img src="<?php echo getAvatarUrl($me['avatar']); ?>" class="avatar" onerror="this.src='/assets/default-avatar.png'">
            <div class="info">
                <div class="name"><?php echo htmlspecialchars($me['display_name'] ?: $me['username']); ?></div>
                <div class="email">@<?php echo htmlspecialchars($me['subdomain']); ?></div>
            </div>
        </div>

        <div class="oss-actions">
            <form method="post" id="ossForm">
                <input type="hidden" name="decision" value="approve">
                <button type="submit" class="btn-approve">同意授权</button>
            </form>
            <form method="post">
                <input type="hidden" name="decision" value="deny">
                <button type="submit" class="btn-deny">拒绝</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="oss-footer">瑞格米 · 安全 · 可信</div>
</div>

</body>
</html>