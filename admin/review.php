<?php
// admin/review.php - 审核队列（修复版）
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

initSecurityHeaders();

$me = me();
if (!$me || !isAdmin($me)) {
    header('Location: /login');
    exit;
}

$error = '';
$success = '';

// 处理审核操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 验证 CSRF
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        $error = 'CSRF 验证失败，请刷新页面重试';
    } else {
        $id = (int)$_POST['id'];
        $type = $_POST['type'] ?? '';
        $status = $_POST['status'] ?? '';

        if (!in_array($status, ['approved', 'rejected'])) {
            $error = '无效状态';
        } elseif ($id <= 0) {
            $error = '无效 ID';
        } else {
            try {
                switch ($type) {
                    case 'developer':
                        $s = $pdo->prepare("UPDATE developer_applications SET status = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
                        $s->execute([$status, $me['id'], $id]);
                        if ($s->rowCount() > 0) {
                            $success = "开发者申请已 {$status}";
                        } else {
                            $error = '未找到该申请或状态未改变';
                        }
                        break;
                    case 'oauth':
                        $s = $pdo->prepare("UPDATE oauth_apps SET status = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
                        $s->execute([$status, $me['id'], $id]);
                        if ($s->rowCount() > 0) {
                            $success = "OAuth 应用已 {$status}";
                        } else {
                            $error = '未找到该应用或状态未改变';
                        }
                        break;
                    case 'bot':
                        $s = $pdo->prepare("UPDATE bots SET status = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
                        $s->execute([$status, $me['id'], $id]);
                        if ($s->rowCount() > 0) {
                            $success = "Bot 已 {$status}";
                        } else {
                            $error = '未找到该 Bot 或状态未改变';
                        }
                        break;
                    default:
                        $error = '无效类型';
                }
            } catch (PDOException $e) {
                error_log('[review.php] DB Error: ' . $e->getMessage());
                $error = '数据库错误，请查看日志';
            }
        }
    }

    // 处理完成后重定向到当前页面，避免重复提交
    if ($success || $error) {
        // 可重定向刷新，但为了显示消息，保留当前页面
        // 如果需要刷新，使用 header('Location: ' . $_SERVER['REQUEST_URI']);
    }
}

// 获取待审核列表（仅 pending 状态）
$developers = $pdo->query("SELECT d.*, u.username, u.display_name FROM developer_applications d JOIN users u ON d.user_id = u.id WHERE d.status = 'pending' ORDER BY d.created_at ASC")->fetchAll();
$oauthApps = $pdo->query("SELECT o.*, u.username, u.display_name FROM oauth_apps o JOIN users u ON o.user_id = u.id WHERE o.status = 'pending' ORDER BY o.created_at ASC")->fetchAll();
$bots = $pdo->query("SELECT b.*, u.username, u.display_name FROM bots b JOIN users u ON b.owner_id = u.id WHERE b.status = 'pending' ORDER BY b.created_at ASC")->fetchAll();

$title = '审核队列 - 管理后台';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/admin/css/admin.css">
    <style>
        .review-item { border-bottom: 1px solid rgba(150,170,190,0.1); padding: 14px 0; }
        .review-item .meta { color: var(--ba-text-muted); font-size: 13px; }
        .review-item .actions { margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap; }
        .review-item .actions .btn-approve { background: #27ae60; color: #fff; border: none; padding: 4px 16px; border-radius: 4px; cursor: pointer; font-family: inherit; }
        .review-item .actions .btn-approve:hover { background: #1e8449; }
        .review-item .actions .btn-reject { background: #e74c3c; color: #fff; border: none; padding: 4px 16px; border-radius: 4px; cursor: pointer; font-family: inherit; }
        .review-item .actions .btn-reject:hover { background: #c0392b; }
        .alert-error { background: rgba(231,76,60,0.1); color: #e74c3c; padding: 10px 14px; border-radius: 6px; margin-bottom: 12px; border: 1px solid rgba(231,76,60,0.15); }
        .alert-success { background: rgba(39,174,96,0.1); color: #27ae60; padding: 10px 14px; border-radius: 6px; margin-bottom: 12px; border: 1px solid rgba(39,174,96,0.15); }
    </style>
</head>
<body>
<div class="particle-bg" id="particleBg"></div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<header id="app-bar">
    <div style="display:flex;align-items:center;gap:10px;">
        <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()" aria-label="菜单">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <a href="/" class="app-title-link">
            <img src="https://ragemi.com/s/top.png" alt="瑞格米" class="app-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
            <div class="app-title" style="display:none;">瑞格米</div>
        </a>
    </div>
    <div class="top-bar-right">
        <a href="/" class="btn-text"><span class="material-symbols-outlined">home</span> 首页</a>
        <button class="icon-btn" onclick="location.href='/@<?php echo e($me['subdomain']); ?>'">
            <img src="<?php echo getAvatarUrl($me['avatar']); ?>" class="avatar-small" onerror="this.src='/assets/default-avatar.png'">
        </button>
    </div>
</header>

<?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<div class="main-wrapper">
    <main class="main-content">
        <div class="admin-header">
            <h1>审核队列</h1>
            <p class="admin-sub">管理所有待审核的开发者、OAuth 应用和 Bot</p>
        </div>

        <?php if ($error): ?><div class="alert-error">⚠️ <?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert-success">✅ <?php echo $success; ?></div><?php endif; ?>

        <div style="margin-bottom:24px;">
            <h2 style="font-size:16px;font-weight:600;margin:16px 0 8px;">开发者申请</h2>
            <?php if ($developers): ?>
                <?php foreach ($developers as $item): ?>
                    <div class="review-item">
                        <div><strong><?php echo e($item['display_name'] ?: $item['username']); ?></strong> (<?php echo e($item['company_name']); ?>)</div>
                        <div class="meta">邮箱: <?php echo e($item['contact_email']); ?> | 网站: <?php echo e($item['website'] ?: '-'); ?></div>
                        <div class="meta">理由: <?php echo e($item['reason']); ?></div>
                        <form method="post" class="actions">
                            <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="type" value="developer">
                            <button type="submit" name="action" value="review" class="btn-approve" onclick="this.form.querySelector('input[name=status]').value='approved';">通过</button>
                            <button type="submit" name="action" value="review" class="btn-reject" onclick="this.form.querySelector('input[name=status]').value='rejected';">拒绝</button>
                            <input type="hidden" name="status" value="">
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--ba-text-muted);">暂无待审核的开发者申请</p>
            <?php endif; ?>

            <h2 style="font-size:16px;font-weight:600;margin:24px 0 8px;">OAuth 应用</h2>
            <?php if ($oauthApps): ?>
                <?php foreach ($oauthApps as $item): ?>
                    <div class="review-item">
                        <div><strong><?php echo e($item['name']); ?></strong> (作者: <?php echo e($item['display_name'] ?: $item['username']); ?>)</div>
                        <div class="meta">Client ID: <?php echo e($item['client_id']); ?> | Redirect: <?php echo e($item['redirect_uri']); ?></div>
                        <form method="post" class="actions">
                            <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="type" value="oauth">
                            <button type="submit" name="action" value="review" class="btn-approve" onclick="this.form.querySelector('input[name=status]').value='approved';">通过</button>
                            <button type="submit" name="action" value="review" class="btn-reject" onclick="this.form.querySelector('input[name=status]').value='rejected';">拒绝</button>
                            <input type="hidden" name="status" value="">
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--ba-text-muted);">暂无待审核的 OAuth 应用</p>
            <?php endif; ?>

            <h2 style="font-size:16px;font-weight:600;margin:24px 0 8px;">Bot</h2>
            <?php if ($bots): ?>
                <?php foreach ($bots as $item): ?>
                    <div class="review-item">
                        <div><strong><?php echo e($item['name']); ?></strong> (作者: <?php echo e($item['display_name'] ?: $item['username']); ?>)</div>
                        <div class="meta">描述: <?php echo e($item['description'] ?: '-'); ?> | API Key: <?php echo e($item['api_key']); ?></div>
                        <form method="post" class="actions">
                            <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="type" value="bot">
                            <button type="submit" name="action" value="review" class="btn-approve" onclick="this.form.querySelector('input[name=status]').value='approved';">通过</button>
                            <button type="submit" name="action" value="review" class="btn-reject" onclick="this.form.querySelector('input[name=status]').value='rejected';">拒绝</button>
                            <input type="hidden" name="status" value="">
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color:var(--ba-text-muted);">暂无待审核的 Bot</p>
            <?php endif; ?>
        </div>

        <div class="ragemi-footer">
            <div class="footer-logo"><img src="https://ragemi.com/s/top.png" alt="瑞格米" class="footer-logo-img"></div>
            <div class="footer-copyright">© <?php echo date('Y'); ?> 瑞格米 · 管理后台</div>
        </div>
    </main>
</div>

<script nonce="<?php echo getCSPNonce(); ?>">
// ===== 粒子背景 =====
(function(){var c=document.getElementById('particleBg');if(!c)return;for(var i=0;i<20;i++){var p=document.createElement('div');p.className='particle';p.style.left=Math.random()*100+'%';p.style.width=(Math.random()*4+2)+'px';p.style.height=p.style.width;p.style.animationDuration=(Math.random()*20+15)+'s';p.style.animationDelay=(Math.random()*20)+'s';p.style.opacity=Math.random()*0.15+0.05;c.appendChild(p);}})();
function toggleSidebar(){var s=document.getElementById('sidebar'),o=document.getElementById('sidebarOverlay');s.classList.toggle('open');o.classList.toggle('active');}
document.addEventListener('click',function(e){if(window.innerWidth<=768){var s=document.getElementById('sidebar'),t=document.getElementById('menuToggle');if(!s.contains(e.target)&&!t.contains(e.target)){if(s.classList.contains('open')){toggleSidebar();}}}});
window.toggleSidebar=toggleSidebar;
</script>
</body>
</html>