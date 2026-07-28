<?php
// admin/oauth.php - OAuth 应用管理（管理员）
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

// 处理审核/禁用/删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appId = (int)$_POST['app_id'];
    $action = $_POST['action'];
    if (!verifyCsrf($_POST['csrf'] ?? '')) { $error = 'CSRF 验证失败'; }
    else {
        switch ($action) {
            case 'approve':
                $s = $pdo->prepare("UPDATE oauth_apps SET status='approved', approved_at=NOW(), approved_by=? WHERE id=?");
                $s->execute([$me['id'], $appId]);
                $success = '应用已通过审核';
                break;
            case 'reject':
                $s = $pdo->prepare("UPDATE oauth_apps SET status='rejected', approved_at=NOW(), approved_by=? WHERE id=?");
                $s->execute([$me['id'], $appId]);
                $success = '应用已拒绝';
                break;
            case 'disable':
                $s = $pdo->prepare("UPDATE oauth_apps SET status='disabled' WHERE id=?");
                $s->execute([$appId]);
                $success = '应用已禁用';
                break;
            case 'enable':
                $s = $pdo->prepare("UPDATE oauth_apps SET status='approved' WHERE id=?");
                $s->execute([$appId]);
                $success = '应用已启用';
                break;
            case 'delete':
                $s = $pdo->prepare("DELETE FROM oauth_apps WHERE id=?");
                $s->execute([$appId]);
                $success = '应用已删除';
                break;
            default: $error = '未知操作';
        }
    }
}

// 获取所有应用（包括状态）
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT o.*, u.username, u.display_name FROM oauth_apps o JOIN users u ON o.user_id = u.id WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (o.name LIKE ? OR o.client_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter) {
    $sql .= " AND o.status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY o.id DESC";
$s = $pdo->prepare($sql);
$s->execute($params);
$apps = $s->fetchAll();

$title = 'OAuth 应用管理 - 瑞格米';
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
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-bar input, .filter-bar select { padding: 8px 12px; border: 2px solid rgba(150,170,190,0.25); border-radius: 8px; font-size: 14px; font-family: inherit; background: rgba(255,255,255,0.5); color: var(--ba-text); outline: none; }
        .filter-bar input:focus, .filter-bar select:focus { border-color: var(--ba-accent); }
        .filter-bar button { padding: 8px 20px; border-radius: 8px; border: none; background: var(--ba-accent); color: #fff; font-size: 14px; font-weight: 500; cursor: pointer; }
        .app-item { border-bottom: 1px solid rgba(150,170,190,0.08); padding: 12px 0; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .app-item .info { flex: 1; min-width: 200px; }
        .app-item .name { font-weight: 600; }
        .app-item .client-id { font-size: 12px; color: var(--ba-text-muted); font-family: monospace; }
        .app-item .status { font-size: 12px; padding: 2px 10px; border-radius: 12px; background: rgba(150,170,190,0.1); }
        .status.approved { background: rgba(39,174,96,0.1); color: #27ae60; }
        .status.pending { background: rgba(243,156,18,0.1); color: #f39c12; }
        .status.rejected { background: rgba(231,76,60,0.1); color: #e74c3c; }
        .status.disabled { background: rgba(150,170,190,0.1); color: #95a5a6; }
        .app-item .actions { display: flex; gap: 4px; flex-wrap: wrap; }
        .app-item .actions form { display: inline; }
        .app-item .actions button { padding: 2px 12px; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; font-family: inherit; }
        .btn-approve { background: #27ae60; color: #fff; }
        .btn-reject { background: #e74c3c; color: #fff; }
        .btn-disable { background: #f39c12; color: #fff; }
        .btn-enable { background: #2980b9; color: #fff; }
        .btn-delete { background: #95a5a6; color: #fff; }
    </style>
</head>
<body>
<!-- 粒子背景、顶部导航栏、侧边栏（通过 include） -->
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
            <h1>OAuth 应用管理</h1>
            <p class="admin-sub">管理所有第三方应用</p>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

        <div class="filter-bar">
            <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;flex:1;">
                <input type="text" name="search" placeholder="搜索应用名称或 Client ID" value="<?php echo e($search); ?>">
                <select name="status">
                    <option value="">全部状态</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>待审核</option>
                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>已通过</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>已拒绝</option>
                    <option value="disabled" <?php echo $statusFilter === 'disabled' ? 'selected' : ''; ?>>已禁用</option>
                </select>
                <button type="submit">筛选</button>
                <a href="/admin/oauth.php" class="btn-text">清除</a>
            </form>
        </div>

        <?php if ($apps): ?>
            <?php foreach ($apps as $app): ?>
                <div class="app-item">
                    <div class="info">
                        <div class="name"><?php echo e($app['name']); ?></div>
                        <div class="client-id">Client ID: <?php echo e($app['client_id']); ?></div>
                        <div style="font-size:13px;color:var(--ba-text-secondary);">作者: <?php echo e($app['display_name'] ?: $app['username']); ?></div>
                        <div style="font-size:13px;color:var(--ba-text-secondary);">回调: <?php echo e($app['redirect_uri']); ?></div>
                        <span class="status <?php echo $app['status']; ?>"><?php echo $app['status']; ?></span>
                    </div>
                    <div class="actions">
                        <?php if ($app['status'] === 'pending'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn-approve">通过</button>
                                <button type="submit" name="action" value="reject" class="btn-reject">拒绝</button>
                            </form>
                        <?php elseif ($app['status'] === 'approved'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                <button type="submit" name="action" value="disable" class="btn-disable">禁用</button>
                                <button type="submit" name="action" value="delete" class="btn-delete" onclick="return confirm('确定删除此应用？')">删除</button>
                            </form>
                        <?php elseif ($app['status'] === 'disabled'): ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                <button type="submit" name="action" value="enable" class="btn-enable">启用</button>
                                <button type="submit" name="action" value="delete" class="btn-delete" onclick="return confirm('确定删除此应用？')">删除</button>
                            </form>
                        <?php else: // rejected ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo csrfToken(); ?>">
                                <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                <button type="submit" name="action" value="delete" class="btn-delete" onclick="return confirm('确定删除此应用？')">删除</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:var(--ba-text-muted);">暂无 OAuth 应用</p>
        <?php endif; ?>

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