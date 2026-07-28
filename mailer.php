<?php
// mailer.php - 瑞格米邮件发送
// QQ邮箱SMTP发送 - 与网站风格统一

function send_email_code($to_email, $code, $type = 'verify') {
    error_log("[Ragemi Mail] 开始发送邮件到: $to_email, 类型: $type");
    
    $smtp_host = 'smtp.qq.com';
    $smtp_port = 465;
    $smtp_user = '3849522479@qq.com';
    $smtp_pass = 'idwfbxbcjpeiccji';
    $from_name = '瑞格米 · Ragemi';

    // ===== 根据类型生成不同标题和内容 =====
    $typeMap = [
        'verify' => [
            'title' => '邮箱验证码',
            'desc' => '正在进行身份验证',
            'action' => '验证'
        ],
        'register' => [
            'title' => '注册验证码',
            'desc' => '正在注册瑞格米账号',
            'action' => '注册'
        ],
        '2fa' => [
            'title' => '二次验证码',
            'desc' => '正在登录二次验证',
            'action' => '登录验证'
        ],
        'email_change' => [
            'title' => '换绑邮箱验证码',
            'desc' => '正在更换绑定邮箱',
            'action' => '换绑验证'
        ]
    ];
    
    $info = $typeMap[$type] ?? $typeMap['verify'];
    $subject = '【瑞格米】' . $info['title'];

    // ★★★ 修复：@username 改为显示"用户" ★★★
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '用户';

    $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Microsoft YaHei,PingFang SC,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:40px 0;">
<tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.55);backdrop-filter:blur(12px);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.8);box-shadow:0 8px 32px rgba(150,170,190,0.15);">

<!-- ===== 顶部 Logo ===== -->
<tr><td style="background:#7A5C2D;padding:24px 24px 16px;text-align:center;">
    <img src="https://ragemi.com/s/top.png" alt="瑞格米" style="height:38px;width:auto;display:inline-block;filter:brightness(0) invert(1);">
    <p style="color:rgba(255,255,255,0.7);font-size:12px;margin:6px 0 0;letter-spacing:3px;">二次元同好聚集地</p>
</td></tr>

<!-- ===== 正文 ===== -->
<tr><td style="padding:32px 28px 24px;color:#4a6075;font-size:15px;line-height:1.8;">

    <p style="margin:0 0 6px;font-size:17px;font-weight:600;color:#4a6075;">
        👋 你好，<span style="color:#7A5C2D;">' . $username . '</span>
    </p>
    <p style="margin:0 0 16px;color:#5a6f84;font-size:14px;">
        你正在 <strong style="color:#7A5C2D;">' . $info['desc'] . '</strong>，你的验证码为：
    </p>

    <!-- ===== 验证码 ===== -->
    <div style="background:rgba(122,92,45,0.05);border:2px dashed rgba(122,92,45,0.15);border-radius:12px;padding:18px 16px;text-align:center;margin:0 0 18px;">
        <span style="font-size:38px;font-weight:700;color:#7A5C2D;letter-spacing:10px;font-family:monospace;">' . $code . '</span>
    </div>

    <!-- ===== 提示 ===== -->
    <div style="background:rgba(122,92,45,0.03);border-radius:10px;padding:12px 16px;margin:0 0 12px;">
        <p style="margin:0;color:#5a6f84;font-size:13px;line-height:1.6;">
            💡 请勿将验证码告诉他人<br>
            ⏱️ 验证码有效期 <strong style="color:#7A5C2D;">10分钟</strong>，请尽快使用。
        </p>
    </div>

    <!-- ===== 垃圾邮件提示 ===== -->
    <div style="background:rgba(122,92,45,0.02);border:1px solid rgba(122,92,45,0.06);border-radius:8px;padding:10px 14px;font-size:12px;color:#8a9db0;line-height:1.6;">
        📬 如果收件箱未收到，请检查 <strong>垃圾邮件</strong> 或 <strong>广告邮件</strong> 文件夹
    </div>

</td></tr>

<!-- ===== 页脚（与网站完全一致） ===== -->
<tr><td style="background:rgba(122,92,45,0.03);padding:20px 24px 18px;text-align:center;border-top:1px solid rgba(150,170,190,0.08);">

    <div style="margin-bottom:8px;">
        <img src="https://ragemi.com/s/top.png" alt="瑞格米" style="height:28px;width:auto;display:inline-block;opacity:0.6;">
    </div>

    <div style="color:#8a9db0;font-size:12px;letter-spacing:1px;line-height:1.8;">
        © 2026 瑞格米 · 二次元帖子分享站
        <span style="color:#c9a87b;margin:0 8px;">·</span>
        <a href="https://ragemi.com" style="color:#7A5C2D;text-decoration:none;">ragemi.com</a>
    </div>

</td></tr>

</table>
</td></tr>
</table>
</body>
</html>';

    // ===== 构建邮件头 =====
    $headers = "From: {$from_name} <{$smtp_user}>\r\n";
    $headers .= "Reply-To: {$smtp_user}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: Ragemi Mailer\r\n";
    $headers .= "Subject: " . $subject . "\r\n";

    // SMTP 发送（保持不变）
    $socket = @fsockopen('ssl://' . $smtp_host, $smtp_port, $errno, $errstr, 30);
    
    if (!$socket) {
        error_log("[Ragemi Mail] SSL 连接失败: $errstr ($errno)，尝试普通连接");
        $socket = @fsockopen($smtp_host, 587, $errno, $errstr, 30);
        if (!$socket) {
            error_log("[Ragemi Mail] 所有连接都失败: $errstr ($errno)");
            return false;
        }
        
        fgets($socket, 512);
        fputs($socket, "EHLO ragemi.com\r\n");
        while ($line = fgets($socket, 512)) { if (substr($line, 3, 1) == ' ') break; }
        fputs($socket, "STARTTLS\r\n");
        fgets($socket, 512);
        @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fputs($socket, "EHLO ragemi.com\r\n");
        while ($line = fgets($socket, 512)) { if (substr($line, 3, 1) == ' ') break; }
    } else {
        error_log("[Ragemi Mail] SSL 连接成功");
        fgets($socket, 512);
        fputs($socket, "EHLO ragemi.com\r\n");
        while ($line = fgets($socket, 512)) { if (substr($line, 3, 1) == ' ') break; }
    }

    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $resp = fgets($socket, 512);
    if (substr($resp, 0, 3) != '334') { 
        error_log("[Ragemi Mail] AUTH LOGIN 失败: $resp");
        fclose($socket); 
        return false; 
    }
    
    fputs($socket, base64_encode($smtp_user) . "\r\n");
    $resp = fgets($socket, 512);
    if (substr($resp, 0, 3) != '334') { 
        error_log("[Ragemi Mail] USER 验证失败: $resp");
        fclose($socket); 
        return false; 
    }
    
    fputs($socket, base64_encode($smtp_pass) . "\r\n");
    $resp = fgets($socket, 512);
    if (substr($resp, 0, 3) != '235') { 
        error_log("[Ragemi Mail] PASS 验证失败: $resp");
        fclose($socket); 
        return false; 
    }
    error_log("[Ragemi Mail] SMTP 认证成功");

    // 发送邮件
    fputs($socket, "MAIL FROM:<{$smtp_user}>\r\n");
    fgets($socket, 512);
    fputs($socket, "RCPT TO:<{$to_email}>\r\n");
    fgets($socket, 512);
    fputs($socket, "DATA\r\n");
    fgets($socket, 512);
    
    $mail_content = $headers . "\r\n" . $body . "\r\n.\r\n";
    fputs($socket, $mail_content);
    
    $response = fgets($socket, 512);
    error_log("[Ragemi Mail] 邮件发送响应: $response");
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    $success = substr($response, 0, 3) == '250';
    error_log("[Ragemi Mail] 发送结果: " . ($success ? '成功' : '失败'));
    return $success;
}

// ============================================================
// 原有函数（用条件判断包裹，避免重复声明）
// ============================================================
if (!function_exists('sendVerificationCode')) {
    function sendVerificationCode($to_email, $code) {
        return send_email_code($to_email, $code, 'verify');
    }
}

if (!function_exists('send2FACode')) {
    function send2FACode($to_email, $code) {
        return send_email_code($to_email, $code, '2fa');
    }
}

if (!function_exists('sendEmailChangeCode')) {
    function sendEmailChangeCode($to_email, $code) {
        return send_email_code($to_email, $code, 'email_change');
    }
}

// ============================================================
// 新增：密码重置邮件发送（与网站风格统一）
// ============================================================
if (!function_exists('send_password_reset_email')) {
    function send_password_reset_email($to_email, $username, $reset_link) {
        $smtp_host = 'smtp.qq.com';
        $smtp_port = 465;
        $smtp_user = '3849522479@qq.com';
        $smtp_pass = 'idwfbxbcjpeiccji';
        $from_name = '瑞格米 · Ragemi';

        $subject = '【瑞格米】密码重置';

        // ★★★ 修复：@username 改为显示"用户" ★★★
        $displayName = $username ?: '用户';

        $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Microsoft YaHei,PingFang SC,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:40px 0;">
<tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="background:rgba(255,255,255,0.55);backdrop-filter:blur(12px);border-radius:20px;overflow:hidden;border:1px solid rgba(255,255,255,0.8);box-shadow:0 8px 32px rgba(150,170,190,0.15);">

<tr><td style="background:#7A5C2D;padding:24px 24px 16px;text-align:center;">
    <img src="https://ragemi.com/s/top.png" alt="瑞格米" style="height:38px;width:auto;display:inline-block;filter:brightness(0) invert(1);">
    <p style="color:rgba(255,255,255,0.7);font-size:12px;margin:6px 0 0;letter-spacing:3px;">二次元同好聚集地</p>
</td></tr>

<tr><td style="padding:32px 28px 24px;color:#4a6075;font-size:15px;line-height:1.8;">
    <p style="margin:0 0 6px;font-size:17px;font-weight:600;color:#4a6075;">
        👋 你好，<span style="color:#7A5C2D;">' . $displayName . '</span>
    </p>
    <p style="margin:0 0 16px;color:#5a6f84;font-size:14px;">
        我们收到了你的密码重置请求，请点击下方按钮重置密码：
    </p>

    <div style="text-align:center;margin:20px 0;">
        <a href="' . $reset_link . '" style="display:inline-block;padding:14px 40px;background:#7A5C2D;color:#fff;border-radius:30px;text-decoration:none;font-weight:600;font-size:16px;transition:background 0.2s;">
            重置密码
        </a>
    </div>

    <div style="background:rgba(122,92,45,0.03);border-radius:10px;padding:12px 16px;margin:0 0 12px;">
        <p style="margin:0;color:#5a6f84;font-size:13px;line-height:1.6;">
            ⏱️ 链接有效期 <strong style="color:#7A5C2D;">1小时</strong>，请尽快使用。
        </p>
    </div>

    <div style="background:rgba(122,92,45,0.02);border:1px solid rgba(122,92,45,0.06);border-radius:8px;padding:10px 14px;font-size:12px;color:#8a9db0;line-height:1.6;">
        📬 如果收件箱未收到，请检查 <strong>垃圾邮件</strong> 或 <strong>广告邮件</strong> 文件夹
    </div>

</td></tr>

<tr><td style="background:rgba(122,92,45,0.03);padding:20px 24px 18px;text-align:center;border-top:1px solid rgba(150,170,190,0.08);">

    <div style="margin-bottom:8px;">
        <img src="https://ragemi.com/s/top.png" alt="瑞格米" style="height:28px;width:auto;display:inline-block;opacity:0.6;">
    </div>

    <div style="color:#8a9db0;font-size:12px;letter-spacing:1px;line-height:1.8;">
        © 2026 瑞格米 · 二次元帖子分享站
        <span style="color:#c9a87b;margin:0 8px;">·</span>
        <a href="https://ragemi.com" style="color:#7A5C2D;text-decoration:none;">ragemi.com</a>
    </div>

</td></tr>

</table>
</td></tr>
</table>
</body>
</html>';

        $headers = "From: {$from_name} <{$smtp_user}>\r\n";
        $headers .= "Reply-To: {$smtp_user}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Ragemi Mailer\r\n";
        $headers .= "Subject: " . $subject . "\r\n";

        // SMTP 发送（与上面相同）
        $socket = @fsockopen('ssl://' . $smtp_host, $smtp_port, $errno, $errstr, 30);
        
        if (!$socket) {
            error_log("[Ragemi Mail] SSL 连接失败: $errstr ($errno)，尝试普通连接");
            $socket = @fsockopen($smtp_host, 587, $errno, $errstr, 30);
            if (!$socket) {
                error_log("[Ragemi Mail] 所有连接都失败: $errstr ($errno)");
                return false;
            }
            
            fgets($socket, 512);
            fputs($socket, "EHLO ragemi.com\r\n");
            while ($line = fgets($socket, 512)) { if (substr($line, 3, 1) == ' ') break; }
            fputs($socket, "STARTTLS\r\n");
            fgets($socket, 512);
            @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO ragemi.com\r\n");
            while ($line = fgets($socket, 512)) { if (substr($line, 3, 1) == ' ') break; }
        } else {
            error_log("[Ragemi Mail] SSL 连接成功");
            fgets($socket, 512);
            fputs($socket, "EHLO ragemi.com\r\n");
            while ($line = fgets($socket, 512)) { if (substr($line, 3, 1) == ' ') break; }
        }

        fputs($socket, "AUTH LOGIN\r\n");
        $resp = fgets($socket, 512);
        if (substr($resp, 0, 3) != '334') { 
            error_log("[Ragemi Mail] AUTH LOGIN 失败: $resp");
            fclose($socket); 
            return false; 
        }
        
        fputs($socket, base64_encode($smtp_user) . "\r\n");
        $resp = fgets($socket, 512);
        if (substr($resp, 0, 3) != '334') { 
            error_log("[Ragemi Mail] USER 验证失败: $resp");
            fclose($socket); 
            return false; 
        }
        
        fputs($socket, base64_encode($smtp_pass) . "\r\n");
        $resp = fgets($socket, 512);
        if (substr($resp, 0, 3) != '235') { 
            error_log("[Ragemi Mail] PASS 验证失败: $resp");
            fclose($socket); 
            return false; 
        }
        error_log("[Ragemi Mail] SMTP 认证成功");

        fputs($socket, "MAIL FROM:<{$smtp_user}>\r\n");
        fgets($socket, 512);
        fputs($socket, "RCPT TO:<{$to_email}>\r\n");
        fgets($socket, 512);
        fputs($socket, "DATA\r\n");
        fgets($socket, 512);
        
        $mail_content = $headers . "\r\n" . $body . "\r\n.\r\n";
        fputs($socket, $mail_content);
        
        $response = fgets($socket, 512);
        error_log("[Ragemi Mail] 密码重置邮件发送响应: $response");
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        $success = substr($response, 0, 3) == '250';
        error_log("[Ragemi Mail] 密码重置邮件发送结果: " . ($success ? '成功' : '失败'));
        return $success;
    }
}