<?php
// oauth/docs.php - OAuth 2.0 完整文档
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$pageStyle = '/css/openplatform.css';
initSecurityHeaders();

$me = me();

$title = 'OAuth 2.0 文档 - 开发者平台';
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
        .doc-container { max-width: 900px; margin: 0 auto; }
        .doc-container h1 { font-size: 28px; margin-bottom: 4px; }
        .doc-container .sub { color: #7a8a9a; font-size: 15px; margin-bottom: 32px; }
        .doc-section { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .doc-section h2 { font-size: 20px; color: #e8f0f8; margin-bottom: 12px; }
        .doc-section h3 { font-size: 16px; color: #c8d8e8; margin: 16px 0 8px; }
        .doc-section p { color: #b0c0d0; line-height: 1.7; font-size: 14px; }
        .doc-section .code-block { background: rgba(0,0,0,0.4); border-radius: 8px; padding: 16px; font-family: 'JetBrains Mono', monospace; font-size: 13px; color: #b0c8e0; overflow-x: auto; margin: 8px 0; border: 1px solid rgba(255,255,255,0.06); }
        .doc-section .code-block .hl { color: #6a9fd8; }
        .doc-section .code-block .str { color: #8ac4a0; }
        .doc-section .code-block .cmt { color: #5a6a7a; }
        .doc-section .param-table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 12px 0; }
        .doc-section .param-table th { text-align: left; padding: 8px 12px; background: rgba(255,255,255,0.04); color: #b0c0d0; font-weight: 500; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .doc-section .param-table td { padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.04); color: #c8d8e8; }
        .doc-section .param-table td code { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #6a9fd8; background: rgba(255,255,255,0.04); padding: 2px 6px; border-radius: 4px; }
        .doc-section .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .badge-get { background: rgba(96,176,128,0.15); color: #60b080; }
        .badge-post { background: rgba(106,159,216,0.15); color: #6a9fd8; }
        .badge-delete { background: rgba(232,112,96,0.15); color: #e87060; }
        .doc-nav { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
        .doc-nav a { padding: 6px 16px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 6px; color: #b0c0d0; font-size: 14px; transition: 0.2s; }
        .doc-nav a:hover { background: rgba(255,255,255,0.08); color: #fff; text-decoration: none; }
        .note-box { background: rgba(106,159,216,0.05); border-left: 3px solid #6a9fd8; padding: 12px 16px; border-radius: 4px; margin: 12px 0; color: #b0c0d0; font-size: 14px; }
        .note-box strong { color: #8bb8e8; }
        .warning-box { background: rgba(232,176,96,0.05); border-left: 3px solid #e8b060; padding: 12px 16px; border-radius: 4px; margin: 12px 0; color: #b0c0d0; font-size: 14px; }
        .warning-box strong { color: #e8b060; }
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
            <?php if ($me): ?>
                <span class="op-user"><?php echo e($me['display_name'] ?: $me['username']); ?></span>
                <a href="/openplatform/logout" class="op-btn op-btn-outline">退出</a>
            <?php else: ?>
                <a href="/login" class="op-btn op-btn-primary">登录</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="op-container">
    <div class="doc-container">
        <h1>OAuth 2.0 文档</h1>
        <p class="sub">使用 <strong>Ragemi 登录</strong> 让第三方应用安全访问用户数据</p>

        <div class="doc-nav">
            <a href="#overview">概述</a>
            <a href="#register">注册应用</a>
            <a href="#auth-flow">授权流程</a>
            <a href="#token">获取 Token</a>
            <a href="#refresh">刷新 Token</a>
            <a href="#api">调用 API</a>
            <a href="#errors">错误处理</a>
            <a href="#faq">常见问题</a>
        </div>

        <!-- ===== 概述 ===== -->
        <div class="doc-section" id="overview">
            <h2>概述</h2>
            <p>瑞格米 OAuth 2.0 服务允许第三方应用通过 <strong>“使用 Ragemi 登录”</strong> 的方式，让用户安全地授权你的应用访问其基本信息。</p>
            <p>我们支持 <strong>授权码模式（Authorization Code Grant）</strong>，所有请求均需通过 HTTPS。</p>
            <p>授权页面会清晰显示 <strong>“使用 Ragemi 登录”</strong> 按钮，用户确认后应用将获得 Access Token。</p>
            <div class="note-box">
                💡 <strong>适用场景：</strong> 本模式适用于有后端服务器的 Web 应用。<br>
                <strong>注意：</strong> <code>client_secret</code> 必须保存在服务端，<strong>严禁</strong>在前端或移动客户端中暴露。
            </div>
        </div>

        <!-- ===== 注册应用 ===== -->
        <div class="doc-section" id="register">
            <h2>注册应用</h2>
            <p>在 <a href="/openplatform/apps">开发者平台</a> 创建 OAuth 应用，审核通过后获得 <code>client_id</code> 和 <code>client_secret</code>。</p>
            <p>你需要提供：</p>
            <ul style="color:#b0c0d0;font-size:14px;line-height:1.8;padding-left:20px;">
                <li>应用名称</li>
                <li>回调地址（Redirect URI）—— 用户授权后跳转的 URL，必须与注册时完全一致</li>
                <li>应用描述（可选）</li>
            </ul>
        </div>

        <!-- ===== 授权流程（关键修复章节） ===== -->
        <div class="doc-section" id="auth-flow">
            <h2>授权流程</h2>

            <div class="warning-box">
                ⚠️ <strong>重要：</strong> 请使用 <strong>链接（<code>&lt;a&gt;</code>）或 JavaScript 重定向（<code>window.location.href</code>）</strong> 引导用户到授权页面。<br>
                <strong>请勿使用表单（<code>&lt;form&gt;</code>）POST 提交</strong>，否则可能被浏览器 CSP 策略拦截，导致“无法提交表单”错误。
            </div>

            <h3>步骤 1：引导用户授权（重定向方式）</h3>
            <p>将用户重定向到授权页面：</p>
            <div class="code-block">
                GET https://ragemi.com/oss?<br>
                &nbsp;&nbsp;client_id=<span class="str">YOUR_CLIENT_ID</span>&<br>
                &nbsp;&nbsp;redirect_uri=<span class="str">YOUR_REDIRECT_URI</span>&<br>
                &nbsp;&nbsp;response_type=<span class="str">code</span>&<br>
                &nbsp;&nbsp;scope=<span class="str">basic</span>&<br>
                &nbsp;&nbsp;state=<span class="str">RANDOM_STRING</span>
            </div>

            <p><strong>✅ 推荐方式 1：HTML 链接</strong></p>
            <div class="code-block">
                &lt;a href="https://ragemi.com/oss?client_id=YOUR_CLIENT_ID&amp;redirect_uri=YOUR_REDIRECT_URI&amp;response_type=code&amp;scope=basic&amp;state=xyz"&gt;使用 Ragemi 登录&lt;/a&gt;
            </div>

            <p><strong>✅ 推荐方式 2：JavaScript 重定向</strong></p>
            <div class="code-block">
                window.location.href = 'https://ragemi.com/oss?client_id=YOUR_CLIENT_ID&amp;redirect_uri=YOUR_REDIRECT_URI&amp;response_type=code&amp;scope=basic&amp;state=xyz';
            </div>

            <p><strong>❌ 错误方式：表单 POST（会导致 CSP 错误）</strong></p>
            <div class="code-block" style="border-left: 3px solid #e87060;">
                <span class="cmt">// ❌ 不要这样做！</span><br>
                &lt;form method="post" action="https://ragemi.com/oss"&gt;<br>
                &nbsp;&nbsp;&lt;input type="hidden" name="client_id" value="..."&gt;<br>
                &nbsp;&nbsp;&lt;button type="submit"&gt;登录&lt;/button&gt;<br>
                &lt;/form&gt;
            </div>

            <p>参数说明：</p>
            <table class="param-table">
                <tr><th>参数</th><th>必填</th><th>说明</th></tr>
                <tr><td><code>client_id</code></td><td>是</td><td>应用注册时获得的 Client ID</td></tr>
                <tr><td><code>redirect_uri</code></td><td>是</td><td>必须与注册时完全一致</td></tr>
                <tr><td><code>response_type</code></td><td>是</td><td>固定为 <code>code</code></td></tr>
                <tr><td><code>scope</code></td><td>否</td><td>权限范围（目前支持 <code>basic</code>，未来将扩展 <code>email</code> 等）</td></tr>
                <tr><td><code>state</code></td><td>推荐</td><td>防 CSRF 攻击，原样返回</td></tr>
            </table>
            <p><strong>关于 Scope：</strong> 目前仅支持 <code>basic</code>（获取用户公开基础信息），更多权限范围（如 <code>email</code>）将陆续开放。</p>

            <h3>步骤 2：用户同意授权</h3>
            <p>用户点击 <strong>“使用 Ragemi 登录 · 同意授权”</strong> 确认授权后，平台会将授权码（<code>code</code>）附加到 <code>redirect_uri</code> 上重定向回你的应用：</p>
            <div class="code-block">
                https://your-app.com/callback?code=<span class="str">AUTH_CODE</span>&state=<span class="str">RANDOM_STRING</span>
            </div>
            <p>如果用户拒绝，则回调 <code>error=access_denied</code>。</p>

            <h3>步骤 3：用授权码换取 Access Token</h3>
            <p>在服务端用授权码向平台交换 Access Token。</p>
            <div class="code-block">
                POST https://ragemi.com/oauth/token<br>
                Content-Type: application/x-www-form-urlencoded<br>
                <br>
                grant_type=<span class="str">authorization_code</span>&<br>
                code=<span class="str">AUTH_CODE</span>&<br>
                client_id=<span class="str">YOUR_CLIENT_ID</span>&<br>
                client_secret=<span class="str">YOUR_CLIENT_SECRET</span>&<br>
                redirect_uri=<span class="str">YOUR_REDIRECT_URI</span>
            </div>
            <p>成功响应：</p>
            <div class="code-block">
                {<br>
                &nbsp;&nbsp;<span class="hl">"access_token"</span>: <span class="str">"eyJ..."</span>,<br>
                &nbsp;&nbsp;<span class="hl">"token_type"</span>: <span class="str">"Bearer"</span>,<br>
                &nbsp;&nbsp;<span class="hl">"expires_in"</span>: 3600,<br>
                &nbsp;&nbsp;<span class="hl">"refresh_token"</span>: <span class="str">"..."</span><br>
                }
            </div>
        </div>

        <!-- ===== 获取 Token ===== -->
        <div class="doc-section" id="token">
            <h2>获取 Access Token</h2>
            <p>使用授权码换取 Access Token 的接口如上所示。Token 有效期为 <strong>1 小时</strong>，过期后需使用 Refresh Token 刷新。</p>
        </div>

        <!-- ===== 刷新 Token ===== -->
        <div class="doc-section" id="refresh">
            <h2>刷新 Access Token</h2>
            <p>当 Access Token 过期时，可使用 Refresh Token 获取新的 Access Token：</p>
            <div class="code-block">
                POST https://ragemi.com/oauth/token<br>
                Content-Type: application/x-www-form-urlencoded<br>
                <br>
                grant_type=<span class="str">refresh_token</span>&<br>
                refresh_token=<span class="str">YOUR_REFRESH_TOKEN</span>&<br>
                client_id=<span class="str">YOUR_CLIENT_ID</span>&<br>
                client_secret=<span class="str">YOUR_CLIENT_SECRET</span>
            </div>
            <p>响应结构与授权码交换相同。</p>
        </div>

        <!-- ===== 调用 API ===== -->
        <div class="doc-section" id="api">
            <h2>调用 API</h2>
            <p>在请求头中携带 Access Token 即可调用需要授权的 API：</p>
            <div class="code-block">
                GET https://ragemi.com/api/user_me<br>
                Authorization: Bearer <span class="str">ACCESS_TOKEN</span>
            </div>
            <p>支持的 API 端点（需用户授权）：</p>
            <ul style="color:#b0c0d0;font-size:14px;line-height:1.8;padding-left:20px;">
                <li><code>GET /api/user_me</code> — 获取当前用户信息</li>
                <li><code>POST /api/post_create</code> — 发帖（需 scope 含 <code>post</code>）</li>
                <li><code>POST /api/comment_create</code> — 评论</li>
                <li><code>POST /api/like</code> — 点赞</li>
                <li>更多功能陆续开放</li>
            </ul>

            <h3>用户信息响应示例</h3>
            <p><code>GET /api/user_me</code> 成功响应 (200 OK)：</p>
            <div class="code-block">
                {<br>
                &nbsp;&nbsp;<span class="hl">"id"</span>: <span class="str">"123456789"</span>,<br>
                &nbsp;&nbsp;<span class="hl">"username"</span>: <span class="str">"ragemi_user"</span>,<br>
                &nbsp;&nbsp;<span class="hl">"nickname"</span>: <span class="str">"瑞格米用户"</span>,<br>
                &nbsp;&nbsp;<span class="hl">"avatar"</span>: <span class="str">"https://ragemi.com/uploads/avatars/avatar.jpg"</span>,<br>
                &nbsp;&nbsp;<span class="hl">"email"</span>: <span class="str">"user@example.com"</span> <span class="cmt">// 仅当 scope 包含 email 时返回</span><br>
                }
            </div>
            <p>字段说明：</p>
            <table class="param-table">
                <tr><th>字段</th><th>说明</th></tr>
                <tr><td><code>id</code></td><td>用户唯一标识（建议作为你们系统的外部 UID）</td></tr>
                <tr><td><code>username</code></td><td>用户名</td></tr>
                <tr><td><code>nickname</code></td><td>显示昵称</td></tr>
                <tr><td><code>avatar</code></td><td>头像 URL</td></tr>
                <tr><td><code>email</code></td><td>邮箱（仅当 scope 包含 email 时返回，当前暂不开放）</td></tr>
            </table>
        </div>

        <!-- ===== 错误处理 ===== -->
        <div class="doc-section" id="errors">
            <h2>错误处理</h2>
            <p>当请求出错时，平台会返回标准的 HTTP 状态码和 JSON 错误体。</p>
            <h3>Token 换取失败示例 (400 Bad Request)</h3>
            <div class="code-block">
                {<br>
                &nbsp;&nbsp;<span class="hl">"error"</span>: <span class="str">"invalid_grant"</span>,<br>
                &nbsp;&nbsp;<span class="hl">"error_description"</span>: <span class="str">"授权码无效或已过期"</span><br>
                }
            </div>
            <p>常见 <code>error</code> 值说明：</p>
            <table class="param-table">
                <tr><th>error</th><th>说明</th></tr>
                <tr><td><code>invalid_request</code></td><td>请求参数缺失或格式错误</td></tr>
                <tr><td><code>invalid_client</code></td><td>client_id 或 client_secret 错误</td></tr>
                <tr><td><code>invalid_grant</code></td><td>授权码无效、已过期或 refresh_token 无效</td></tr>
                <tr><td><code>unsupported_grant_type</code></td><td>grant_type 不支持</td></tr>
                <tr><td><code>access_denied</code></td><td>用户拒绝授权</td></tr>
            </table>
            <h3>API 调用失败示例 (401 Unauthorized)</h3>
            <div class="code-block">
                {<br>
                &nbsp;&nbsp;<span class="hl">"code"</span>: 401,<br>
                &nbsp;&nbsp;<span class="hl">"msg"</span>: <span class="str">"未授权或 Token 已过期"</span><br>
                }
            </div>
            <p>若 Access Token 过期，应使用 Refresh Token 刷新后重试。</p>
        </div>

        <!-- ===== 常见问题 ===== -->
        <div class="doc-section" id="faq">
            <h2>常见问题</h2>

            <h3>Q1：点击“使用 Ragemi 登录”没反应，控制台报 CSP 错误</h3>
            <p><strong>原因：</strong> 您使用了表单（<code>&lt;form&gt;</code>）向 <code>ragemi.com</code> 提交请求，但您网站的 CSP 策略只允许同源提交（<code>form-action 'self'</code>）。</p>
            <p><strong>解决：</strong> 改用 <code>&lt;a&gt;</code> 链接或 <code>window.location.href</code> 重定向方式，详见 <a href="#auth-flow">授权流程</a> 章节。</p>

            <h3>Q2：提示“redirect_uri 不匹配”</h3>
            <p><strong>原因：</strong> 请求中的 <code>redirect_uri</code> 与你在开发者平台注册时填写的不完全一致（包括大小写、尾部斜杠、协议）。</p>
            <p><strong>解决：</strong> 检查两边是否完全一致。</p>

            <h3>Q3：提示“应用不存在或未审核通过”</h3>
            <p><strong>原因：</strong> 应用尚未通过管理员审核，或 <code>client_id</code> 错误。</p>
            <p><strong>解决：</strong> 联系管理员审核，或检查 Client ID 是否正确。</p>

            <h3>Q4：授权码换 Token 时返回 <code>invalid_grant</code></h3>
            <p><strong>原因：</strong> 授权码已过期（5分钟有效期），或已使用过一次。</p>
            <p><strong>解决：</strong> 重新走一遍授权流程获取新码。</p>

            <h3>Q5：Token 过期后如何续期？</h3>
            <p>使用 <code>refresh_token</code> 调用 <code>/oauth/token</code> 接口刷新，详见 <a href="#refresh">刷新 Token</a> 章节。</p>
        </div>

        <!-- ===== PKCE 安全建议 ===== -->
        <div class="doc-section" id="security">
            <h2>安全建议</h2>
            <div class="note-box">
                <strong>🔐 重要：</strong> 授权码模式要求 <code>client_secret</code> 必须保存在服务端，<strong>严禁</strong>在前端或移动客户端中暴露。<br>
                若您的应用为纯前端（SPA 单页应用）或原生客户端，建议后续关注平台对 <strong>PKCE (Proof Key for Code Exchange)</strong> 扩展的支持，以确保安全。
            </div>
        </div>

        <div style="margin-top: 20px;">
            <a href="/openplatform" class="op-btn op-btn-ghost">← 返回开发者平台</a>
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