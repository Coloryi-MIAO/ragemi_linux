<?php
// openplatform/bot-create.php - 创建 Bot
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageStyle = '/css/openplatform.css';
initSecurityHeaders();

$me = me();
if (!$me) {
    header('Location: /login?redirect=' . urlencode('/openplatform/bot-create'));
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
    $description = trim($_POST['description'] ?? '');
    $webhook_url = trim($_POST['webhook_url'] ?? '');

    if (empty($name)) {
        $error = '请填写 Bot 名称';
    } else {
        // 生成唯一的 API Key
        $api_key = 'ragemi_' . bin2hex(random_bytes(24));
        $s = $pdo->prepare("INSERT INTO bots (name, description, api_key, webhook_url, owner_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        if ($s->execute([$name, $description, $api_key, $webhook_url, $me['id']])) {
            $success = 'Bot 已创建，等待管理员审核。';
        } else {
            $error = '创建失败，请稍后再试';
        }
    }
}

$title = '创建 Bot - 开发者平台';
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
        <h1>创建 Bot</h1>
        <p class="op-sub">Bot 需要管理员审核通过后方可使用 API</p>

        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
            <div style="margin-top:16px;">
                <a href="/openplatform/bots" class="op-btn op-btn-primary">查看我的 Bot</a>
            </div>
        <?php else: ?>
            <form method="post">
                <label>Bot 名称 *</label>
                <input type="text" name="name" required placeholder="例如：通知机器人" value="<?php echo e($_POST['name'] ?? ''); ?>">

                <label>描述（可选）</label>
                <textarea name="description" rows="3" placeholder="简单描述 Bot 功能"><?php echo e($_POST['description'] ?? ''); ?></textarea>

                <label>Webhook URL（可选）</label>
                <input type="url" name="webhook_url" placeholder="https://example.com/webhook" value="<?php echo e($_POST['webhook_url'] ?? ''); ?>">
                <div class="hint">接收平台事件推送的地址</div>

                <button type="submit" class="op-btn op-btn-primary" style="width:100%;justify-content:center;margin-top:20px;">创建 Bot</button>
            </form>
            <div style="margin-top:16px;">
                <a href="/openplatform/bots" class="op-btn op-btn-ghost">← 返回 Bot 列表</a>
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