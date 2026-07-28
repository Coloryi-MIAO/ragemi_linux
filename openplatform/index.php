<?php
// openplatform/index.php - 开发者仪表盘
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// 使用独立样式（不加载 home.css）
$pageStyle = '/css/openplatform.css';

initSecurityHeaders();

$me = me();

// ---- 访问控制 ----
if (!$me) {
    header('Location: /login?redirect=' . urlencode('/openplatform'));
    exit;
}

// uid=1 默认开发者
if ($me['id'] == 1) {
    $isDeveloper = true;
} else {
    // 查询开发者申请状态
    $s = $pdo->prepare("SELECT status FROM developer_applications WHERE user_id = ?");
    $s->execute([$me['id']]);
    $app = $s->fetch();

    if (!$app) {
        // 未申请，跳转到申请页
        header('Location: /openplatform/register');
        exit;
    }

    if ($app['status'] === 'pending') {
        // 审核中
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>审核中</title><link rel="stylesheet" href="/css/common.css"><link rel="stylesheet" href="/css/openplatform.css"></head>';
        echo '<body style="text-align:center;padding:60px 20px;"><h1>⏳ 等待审核</h1><p>您的开发者申请正在审核中，请耐心等待。</p><a href="/" class="btn-primary">返回首页</a></body></html>';
        exit;
    }

    if ($app['status'] === 'rejected') {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>申请被拒</title><link rel="stylesheet" href="/css/common.css"><link rel="stylesheet" href="/css/openplatform.css"></head>';
        echo '<body style="text-align:center;padding:60px 20px;"><h1>❌ 申请被拒绝</h1><p>您的开发者申请已被拒绝，请联系管理员。</p><a href="/" class="btn-primary">返回首页</a></body></html>';
        exit;
    }

    // status === 'approved'
    $isDeveloper = true;
}

// ---- 统计（仅开发者可见） ----
$s = $pdo->prepare("SELECT COUNT(*) FROM oauth_apps WHERE user_id = ? AND status != 'deleted'");
$s->execute([$me['id']]);
$appCount = $s->fetchColumn();

$s = $pdo->prepare("SELECT COUNT(*) FROM bots WHERE owner_id = ? AND status != 'deleted'");
$s->execute([$me['id']]);
$botCount = $s->fetchColumn();

$title = '开发者平台 - 瑞格米';
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
</head>
<body>

<!-- ===== 粒子背景（独立简化版） ===== -->
<div class="op-bg"></div>

<!-- ===== 顶部导航栏（开发者专用） ===== -->
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

<!-- ===== 主内容区 ===== -->
<div class="op-container">
    <div class="op-dashboard">
        <h1>仪表盘</h1>
        <p class="op-sub">管理你的应用和 Bot</p>

        <div class="op-stats">
            <div class="op-stat-card">
                <div class="op-stat-number"><?php echo $appCount; ?></div>
                <div class="op-stat-label">应用</div>
            </div>
            <div class="op-stat-card">
                <div class="op-stat-number"><?php echo $botCount; ?></div>
                <div class="op-stat-label">Bot</div>
            </div>
        </div>

        <div class="op-actions">
            <a href="/openplatform/apps" class="op-btn op-btn-primary">
                <span class="material-symbols-outlined">apps</span> 我的应用
            </a>
            <a href="/openplatform/app-create" class="op-btn op-btn-secondary">
                <span class="material-symbols-outlined">add_circle</span> 创建应用
            </a>
            <a href="/openplatform/bots" class="op-btn op-btn-primary">
                <span class="material-symbols-outlined">smart_toy</span> 我的 Bot
            </a>
            <a href="/openplatform/bot-create" class="op-btn op-btn-secondary">
                <span class="material-symbols-outlined">add_circle</span> 创建 Bot
            </a>
            <a href="/" class="op-btn op-btn-ghost">
                <span class="material-symbols-outlined">home</span> 回到网站
            </a>
        </div>

        <div class="op-docs">
            <h3>📖 开发文档</h3>
            <p>查看 <a href="/bots/docs">Bot API 文档</a> 和 <a href="/oauth/docs">OAuth 2.0 文档</a></p>
        </div>
    </div>
</div>

<!-- ===== Footer ===== -->
<footer class="op-footer">
    <div class="op-footer-inner">
        <span>© <?php echo date('Y'); ?> 瑞格米 · 开发者平台</span>
        <a href="/privacy">隐私政策</a>
        <a href="/terms">服务条款</a>
    </div>
</footer>

</body>
</html>