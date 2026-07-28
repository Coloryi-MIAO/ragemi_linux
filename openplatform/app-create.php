<?php
// openplatform/app-create.php - 创建 OAuth 应用
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageStyle = '/css/openplatform.css';
initSecurityHeaders();

$me = me();
if (!$me) {
    header('Location: /login?redirect=' . urlencode('/openplatform/app-create'));
    exit;
}
if ($me['id'] != 1) {
    $s = $pdo->prepare("SELECT status FROM developer_applications WHERE user_id = ?");
    $s->execute([$me['id']]);
    $app = $s->fetch();
    if (!$app || $app['status'] !== 'approved') {
        header('Location: /openplatform/register');
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $redirect_uri = trim($_POST['redirect_uri'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name) || empty($redirect_uri)) {
        $error = '请填写应用名称和回调地址';
    } elseif (!filter_var($redirect_uri, FILTER_VALIDATE_URL)) {
        $error = '回调地址格式不正确';
    } else {
        $client_id = bin2hex(random_bytes(16));
        $client_secret = bin2hex(random_bytes(32));
        $s = $pdo->prepare("INSERT INTO oauth_apps (name, description, client_id, client_secret, redirect_uri, user_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
        if ($s->execute([$name, $description, $client_id, $client_secret, $redirect_uri, $me['id']])) {
            $success = '应用已创建，等待管理员审核。';
            // 可以展示 client_id 和 client_secret，但仅显示一次
            // 为安全起见，只显示 client_id，client_secret 后续可在编辑页查看
        } else {
            $error = '创建失败，请稍后再试';
        }
    }
}

$title = '创建应用 - 开发者平台';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/openplatform.css">
    <style>
        .op-form { max-width: 600px; margin: 0 auto; }
        .op-form label { display: block; font-size: 13px; font-weight: 500; color: #b0c0d0; margin-bottom: 4px; margin-top: 16px; }
        .op-form input, .op-form textarea { width: 100%; padding: 10px 12px; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; background: rgba(0,0,0,0.3); color: #d0d8e0; font-size: 14px; font-family: inherit; outline: none; transition: border 0.2s; }
        .op-form input:focus, .op-form textarea:focus { border-color: #6a9fd8; }
        .op-form .hint { font-size: 12px; color: #5a6a7a; margin-top: 4px; }
        .op-form .error { color: #e87060; background: rgba(232,112,96,0.1); padding: 10px 12px; border-radius: 6px; margin: 12px 0; }
        .op-form .success { color: #60b080; background: rgba(96,176,128,0.1); padding: 10px 12px; border-radius: 6px; margin: 12px 0; }
        .op-form .secret-info { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 12px 16px; margin: 12px 0; font-size: 14px; }
        .op-form .secret-info code { font-family: 'JetBrains Mono', monospace; color: #6a9fd8; word-break: break-all; }
    </style>
</head>
<body>
<div class="op-bg"></div>

<header class="op-header">
    <div class="op-header-inner">
        <a href="/openplatform" class="op-logo">
            <span class="material-symbols-outlined">developer_mode</span>
            开发者平台
        </a>
        <div class="op-header-right">
            <span class="op-user"><?php echo e($me['display_name'] ?: $me['username']); ?></span>
            <a href="/openplatform/logout" class="op-btn op-btn-outline">退出</a>
        </div>
    </div>
</header>

<div class="op-container">
    <div class="op-form">
        <h1>创建 OAuth 应用</h1>
        <p class="op-sub">应用需要管理员审核通过后方可使用</p>

        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
            <div style="margin-top:16px;">
                <a href="/openplatform/apps" class="op-btn op-btn-primary">查看我的应用</a>
            </div>
        <?php else: ?>
            <form method="post">
                <label>应用名称 *</label>
                <input type="text" name="name" required placeholder="例如：我的网站登录" value="<?php echo e($_POST['name'] ?? ''); ?>">

                <label>回调地址 *</label>
                <input type="url" name="redirect_uri" required placeholder="https://example.com/callback" value="<?php echo e($_POST['redirect_uri'] ?? ''); ?>">
                <div class="hint">用户授权后跳转的地址，必须与注册时一致</div>

                <label>应用描述（可选）</label>
                <textarea name="description" rows="3" placeholder="简单描述应用用途"><?php echo e($_POST['description'] ?? ''); ?></textarea>

                <button type="submit" class="op-btn op-btn-primary" style="width:100%;justify-content:center;margin-top:20px;">创建应用</button>
            </form>
            <div style="margin-top:16px;">
                <a href="/openplatform/apps" class="op-btn op-btn-ghost">← 返回应用列表</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="op-footer">
    <div class="op-footer-inner">
        <span>© <?php echo date('Y'); ?> 瑞格米 · 开发者平台</span>
        <a href="/privacy">隐私政策</a>
        <a href="/terms">服务条款</a>
    </div>
</footer>
</body>
</html>