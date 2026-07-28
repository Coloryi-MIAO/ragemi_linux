<?php
// privacy.php - 隐私政策
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

initSecurityHeaders();

$me = me();
$title = '隐私政策 - 瑞格米';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="icon" href="<?php echo ASSET_URL; ?>/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="/css/common.css">
    <style>
        .legal-page {
            max-width: 820px;
            margin: 0 auto;
            padding: 20px 16px 40px;
        }
        .legal-page h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: var(--ba-accent);
            margin-bottom: 4px;
        }
        .legal-page .meta {
            color: var(--ba-text-muted);
            font-size: 14px;
            margin-bottom: 24px;
            border-bottom: 1px solid rgba(150,170,190,0.1);
            padding-bottom: 12px;
        }
        .legal-page h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--ba-text);
            margin: 24px 0 8px;
        }
        .legal-page h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--ba-text-secondary);
            margin: 16px 0 4px;
        }
        .legal-page p {
            font-size: 14px;
            line-height: 1.8;
            color: var(--ba-text-secondary);
            margin-bottom: 8px;
        }
        .legal-page ul {
            padding-left: 24px;
            margin-bottom: 12px;
        }
        .legal-page ul li {
            font-size: 14px;
            line-height: 1.8;
            color: var(--ba-text-secondary);
        }
        .legal-page table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 16px;
            font-size: 14px;
        }
        .legal-page table th {
            background: rgba(122,92,45,0.08);
            padding: 8px 12px;
            text-align: left;
            font-weight: 600;
            color: var(--ba-text);
            border: 1px solid rgba(150,170,190,0.1);
        }
        .legal-page table td {
            padding: 8px 12px;
            border: 1px solid rgba(150,170,190,0.1);
            color: var(--ba-text-secondary);
        }
        .legal-page .back-link {
            display: inline-block;
            margin-top: 24px;
            color: var(--ba-accent);
            text-decoration: none;
        }
        .legal-page .back-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 640px) {
            .legal-page h1 { font-size: 22px; }
            .legal-page table { font-size: 13px; }
            .legal-page table th, .legal-page table td { padding: 6px 8px; }
        }
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
    </div>
</header>

<nav class="sidebar" id="sidebar">
    <div class="nav-section">
        <a href="/" class="nav-item" data-page="home"><span class="material-symbols-outlined">home</span><span class="nav-label">主页</span></a>
        <a href="/explore" class="nav-item" data-page="explore"><span class="material-symbols-outlined">explore</span><span class="nav-label">发现</span></a>
        <a href="/login" class="nav-item" data-page="login"><span class="material-symbols-outlined">login</span><span class="nav-label">登录</span></a>
    </div>
    <div class="sidebar-footer">
        <hr class="sidebar-divider">
        <a href="/settings" class="nav-item" data-page="settings"><span class="material-symbols-outlined">settings</span><span class="nav-label">设置</span></a>
    </div>
</nav>

<div class="main-wrapper">
    <main class="main-content">
        <div class="legal-page">
            <h1>隐私政策</h1>
            <div class="meta">更新日期：2026年7月17日 | 瑞格米（Ragemi）</div>

            <p>瑞格米（以下简称"我们"）非常重视您的隐私。本隐私政策说明我们如何收集、使用、存储和保护您的个人信息。</p>

            <h2>一、我们收集的信息</h2>

            <h3>1.1 您主动提供的信息</h3>
            <ul>
                <li><strong>注册信息：</strong>用户名、昵称、邮箱地址、密码（加密存储）</li>
                <li><strong>个人资料：</strong>头像、个人简介、主页背景图</li>
                <li><strong>发布内容：</strong>帖子、评论、私信内容</li>
                <li><strong>互动记录：</strong>点赞、关注、收藏等操作</li>
            </ul>

            <h3>1.2 第三方登录信息</h3>
            <p>当您使用 Google 账号登录时，我们会获取：</p>
            <ul>
                <li>Google 账号的邮箱地址</li>
                <li>Google 账号的公开姓名</li>
                <li>Google 账号的头像（如已设置）</li>
            </ul>

            <h3>1.3 自动收集的信息</h3>
            <ul>
                <li>设备信息：IP 地址、浏览器类型、操作系统版本</li>
                <li>访问记录：访问时间、访问页面、点击行为</li>
                <li>Cookie：用于保持登录状态和偏好设置</li>
            </ul>

            <h2>二、我们如何使用信息</h2>

            <table>
                <tr><th>用途</th><th>说明</th></tr>
                <tr><td>提供服务</td><td>创建账号、发布内容、互动功能</td></tr>
                <tr><td>安全保障</td><td>检测异常登录、防止恶意行为</td></tr>
                <tr><td>优化体验</td><td>个性化推荐、改进界面设计</td></tr>
                <tr><td>遵守法律</td><td>配合执法机关依法调查</td></tr>
            </table>

            <h2>三、信息存储与保护</h2>

            <h3>3.1 存储方式</h3>
            <ul>
                <li>数据存储在中国境内的服务器</li>
                <li>密码使用 bcrypt 算法加密</li>
                <li>传输使用 TLS/SSL 加密</li>
            </ul>

            <h3>3.2 数据保留</h3>
            <ul>
                <li>账号注销后，内容数据将在 30 天内删除</li>
                <li>日志数据保留 180 天用于安全审计</li>
                <li>用户可随时导出个人数据</li>
            </ul>

            <h3>3.3 安全措施</h3>
            <ul>
                <li>定期安全审计和漏洞扫描</li>
                <li>严格的访问权限控制</li>
                <li>实时监控异常行为</li>
            </ul>

            <h2>四、信息共享</h2>
            <p>我们不会出售您的个人信息。</p>

            <h3>可能共享的场景</h3>
            <ul>
                <li><strong>法律法规要求：</strong>应法院传票、政府调查等合法要求</li>
                <li><strong>防止伤害：</strong>保护用户生命财产安全</li>
                <li><strong>第三方服务：</strong>使用邮件服务发送验证码（仅限必要范围）</li>
            </ul>

            <h3>二次元社区特别说明</h3>
            <ul>
                <li>您发布的帖子、评论将公开展示</li>
                <li>您的用户名、头像、昵称对全体用户可见</li>
                <li>私信内容仅发送者和接收者可查看</li>
            </ul>

            <h2>五、您的权利</h2>

            <table>
                <tr><th>权利</th><th>操作方式</th></tr>
                <tr><td>访问数据</td><td>在设置页查看个人资料</td></tr>
                <tr><td>修改数据</td><td>在设置页编辑个人信息</td></tr>
                <tr><td>删除数据</td><td>联系管理员申请账号注销</td></tr>
                <tr><td>撤回同意</td><td>撤回对 Google 登录的授权</td></tr>
                <tr><td>数据导出</td><td>联系管理员获取数据副本</td></tr>
            </table>

            <h2>六、未成年人保护</h2>
            <ul>
                <li>本服务面向 13 岁以上用户</li>
                <li>如您是 13 岁以下用户，请在监护人陪同下使用</li>
                <li>如发现未成年人未经监护人同意注册，请联系我们删除</li>
            </ul>

            <h2>七、Cookie 使用</h2>
            <p>我们使用 Cookie 用于：</p>
            <ul>
                <li>保持登录状态（7 天有效期）</li>
                <li>记住主题偏好（深色/浅色模式）</li>
                <li>防 CSRF 攻击（安全 Token）</li>
            </ul>
            <p>您可以在浏览器设置中禁用 Cookie，但可能影响部分功能。</p>

            <h2>八、隐私政策变更</h2>
            <ul>
                <li>重大变更将提前 7 天在网站公告</li>
                <li>继续使用即视为接受变更</li>
                <li>历史版本可向管理员索取</li>
            </ul>

            <h2>九、联系我们</h2>
            <p>如您有任何隐私相关问题，请通过以下方式联系我们：</p>
            <ul>
                <li>网站：<a href="https://www.ragemi.com">https://www.ragemi.com</a></li>
                <li>邮件：ruansik@126.com</li>
            </ul>

            <a href="/" class="back-link">← 返回首页</a>
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