<?php
// openplatform/app-edit.php - 编辑 OAuth 应用
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageStyle = '/css/openplatform.css';
initSecurityHeaders();

$me = me();
if (!$me) {
    header('Location: /login?redirect=' . urlencode('/openplatform/app-edit'));
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

$appId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($appId <= 0) {
    header('Location: /openplatform/apps');
    exit;
}

// 获取应用信息，并校验所有权
$s = $pdo->prepare("SELECT * FROM oauth_apps WHERE id = ? AND user_id = ? AND status != 'deleted'");
$s->execute([$appId, $me['id']]);
$app = $s->fetch();
if (!$app) {
    header('Location: /openplatform/apps');
    exit;
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
        $s = $pdo->prepare("UPDATE oauth_apps SET name = ?, redirect_uri = ?, description = ? WHERE id = ? AND user_id = ?");
        if ($s->execute([$name, $redirect_uri, $description, $appId, $me['id']])) {
            $success = '应用信息已更新';
            // 刷新应用数据
            $s = $pdo->prepare("SELECT * FROM oauth_apps WHERE id = ? AND user_id = ? AND status != 'deleted'");
            $s->execute([$appId, $me['id']]);
            $app = $s->fetch();
        } else {
            $error = '更新失败，请稍后再试';
        }
    }
}

$title = '编辑应用 - 开发者平台';
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
        <h1>编辑应用</h1>
        <p class="op-sub">修改应用信息</p>

        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>

        <div class="secret-info">
            <div><strong>Client ID</strong><br><code><?php echo e($app['client_id']); ?></code></div>
            <div style="margin-top:8px;"><strong>Client Secret</strong><br><code><?php echo e($app['client_secret']); ?></code></div>
            <div style="margin-top:8px;"><strong>状态</strong><br><span class="status status-<?php echo $app['status']; ?>"><?php echo $app['status']; ?></span></div>
        </div>

        <form method="post">
            <label>应用名称 *</label>
            <input type="text" name="name" required value="<?php echo e($app['name']); ?>">

            <label>回调地址 *</label>
            <input type="url" name="redirect_uri" required value="<?php echo e($app['redirect_uri']); ?>">
            <div class="hint">用户授权后跳转的地址，修改后需要重新审核</div>

            <label>应用描述（可选）</label>
            <textarea name="description" rows="3"><?php echo e($app['description']); ?></textarea>

            <button type="submit" class="op-btn op-btn-primary" style="width:100%;justify-content:center;margin-top:20px;">保存修改</button>
        </form>
        <div style="margin-top:16px;">
            <a href="/openplatform/apps" class="op-btn op-btn-ghost">← 返回应用列表</a>
        </div>
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