<?php
// openplatform/bots.php - 我的 Bot
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageStyle = '/css/openplatform.css';
initSecurityHeaders();

$me = me();
if (!$me) {
    header('Location: /login?redirect=' . urlencode('/openplatform/bots'));
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

// ---- 处理删除操作 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $botId = (int)$_POST['bot_id'];
    if ($botId > 0) {
        $s = $pdo->prepare("UPDATE bots SET status = 'deleted' WHERE id = ? AND owner_id = ?");
        $s->execute([$botId, $me['id']]);
        header('Location: /openplatform/bots');
        exit;
    }
}

// ---- 获取 Bot 列表 ----
$s = $pdo->prepare("SELECT * FROM bots WHERE owner_id = ? AND status != 'deleted' ORDER BY id DESC");
$s->execute([$me['id']]);
$bots = $s->fetchAll();

$title = '我的 Bot - 开发者平台';
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
        .bot-list { margin-top: 24px; }
        .bot-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .bot-item .info { flex: 1; min-width: 200px; }
        .bot-item .name { font-weight: 600; color: #e8f0f8; font-size: 16px; }
        .bot-item .api-key { font-size: 12px; color: #7a8a9a; font-family: 'JetBrains Mono', monospace; }
        .bot-item .status { font-size: 12px; padding: 2px 10px; border-radius: 12px; background: rgba(255,255,255,0.06); }
        .status-approved { color: #60b080; background: rgba(96,176,128,0.1); }
        .status-pending { color: #e8b060; background: rgba(232,176,96,0.1); }
        .status-rejected { color: #e87060; background: rgba(232,112,96,0.1); }
        .status-disabled { color: #7a8a9a; background: rgba(255,255,255,0.04); }
        .bot-item .actions { display: flex; gap: 8px; }
        .bot-item .actions a, .bot-item .actions button { padding: 4px 12px; border-radius: 6px; font-size: 13px; border: none; cursor: pointer; background: rgba(255,255,255,0.06); color: #c8d8e8; transition: 0.2s; font-family: inherit; }
        .bot-item .actions a:hover, .bot-item .actions button:hover { background: rgba(255,255,255,0.12); }
        .bot-item .actions .delete { color: #e87060; }
        .bot-item .actions .delete:hover { background: rgba(232,112,96,0.15); }
        .empty-state { text-align: center; padding: 40px 20px; color: #7a8a9a; }
        .empty-state .material-symbols-outlined { font-size: 48px; opacity: 0.3; display: block; margin-bottom: 12px; }
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
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <h1>我的 Bot</h1>
            <p class="op-sub">管理你的自动化 Bot</p>
        </div>
        <a href="/openplatform/bot-create" class="op-btn op-btn-primary">
            <span class="material-symbols-outlined">add_circle</span> 创建 Bot
        </a>
    </div>

    <div class="bot-list">
        <?php if ($bots): ?>
            <?php foreach ($bots as $bot): ?>
                <div class="bot-item">
                    <div class="info">
                        <div class="name"><?php echo e($bot['name']); ?></div>
                        <div class="api-key">API Key: <?php echo e($bot['api_key']); ?></div>
                        <div style="font-size:13px;color:#8a9aaa;">描述: <?php echo e($bot['description'] ?: '-'); ?></div>
                        <span class="status status-<?php echo $bot['status']; ?>"><?php echo $bot['status']; ?></span>
                    </div>
                    <div class="actions">
                        <?php if ($bot['status'] === 'approved'): ?>
                            <a href="/bots/docs?bot_id=<?php echo $bot['id']; ?>" target="_blank">文档</a>
                        <?php endif; ?>
                        <?php if ($bot['status'] !== 'approved'): ?>
                            <span style="color:#5a6a7a;font-size:13px;">(待审核)</span>
                        <?php endif; ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('确定删除此 Bot？');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                            <button type="submit" class="delete">删除</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <span class="material-symbols-outlined">smart_toy</span>
                <p>还没有创建任何 Bot</p>
                <a href="/openplatform/bot-create" class="op-btn op-btn-primary" style="display:inline-flex;margin-top:8px;">创建第一个 Bot</a>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;">
        <a href="/openplatform" class="op-btn op-btn-ghost">← 返回仪表盘</a>
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