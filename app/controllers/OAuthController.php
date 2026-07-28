<?php
// /www/wwwroot/ragemi.com/app/controllers/OAuthController.php

class OAuthController
{
    private $clientId = '656996971471-k9o8qo4faddn6are54tkb1uh9e2sfk4n.apps.googleusercontent.com';
    private $clientSecret = ''; // 需要从 Google Cloud 获取
    private $redirectUri = 'https://ragemi.com/oauth/callback';
    private $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $tokenUrl = 'https://oauth2.googleapis.com/token';
    private $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    /**
     * 发起 Google 登录
     */
    public function login() {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account'
        ];
        
        $url = $this->authUrl . '?' . http_build_query($params);
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Google 回调处理
     */
    public function callback() {
        // 验证 state（防止 CSRF）
        if (!isset($_GET['state']) || !isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
            $this->redirectWithError('state_mismatch');
        }
        unset($_SESSION['oauth_state']);
        
        // 获取授权码
        if (!isset($_GET['code'])) {
            $this->redirectWithError('no_code');
        }
        $code = $_GET['code'];
        
        // 交换 access_token
        $tokenData = $this->exchangeCodeForToken($code);
        if (!$tokenData || !isset($tokenData['access_token'])) {
            $this->redirectWithError('token_exchange_failed');
        }
        
        // 获取用户信息
        $userInfo = $this->getUserInfo($tokenData['access_token']);
        if (!$userInfo || !isset($userInfo['email'])) {
            $this->redirectWithError('user_info_failed');
        }
        
        // 登录或注册用户
        $result = $this->loginOrRegisterUser($userInfo);
        if ($result['success']) {
            // 登录成功
            session_regenerate_id(true);
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH / 2));
            
            // 设置记住我 Cookie
            if (isset($result['token'])) {
                setcookie('ragemi-token', $result['token'], [
                    'expires' => time() + 7 * 24 * 3600,
                    'path' => '/',
                    'domain' => COOKIE_DOMAIN,
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
                ]);
            }
            
            header('Location: /');
            exit;
        } else {
            $this->redirectWithError($result['error'] ?? 'unknown');
        }
    }
    
    /**
     * 用授权码换取 access_token
     */
    private function exchangeCodeForToken($code) {
        $data = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $ch = curl_init($this->tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * 获取用户信息
     */
    private function getUserInfo($accessToken) {
        $ch = curl_init($this->userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    /**
     * 登录或注册用户
     */
    private function loginOrRegisterUser($userInfo) {
        global $pdo;
        
        $email = $userInfo['email'];
        $googleId = $userInfo['id'] ?? '';
        $displayName = $userInfo['name'] ?? $userInfo['given_name'] ?? '';
        $avatar = $userInfo['picture'] ?? '';
        
        // 检查是否已存在通过 Google 登录的用户
        $s = $pdo->prepare("SELECT user_id FROM oauth_accounts WHERE provider = 'google' AND provider_id = ?");
        $s->execute([$googleId]);
        $existing = $s->fetch();
        
        if ($existing) {
            // 已有 Google 账号绑定，直接登录
            $s = $pdo->prepare("SELECT id, status FROM users WHERE id = ?");
            $s->execute([$existing['user_id']]);
            $user = $s->fetch();
            
            if (!$user || $user['status'] === 'banned') {
                return ['success' => false, 'error' => '账号已被禁用'];
            }
            
            return ['success' => true, 'user_id' => $user['id']];
        }
        
        // 检查邮箱是否已被注册
        $s = $pdo->prepare("SELECT id, status FROM users WHERE email = ?");
        $s->execute([$email]);
        $existingUser = $s->fetch();
        
        if ($existingUser) {
            // 邮箱已存在，绑定 Google 账号
            if ($existingUser['status'] === 'banned') {
                return ['success' => false, 'error' => '账号已被禁用'];
            }
            
            $s = $pdo->prepare("INSERT INTO oauth_accounts (user_id, provider, provider_id, created_at) VALUES (?, 'google', ?, NOW())");
            $s->execute([$existingUser['id'], $googleId]);
            
            return ['success' => true, 'user_id' => $existingUser['id']];
        }
        
        // 创建新用户
        $username = $this->generateUsername($email);
        $subdomain = strtolower($username);
        
        // 检查子域名是否冲突
        $s = $pdo->prepare("SELECT id FROM users WHERE subdomain = ?");
        $s->execute([$subdomain]);
        if ($s->fetch()) {
            $subdomain = $subdomain . rand(100, 999);
        }
        
        // 生成随机密码（用户以后可以用邮箱重置密码）
        $password = bin2hex(random_bytes(8));
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $s = $pdo->prepare("INSERT INTO users (username, display_name, email, subdomain, password_hash, avatar, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $s->execute([$username, $displayName, $email, $subdomain, $passwordHash, $avatar]);
        $userId = $pdo->lastInsertId();
        
        // 绑定 Google 账号
        $s = $pdo->prepare("INSERT INTO oauth_accounts (user_id, provider, provider_id, created_at) VALUES (?, 'google', ?, NOW())");
        $s->execute([$userId, $googleId]);
        
        return ['success' => true, 'user_id' => $userId];
    }
    
    /**
     * 生成用户名
     */
    private function generateUsername($email) {
        global $pdo;
        
        $base = strtolower(explode('@', $email)[0]);
        $base = preg_replace('/[^a-z0-9_]/', '', $base);
        if (empty($base)) $base = 'user';
        
        $username = $base;
        $counter = 1;
        
        while (true) {
            $s = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $s->execute([$username]);
            if (!$s->fetch()) {
                return $username;
            }
            $username = $base . $counter;
            $counter++;
        }
    }
    
    /**
     * 重定向并显示错误
     */
    private function redirectWithError($error) {
        header('Location: /login?error=' . urlencode($error));
        exit;
    }
}