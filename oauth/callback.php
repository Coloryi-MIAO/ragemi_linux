<?php
// /oauth/callback.php - Google OAuth 回调处理（调试版）
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// 初始化安全头
initSecurityHeaders();

// ===== 第一步：记录所有请求参数 =====
$error = $_GET['error'] ?? '';
$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

// 如果有错误参数，跳转回登录页显示错误
if (!empty($error)) {
    $errorMessages = [
        'access_denied' => '您拒绝了授权请求',
        'invalid_request' => '请求无效，请检查客户端配置',
        'unauthorized_client' => '客户端未授权，请检查 Client ID 和 Secret',
        'unsupported_response_type' => '不支持的响应类型',
        'invalid_scope' => '无效的权限范围',
        'server_error' => 'Google 服务器错误，请稍后重试',
        'temporarily_unavailable' => '服务暂时不可用，请稍后重试'
    ];
    $errorMsg = $errorMessages[$error] ?? '授权失败: ' . $error;
    $_SESSION['oauth_error'] = $errorMsg;
    header('Location: /login?error=' . urlencode($error));
    exit;
}

// 检查是否有授权码
if (empty($code)) {
    $_SESSION['oauth_error'] = '未收到授权码，请重新尝试登录 (code 为空)';
    header('Location: /login?error=no_code');
    exit;
}

// ===== 第二步：交换 Token =====
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $_SESSION['oauth_error'] = '获取 Token 失败 (HTTP ' . $httpCode . '): ' . substr($response, 0, 200);
    header('Location: /login?error=token_failed');
    exit;
}

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    $_SESSION['oauth_error'] = 'Token 响应无效: ' . substr($response, 0, 200);
    header('Location: /login?error=invalid_token');
    exit;
}

// ===== 第三步：获取用户信息 =====
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $tokenData['access_token']]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);

if (!isset($userData['email']) || !isset($userData['id'])) {
    $_SESSION['oauth_error'] = '获取用户信息失败: ' . substr($userResponse, 0, 200);
    header('Location: /login?error=user_info_failed');
    exit;
}

// ===== 第四步：查找或创建用户 =====
$email = $userData['email'];
$googleId = $userData['id'];
$displayName = $userData['name'] ?? $userData['email'];
$avatar = $userData['picture'] ?? '';

// 检查用户是否已存在
$s = $pdo->prepare("SELECT id, username, display_name, status FROM users WHERE email = ? OR google_id = ?");
$s->execute([$email, $googleId]);
$user = $s->fetch();

if ($user) {
    if ($user['status'] === 'banned') {
        $_SESSION['oauth_error'] = '账号已被封禁';
        header('Location: /login?error=banned');
        exit;
    }
    if (empty($user['google_id'])) {
        $s = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
        $s->execute([$googleId, $user['id']]);
    }
    $userId = $user['id'];
} else {
    // 创建新用户
    $username = 'google_' . substr($googleId, 0, 8);
    $s = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $s->execute([$username]);
    if ($s->fetch()) {
        $username = 'google_' . $googleId;
    }
    $subdomain = strtolower($username);
    $s = $pdo->prepare("SELECT id FROM users WHERE subdomain = ?");
    $s->execute([$subdomain]);
    if ($s->fetch()) {
        $subdomain = $subdomain . rand(100, 999);
    }
    
    $s = $pdo->prepare("INSERT INTO users (username, display_name, email, subdomain, avatar, google_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
    if (!$s->execute([$username, $displayName, $email, $subdomain, $avatar, $googleId])) {
        $_SESSION['oauth_error'] = '创建用户失败';
        header('Location: /login?error=create_failed');
        exit;
    }
    $userId = $pdo->lastInsertId();
}

// ===== 第五步：登录用户 =====
// 确保会话是新的
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH / 2));

// 记录登录
$s = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
$s->execute([$userId]);

// 设置 Cookie（记住登录）
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
$s = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
$s->execute([$userId, $token, $expires, $token, $expires]);
setcookie('ragemi-token', $token, [
    'expires' => time() + 7 * 24 * 3600,
    'path' => '/',
    'domain' => COOKIE_DOMAIN,
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
]);

// ===== 第六步：验证登录是否成功 =====
// 检查 session 是否保存成功
$checkUser = me();
if ($checkUser && $checkUser['id'] == $userId) {
    // 登录成功，跳转首页
    header('Location: /');
    exit;
} else {
    // 登录失败，记录日志
    error_log('[Google OAuth] 登录验证失败: user_id=' . $userId . ', session=' . session_id());
    $_SESSION['oauth_error'] = '登录验证失败，请重试';
    header('Location: /login?error=session_failed');
    exit;
}