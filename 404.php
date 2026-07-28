<?php
// 404.php - 页面不存在
require_once __DIR__ . '/config.php';

// 设置404状态码
http_response_code(404);

$me = me();
$stats = getSiteStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>页面不存在 - <?php echo SITE_TITLE; ?></title>
    <link rel="icon" href="/s/67.png">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Noto+Serif+SC:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <style>
        /* ========================================
           Ragemi - 蔚蓝档案主题（404页）
           ======================================== */
        
        :root {
            --ba-blue: #7ec8e3;
            --ba-blue-dark: #4a9fc7;
            --ba-pink: #f4c2d0;
            --ba-bg: #f0f4f8;
            --ba-text: #4a6075;
            --ba-text-secondary: #5a6f84;
            --ba-text-muted: #8a9db0;
            --ba-text-light: #a0b8cc;
            --ba-accent: #7A5C2D;
            --ba-accent-light: #c9a06a;
            --ba-accent-dark: #5c3e1f;
            --ba-card-bg: rgba(255,255,255,0.55);
            --ba-card-border: rgba(255,255,255,0.8);
            --ba-card-shadow: 0 2px 12px rgba(150,170,190,0.12);
            --ba-card-shadow-hover: 0 8px 28px rgba(150,170,190,0.22);
            --ba-radius: 12px;
            --ba-radius-lg: 20px;
            --content-max-width: 680px;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --ba-bg: #1a1e24;
                --ba-text: #d4dce8;
                --ba-text-secondary: #b0c0d0;
                --ba-text-muted: #7a8a9a;
                --ba-text-light: #5a6a7a;
                --ba-card-bg: rgba(30,35,45,0.7);
                --ba-card-border: rgba(60,70,85,0.5);
                --ba-card-shadow: 0 2px 12px rgba(0,0,0,0.3);
                --ba-card-shadow-hover: 0 8px 28px rgba(0,0,0,0.4);
            }
            body { background: var(--ba-bg); }
            .error-container { background: var(--ba-card-bg); backdrop-filter: blur(10px); }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            background: var(--ba-bg);
            font-family: "Microsoft YaHei","PingFang SC","Helvetica Neue",sans-serif;
            color: var(--ba-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            transition: background 0.3s ease, color 0.3s ease;
        }
        a { color: var(--ba-accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ===== Top Navigation Bar ===== */
        #app-bar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            padding: 0 20px;
            background: var(--ba-card-bg);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border-bottom: 1px solid var(--ba-card-border);
            box-shadow: 0 1px 6px rgba(150,170,190,0.1);
        }

        .app-title-link {
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .app-title-link:hover {
            text-decoration: none;
        }

        .app-logo {
            height: 38px;
            width: auto;
            display: block;
            object-fit: contain;
        }

        @media (prefers-color-scheme: dark) {
            .app-logo {
                filter: brightness(0.9);
            }
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-text {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            border: none;
            background: transparent;
            color: var(--ba-text-secondary);
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            font-family: inherit;
        }
        .btn-text:hover {
            background: rgba(122,92,45,0.08);
            color: var(--ba-accent);
            text-decoration: none;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 20px;
            border: none;
            background: var(--ba-accent);
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
            font-family: inherit;
        }
        .btn-primary:hover {
            background: var(--ba-accent-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(122,92,45,0.3);
            text-decoration: none;
        }

        /* ===== 主内容 ===== */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .error-container {
            background: var(--ba-card-bg);
            backdrop-filter: blur(12px);
            border-radius: var(--ba-radius-lg);
            padding: 48px 40px 40px;
            border: 1px solid var(--ba-card-border);
            box-shadow: var(--ba-card-shadow);
            max-width: 520px;
            width: 100%;
            text-align: center;
            transition: box-shadow 0.35s ease;
        }
        .error-container:hover {
            box-shadow: var(--ba-card-shadow-hover);
        }

        .error-container .error-icon {
            font-size: 80px;
            color: var(--ba-accent-light);
            display: block;
            margin-bottom: 8px;
            opacity: 0.6;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }

        .error-container .error-code {
            font-size: 72px;
            font-weight: 800;
            font-family: 'Playfair Display', serif;
            color: var(--ba-accent);
            line-height: 1;
            margin-bottom: 4px;
        }

        .error-container .error-title {
            font-size: 22px;
            font-weight: 600;
            color: var(--ba-text);
            margin-bottom: 8px;
            font-family: 'Playfair Display', serif;
        }

        .error-container .error-desc {
            color: var(--ba-text-muted);
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 24px;
        }

        .error-container .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .error-container .error-actions .btn-primary,
        .error-container .error-actions .btn-secondary {
            padding: 10px 28px;
            font-size: 15px;
            border-radius: 30px;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            border-radius: 20px;
            border: 1px solid var(--ba-card-border);
            background: transparent;
            color: var(--ba-text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
            font-family: inherit;
        }
        .btn-secondary:hover {
            background: rgba(122,92,45,0.06);
            border-color: var(--ba-accent);
            color: var(--ba-accent);
            text-decoration: none;
        }

        /* 热门话题推荐 */
        .error-container .suggestions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(150,170,190,0.12);
        }
        .error-container .suggestions .suggest-title {
            font-size: 13px;
            color: var(--ba-text-muted);
            margin-bottom: 10px;
        }
        .error-container .suggestions .tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        .error-container .suggestions .tags .tag {
            background: rgba(122,92,45,0.06);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 13px;
            color: var(--ba-accent);
            border: 1px solid rgba(122,92,45,0.08);
            transition: all 0.2s;
            cursor: pointer;
        }
        .error-container .suggestions .tags .tag:hover {
            background: rgba(122,92,45,0.12);
            border-color: var(--ba-accent);
            transform: translateY(-1px);
            text-decoration: none;
        }

        /* ===== Footer ===== */
        .ragemi-footer {
            text-align: center;
            padding: 24px 16px 28px;
            border-top: 1px solid rgba(150,170,190,0.12);
            margin-top: 20px;
        }

        .footer-logo {
            margin-bottom: 8px;
        }

        .footer-logo-img {
            height: 30px;
            width: auto;
            display: inline-block;
            object-fit: contain;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }

        .footer-logo-img:hover {
            opacity: 1;
        }

        .footer-copyright {
            color: var(--ba-text-muted);
            font-size: 12px;
            letter-spacing: 1px;
        }

        @media (prefers-color-scheme: dark) {
            .footer-logo-img {
                filter: brightness(0.9);
            }
        }

        /* ===== 粒子背景 ===== */
        .particle-bg {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }
        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--ba-accent-light);
            border-radius: 50%;
            opacity: 0.12;
            animation: floatParticle 25s infinite linear;
        }
        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg); opacity: 0; }
            10% { opacity: 0.12; }
            90% { opacity: 0.12; }
            100% { transform: translateY(-10vh) rotate(720deg); opacity: 0; }
        }

        /* ===== Responsive ===== */
        @media (max-width: 500px) {
            .error-container {
                padding: 32px 24px 28px;
            }
            .error-container .error-code {
                font-size: 52px;
            }
            .error-container .error-icon {
                font-size: 56px;
            }
            .error-container .error-title {
                font-size: 18px;
            }
            .error-container .error-actions {
                flex-direction: column;
            }
            .error-container .error-actions .btn-primary,
            .error-container .error-actions .btn-secondary {
                width: 100%;
                justify-content: center;
            }
            .app-logo {
                height: 30px;
            }
        }

        @media (max-width: 360px) {
            .error-container {
                padding: 24px 16px 20px;
            }
            .error-container .error-code {
                font-size: 40px;
            }
            .error-container .error-title {
                font-size: 16px;
            }
            .error-container .error-desc {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<!-- ===== 粒子背景 ===== -->
<div class="particle-bg" id="particleBg"></div>

<!-- ===== Top Navigation Bar ===== -->
<header id="app-bar">
    <a href="/" class="app-title-link">
        <img src="https://ragemi.com/s/top.png" alt="Ragemi" class="app-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
        <div class="app-title" style="display:none;">Ragemi</div>
    </a>
    <div class="top-bar-right">
        <a href="/" class="btn-text">
            <span class="material-symbols-outlined">home</span>
            返回首页
        </a>
    </div>
</header>

<!-- ===== 主内容 ===== -->
<main class="main-content">
    <div class="error-container">

        <!-- 404 图标 -->
        <span class="error-icon">🔍</span>

        <!-- 错误代码 -->
        <div class="error-code">404</div>

        <!-- 标题 -->
        <div class="error-title">页面走丢了</div>

        <!-- 描述 -->
        <div class="error-desc">
            抱歉，你访问的页面不存在或已被移除。<br>
            可能是链接有误，或者内容已迁移。
        </div>

        <!-- 操作按钮 -->
        <div class="error-actions">
            <a href="/" class="btn-primary">
                <span class="material-symbols-outlined">home</span>
                回到首页
            </a>
            <a href="javascript:history.back()" class="btn-secondary">
                <span class="material-symbols-outlined">arrow_back</span>
                返回上一页
            </a>
        </div>

        <!-- 热门话题推荐 -->
        <div class="suggestions">
            <div class="suggest-title">🔥 热门话题</div>
            <div class="tags">
                <?php 
                try {
                    $tags = getTrendingHashtags(6);
                    foreach ($tags as $tag): 
                ?>
                    <a href="/search?q=#<?php echo urlencode($tag); ?>" class="tag">#<?php echo e($tag); ?></a>
                <?php 
                    endforeach; 
                } catch (Exception $e) {
                    // 如果数据库查询失败，显示默认标签
                ?>
                    <a href="/search?q=#生活" class="tag">#生活</a>
                    <a href="/search?q=#分享" class="tag">#分享</a>
                    <a href="/search?q=#日常" class="tag">#日常</a>
                    <a href="/search?q=#心情" class="tag">#心情</a>
                <?php } ?>
            </div>
        </div>

    </div>
</main>

<!-- ===== Footer ===== -->
<div class="ragemi-footer">
    <div class="footer-logo">
        <img src="https://ragemi.com/s/top.png" alt="Ragemi" class="footer-logo-img">
    </div>
    <div class="footer-copyright">
        © 2026 瑞格米工作室
    </div>
</div>

<script>
// ===== 粒子背景生成 =====
(function() {
    const container = document.getElementById('particleBg');
    if (!container) return;
    for (let i = 0; i < 15; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.width = (Math.random() * 4 + 2) + 'px';
        particle.style.height = particle.style.width;
        particle.style.animationDuration = (Math.random() * 20 + 15) + 's';
        particle.style.animationDelay = (Math.random() * 20) + 's';
        particle.style.opacity = Math.random() * 0.12 + 0.04;
        container.appendChild(particle);
    }
})();

// ===== 深色模式 =====
(function() {
    function applyTheme(dark) {
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    }
    const saved = localStorage.getItem('ragemi_theme');
    if (saved === 'dark') applyTheme(true);
    else if (saved === 'light') applyTheme(false);
    else if (window.matchMedia('(prefers-color-scheme: dark)').matches) applyTheme(true);
})();
</script>

</body>
</html>