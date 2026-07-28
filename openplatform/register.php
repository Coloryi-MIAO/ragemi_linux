<?php
// openplatform/register.php - 申请成为开发者
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageStyle = '/css/openplatform.css';
initSecurityHeaders();

$me = me();
if (!$me) {
    header('Location: /login?redirect=' . urlencode('/openplatform/register'));
    exit;
}

// uid=1 默认开发者，无需申请
if ($me['id'] == 1) {
    header('Location: /openplatform');
    exit;
}

$error = '';
$success = '';

// 检查是否已有申请
$s = $pdo->prepare("SELECT * FROM developer_applications WHERE user_id = ?");
$s->execute([$me['id']]);
$existing = $s->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = trim($_POST['company'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($company) || empty($contact_email) || empty($reason)) {
        $error = '请填写完整信息';
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } else {
        if ($existing) {
            $s = $pdo->prepare("UPDATE developer_applications SET company_name=?, contact_email=?, website=?, reason=?, status='pending', updated_at=NOW() WHERE id=?");
            $s->execute([$company, $contact_email, $website, $reason, $existing['id']]);
            $success = '申请已更新，等待审核。';
        } else {
            $s = $pdo->prepare("INSERT INTO developer_applications (user_id, company_name, contact_email, website, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $s->execute([$me['id'], $company, $contact_email, $website, $reason]);
            $success = '申请已提交，等待审核。';
        }
    }
}

$title = '申请开发者 - 瑞格米';
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
        .op-auth-card { max-width: 520px; margin: 40px auto; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 32px; }
        .op-auth-card h2 { font-size: 22px; color: #e8f0f8; margin-bottom: 4px; }
        .op-auth-card .sub { color: #7a8a9a; font-size: 14px; margin-bottom: 24px; }
        .op-auth-card label { display: block; font-size: 13px; font-weight: 500; color: #b0c0d0; margin-bottom: 4px; }
        .op-auth-card input, .op-auth-card textarea { width: 100%; padding: 10px 12px; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; background: rgba(0,0,0,0.3); color: #d0d8e0; font-size: 14px; font-family: inherit; outline: none; transition: border 0.2s; }
        .op-auth-card input:focus, .op-auth-card textarea:focus { border-color: #6a9fd8; }
        .op-auth-card .op-btn { width: 100%; justify-content: center; margin-top: 8px; }
        .op-auth-card .error { color: #e87060; background: rgba(232,112,96,0.1); padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .op-auth-card .success { color: #60b080; background: rgba(96,176,128,0.1); padding: 10px 12px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .op-auth-card .hint { font-size: 13px; color: #5a6a7a; margin-top: 12px; text-align: center; }
        .op-auth-card .hint a { color: #6a9fd8; }
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
    <div class="op-auth-card">
        <h2>申请开发者</h2>
        <p class="sub">成为开发者后，你可以创建 OAuth 应用和 Bot</p>

        <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>

        <?php if ($existing && $existing['status'] === 'pending'): ?>
            <p style="color:#b0c0d0;text-align:center;padding:20px 0;">⏳ 您的申请正在审核中，请耐心等待。</p>
        <?php elseif ($existing && $existing['status'] === 'rejected'): ?>
            <p style="color:#e87060;text-align:center;padding:20px 0;">❌ 您的申请已被拒绝，如需重新申请请联系管理员。</p>
        <?php else: ?>
            <form method="post">
                <div style="margin-bottom:16px;">
                    <label>公司/组织名称</label>
                    <input type="text" name="company" required value="<?php echo e($existing['company_name'] ?? ''); ?>">
                </div>
                <div style="margin-bottom:16px;">
                    <label>联系邮箱</label>
                    <input type="email" name="contact_email" required value="<?php echo e($existing['contact_email'] ?? $me['email']); ?>">
                </div>
                <div style="margin-bottom:16px;">
                    <label>网站（可选）</label>
                    <input type="url" name="website" value="<?php echo e($existing['website'] ?? ''); ?>">
                </div>
                <div style="margin-bottom:16px;">
                    <label>申请理由</label>
                    <textarea name="reason" rows="4" required><?php echo e($existing['reason'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="op-btn op-btn-primary">提交申请</button>
            </form>
        <?php endif; ?>
        <div class="hint">
            <a href="/">返回首页</a> · <a href="/openplatform">开发者平台</a>
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