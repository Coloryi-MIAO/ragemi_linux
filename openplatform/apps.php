<?php
// openplatform/apps.php - 我的应用
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageStyle = '/css/openplatform.css';
initSecurityHeaders();

$me = me();

// ---- 访问控制 ----
if (!$me) {
    header('Location: /login?redirect=' . urlencode('/openplatform/apps'));
    exit;
}

// 检查开发者身份（uid=1 默认开发者）
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
    $appId = (int)$_POST['app_id'];
    if ($appId > 0) {
        $s = $pdo->prepare("UPDATE oauth_apps SET status = 'deleted' WHERE id = ? AND user_id = ?");
        $s->execute([$appId, $me['id']]);
        // 刷新页面
        header('Location: /openplatform/apps');
        exit;
    }
}

// ---- 获取应用列表 ----
$s = $pdo->prepare("SELECT * FROM oauth_apps WHERE user_id = ? AND status != 'deleted' ORDER BY id DESC");
$s->execute([$me['id']]);
$apps = $s->fetchAll();

$title = '我的应用 - 开发者平台';
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
        .app-list { margin-top: 24px; }
        .app-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .app-item .info { flex: 1; min-width: 200px; }
        .app-item .name { font-weight: 600; color: #e8f0f8; font-size: 16px; }
        .app-item .client-id { font-size: 12px; color: #7a8a9a; font-family: 'JetBrains Mono', monospace; }
        .app-item .status { font-size: 12px; padding: 2px 10px; border-radius: 12px; background: rgba(255,255,255,0.06); }
        .status-approved { color: #60b080; background: rgba(96,176,128,0.1); }
        .status-pending { color: #e8b060; background: rgba(232,176,96,0.1); }
        .status-rejected { color: #e87060; background: rgba(232,112,96,0.1); }
        .status-disabled { color: #7a8a9a; background: rgba(255,255,255,0.04); }
        .app-item .actions { display: flex; gap: 8px; }
        .app-item .actions a, .app-item .actions button { padding: 4px 12px; border-radius: 6px; font-size: 13px; border: none; cursor: pointer; background: rgba(255,255,255,0.06); color: #c8d8e8; transition: 0.2s; font-family: inherit; }
        .app-item .actions a:hover, .app-item .actions button:hover { background: rgba(255,255,255,0.12); }
        .app-item .actions .delete { color: #e87060; }
        .app-item .actions .delete:hover { background: rgba(232,112,96,0.15); }
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
            <h1>我的应用</h1>
            <p class="op-sub">管理你的 OAuth 应用</p>
        </div>
        <a href="/openplatform/app-create" class="op-btn op-btn-primary">
            <span class="material-symbols-outlined">add_circle</span> 创建应用
        </a>
    </div>

    <div class="app-list">
        <?php if ($apps): ?>
            <?php foreach ($apps as $app): ?>
                <div class="app-item">
                    <div class="info">
                        <div class="name"><?php echo e($app['name']); ?></div>
                        <div class="client-id">Client ID: <?php echo e($app['client_id']); ?></div>
                        <div style="font-size:13px;color:#8a9aaa;">回调: <?php echo e($app['redirect_uri']); ?></div>
                        <span class="status status-<?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                    </div>
                    <div class="actions">
                        <a href="/openplatform/app-edit?id=<?php echo $app['id']; ?>">编辑</a>
                        <?php if ($app['status'] !== 'approved'): ?>
                            <span style="color:#5a6a7a;font-size:13px;">(待审核)</span>
                        <?php endif; ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('确定删除此应用？此操作不可恢复。');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                            <button type="submit" class="delete">删除</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <span class="material-symbols-outlined">apps</span>
                <p>还没有创建任何应用</p>
                <a href="/openplatform/app-create" class="op-btn op-btn-primary" style="display:inline-flex;margin-top:8px;">创建第一个应用</a>
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