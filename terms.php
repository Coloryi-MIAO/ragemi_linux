<?php
// terms.php - 服务条款
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

initSecurityHeaders();

$me = me();
$title = '服务条款 - 瑞格米';
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
            <h1>服务条款</h1>
            <div class="meta">更新日期：2026年7月17日 | 瑞格米（Ragemi）</div>

            <h2>一、服务说明</h2>
            <p>瑞格米是一个二次元主题的帖子分享社区，为用户提供以下服务：</p>
            <ul>
                <li>发布文字和图片帖子</li>
                <li>评论和互动</li>
                <li>私信交流</li>
                <li>关注其他用户</li>
                <li>个性化个人空间</li>
            </ul>

            <h2>二、用户义务</h2>

            <h3>2.1 账号安全</h3>
            <ul>
                <li>妥善保管密码，不分享给他人</li>
                <li>发现账号异常立即通知管理员</li>
                <li>不使用他人账号登录</li>
            </ul>

            <h3>2.2 内容规范</h3>
            <p>您发布的内容不得包含：</p>

            <table>
                <tr><th>禁止内容</th><th>示例</th></tr>
                <tr><td>违法违规</td><td>违反国家法律法规的内容</td></tr>
                <tr><td>暴力恐怖</td><td>宣扬暴力、恐怖主义</td></tr>
                <tr><td>色情低俗</td><td>色情图片、文字、链接</td></tr>
                <tr><td>仇恨歧视</td><td>种族、性别、地域歧视</td></tr>
                <tr><td>侵权内容</td><td>未经授权的版权作品</td></tr>
                <tr><td>垃圾信息</td><td>广告、刷屏、恶意链接</td></tr>
                <tr><td>谣言诈骗</td><td>虚假信息、诈骗内容</td></tr>
            </table>

            <h3>2.3 行为规范</h3>
            <ul>
                <li>尊重其他用户，不进行人身攻击</li>
                <li>不恶意刷屏、灌水</li>
                <li>不利用漏洞破坏平台</li>
                <li>不进行任何形式的网络攻击</li>
            </ul>

            <h2>三、用户权利</h2>

            <h3>3.1 账号权利</h3>
            <ul>
                <li>随时修改个人资料</li>
                <li>删除自己发布的内容</li>
                <li>注销自己的账号</li>
                <li>退出平台（注销登录）</li>
            </ul>

            <h3>3.2 内容权利</h3>
            <ul>
                <li>用户对发布的内容拥有所有权</li>
                <li>授予平台展示、分发内容的权利</li>
                <li>用户可要求删除自己的内容</li>
            </ul>

            <h2>四、平台权利与义务</h2>

            <h3>4.1 平台权利</h3>
            <ul>
                <li>对违规内容进行删除、隐藏</li>
                <li>对违规用户进行警告、封禁</li>
                <li>修改服务条款（提前通知）</li>
                <li>临时关闭服务进行维护</li>
            </ul>

            <h3>4.2 平台义务</h3>
            <ul>
                <li>保护用户隐私和数据安全</li>
                <li>提供稳定的服务</li>
                <li>对用户反馈及时回应</li>
                <li>遵守法律法规</li>
            </ul>

            <h2>五、免责声明</h2>

            <h3>5.1 用户内容</h3>
            <ul>
                <li>用户发布的内容不代表平台立场</li>
                <li>用户对内容负全部法律责任</li>
                <li>平台不对用户间纠纷承担责任</li>
            </ul>

            <h3>5.2 服务中断</h3>
            <ul>
                <li>因维护、升级导致的服务中断提前通知</li>
                <li>因不可抗力（地震、战争、网络攻击等）导致的服务中断，平台不承担责任</li>
            </ul>

            <h3>5.3 第三方链接</h3>
            <ul>
                <li>用户分享的第三方链接与平台无关</li>
                <li>平台不对外部链接内容负责</li>
            </ul>

            <h2>六、终止服务</h2>
            <ul>
                <li>用户可随时注销账号退出</li>
                <li>违反服务条款可能导致账号封禁</li>
                <li>严重违规将永久封禁账号</li>
                <li>封禁期间用户可申请复核</li>
            </ul>

            <h2>七、知识产权</h2>
            <ul>
                <li>平台名称、Logo、设计受版权保护</li>
                <li>用户内容版权归用户所有</li>
                <li>未经许可不得盗用平台资源</li>
            </ul>

            <h2>八、法律适用</h2>
            <ul>
                <li>适用中华人民共和国法律</li>
                <li>争议协商解决，协商不成诉至平台所在地法院</li>
            </ul>

            <h2>九、联系我们</h2>
            <ul>
                <li>网站：<a href="https://www.ragemi.com">https://www.ragemi.com</a></li>
                <li>邮件：ruansik@163.com</li>
            </ul>

            <a href="/" class="back-link">← 返回首页</a>
        </div>
    </main>
</div>

<script nonce="<?php echo getCSPNonce(); ?>">
(function(){var c=document.getElementById('particleBg');if(!c)return;for(var i=0;i<20;i++){var p=document.createElement('div');p.className='particle';p.style.left=Math.random()*100+'%';p.style.width=(Math.random()*4+2)+'px';p.style.height=p.style.width;p.style.animationDuration=(Math.random()*20+15)+'s';p.style.animationDelay=(Math.random()*20)+'s';p.style.opacity=Math.random()*0.15+0.05;c.appendChild(p);}})();
function toggleSidebar(){var s=document.getElementById('sidebar'),o=document.getElementById('sidebarOverlay');s.classList.toggle('open');o.classList.toggle('active');}
document.addEventListener('click',function(e){if(window.innerWidth<=768){var s=document.getElementById('sidebar'),t=document.getElementById('menuToggle');if(!s.contains(e.target)&&!t.contains(e.target)){if(s.classList.contains('open')){toggleSidebar();}}}});
window.toggleSidebar=toggleSidebar;
</script>
</body>
</html>