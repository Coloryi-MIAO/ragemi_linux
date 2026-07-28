<?php
// /app/controllers/AuthController.php - OAuth 2.0
class AuthController {
    
    // 授权页面 (显示授权确认)
    public function authorize() {
        // 检查参数
        $clientId = $_GET['client_id'] ?? '';
        $redirectUri = $_GET['redirect_uri'] ?? '';
        $responseType = $_GET['response_type'] ?? '';
        $state = $_GET['state'] ?? '';
        
        // 验证 client_id
        global $pdo;
        $s = $pdo->prepare("SELECT * FROM oauth_apps WHERE client_id = ?");
        $s->execute([$clientId]);
        $app = $s->fetch();
        if (!$app) {
            die('无效的 client_id');
        }
        if ($app['redirect_uri'] !== $redirectUri) {
            die('redirect_uri 不匹配');
        }
        if ($responseType !== 'code') {
            die('仅支持 authorization code');
        }
        
        // 检查用户是否登录
        $me = me();
        if (!$me) {
            header('Location: /login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        
        // 如果是 POST 请求，用户确认授权
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
            $code = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 600); // 10分钟有效
            $s = $pdo->prepare("INSERT INTO oauth_codes (code, client_id, user_id, redirect_uri, expires_at) VALUES (?, ?, ?, ?, ?)");
            $s->execute([$code, $clientId, $me['id'], $redirectUri, $expires]);
            
            $redirectUrl = $redirectUri . (strpos($redirectUri, '?') === false ? '?' : '&') . 'code=' . $code;
            if ($state) {
                $redirectUrl .= '&state=' . urlencode($state);
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
        
        // 显示授权页面
        ?>
        <!DOCTYPE html>
        <html>
        <head><title>授权确认 - 瑞格米</title></head>
        <body style="font-family:sans-serif;padding:40px;background:#f5f0eb;color:#4a3f35;">
            <div style="max-width:500px;margin:0 auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.1);">
                <h1 style="font-size:20px;color:#7A5C2D;">授权确认</h1>
                <p><strong><?php echo e($app['name']); ?></strong> 请求访问您的账号：</p>
                <ul style="list-style:none;padding:0;">
                    <li>✔ 查看您的公开信息</li>
                    <li>✔ 以您的名义发帖</li>
                </ul>
                <form method="post">
                    <button type="submit" name="confirm" value="1" style="background:#7A5C2D;color:#fff;border:none;padding:10px 30px;border-radius:30px;font-size:16px;cursor:pointer;">允许</button>
                    <a href="/" style="color:#999;margin-left:20px;">取消</a>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // 颁发 Token
    public function token() {
        global $pdo;
        $grantType = $_POST['grant_type'] ?? '';
        $code = $_POST['code'] ?? '';
        $clientId = $_POST['client_id'] ?? '';
        $clientSecret = $_POST['client_secret'] ?? '';
        $refreshToken = $_POST['refresh_token'] ?? '';
        
        if ($grantType === 'authorization_code') {
            // 验证 code
            $s = $pdo->prepare("SELECT * FROM oauth_codes WHERE code = ? AND used = 0 AND expires_at > NOW()");
            $s->execute([$code]);
            $codeRow = $s->fetch();
            if (!$codeRow) {
                echo json_encode(['error' => 'invalid_grant', 'error_description' => '无效或已过期的授权码']);
                return;
            }
            // 验证 client
            $s = $pdo->prepare("SELECT * FROM oauth_apps WHERE client_id = ? AND client_secret = ?");
            $s->execute([$clientId, $clientSecret]);
            $app = $s->fetch();
            if (!$app) {
                echo json_encode(['error' => 'invalid_client', 'error_description' => '客户端认证失败']);
                return;
            }
            // 标记 code 已使用
            $s = $pdo->prepare("UPDATE oauth_codes SET used = 1 WHERE code = ?");
            $s->execute([$code]);
            
            // 生成 access_token 和 refresh_token
            $accessToken = bin2hex(random_bytes(32));
            $refreshToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1小时
            
            $s = $pdo->prepare("INSERT INTO oauth_tokens (access_token, refresh_token, client_id, user_id, expires_at) VALUES (?, ?, ?, ?, ?)");
            $s->execute([$accessToken, $refreshToken, $clientId, $codeRow['user_id'], $expires]);
            
            echo json_encode([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_token' => $refreshToken
            ]);
        } elseif ($grantType === 'refresh_token') {
            // 刷新 token
            $s = $pdo->prepare("SELECT * FROM oauth_tokens WHERE refresh_token = ? AND expires_at > NOW()");
            $s->execute([$refreshToken]);
            $tokenRow = $s->fetch();
            if (!$tokenRow) {
                echo json_encode(['error' => 'invalid_grant', 'error_description' => '无效的 refresh_token']);
                return;
            }
            // 生成新 access_token
            $newAccessToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $s = $pdo->prepare("UPDATE oauth_tokens SET access_token = ?, expires_at = ? WHERE refresh_token = ?");
            $s->execute([$newAccessToken, $expires, $refreshToken]);
            
            echo json_encode([
                'access_token' => $newAccessToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_token' => $refreshToken
            ]);
        } else {
            echo json_encode(['error' => 'unsupported_grant_type']);
        }
    }

    // 回调处理 (用于测试)
    public function callback() {
        // 用于接收授权码并换取 token (开发测试)
        echo "OAuth callback endpoint. Use token endpoint to exchange code.";
    }
}