<?php
// oauth/token.php - OAuth 2.0 Token 颁发端点
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed', 'error_description' => '只支持 POST 请求']);
    exit;
}

// 获取请求参数
$grant_type = $_POST['grant_type'] ?? '';
$code = $_POST['code'] ?? '';
$client_id = $_POST['client_id'] ?? '';
$client_secret = $_POST['client_secret'] ?? '';
$redirect_uri = $_POST['redirect_uri'] ?? '';
$refresh_token = $_POST['refresh_token'] ?? '';

// ---- 验证 grant_type ----
if (!in_array($grant_type, ['authorization_code', 'refresh_token'])) {
    http_response_code(400);
    echo json_encode(['error' => 'unsupported_grant_type', 'error_description' => '不支持的 grant_type']);
    exit;
}

// ---- 验证 client_id ----
if (empty($client_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_request', 'error_description' => '缺少 client_id']);
    exit;
}

// 查找应用
$s = $pdo->prepare("SELECT * FROM oauth_apps WHERE client_id = ? AND status = 'approved'");
$s->execute([$client_id]);
$app = $s->fetch();

if (!$app) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_client', 'error_description' => '应用不存在或未审核通过']);
    exit;
}

// ---- 验证 client_secret ----
if (empty($client_secret) || $client_secret !== $app['client_secret']) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_client', 'error_description' => 'client_secret 错误']);
    exit;
}

// ---- 授权码模式 ----
if ($grant_type === 'authorization_code') {
    // 验证 code
    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request', 'error_description' => '缺少 code']);
        exit;
    }

    // 验证 redirect_uri
    if (empty($redirect_uri)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request', 'error_description' => '缺少 redirect_uri']);
        exit;
    }

    // 查找授权码
    $s = $pdo->prepare("SELECT * FROM oauth_codes WHERE code = ? AND client_id = ? AND used = 0 AND expires_at > NOW()");
    $s->execute([$code, $client_id]);
    $authCode = $s->fetch();

    if (!$authCode) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_grant', 'error_description' => '授权码无效或已过期']);
        exit;
    }

    // 验证 redirect_uri
    if ($redirect_uri !== $authCode['redirect_uri']) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_grant', 'error_description' => 'redirect_uri 不匹配']);
        exit;
    }

    // 标记授权码为已使用
    $s = $pdo->prepare("UPDATE oauth_codes SET used = 1 WHERE id = ?");
    $s->execute([$authCode['id']]);

    // 生成 Access Token 和 Refresh Token
    $access_token = bin2hex(random_bytes(32));
    $refresh_token = bin2hex(random_bytes(32));
    $expires_in = OAUTH_TOKEN_EXPIRE; // 默认 3600 秒
    $expires_at = date('Y-m-d H:i:s', time() + $expires_in);

    // 保存 Token
    $s = $pdo->prepare("INSERT INTO oauth_tokens (access_token, refresh_token, client_id, user_id, expires_at, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $s->execute([$access_token, $refresh_token, $client_id, $authCode['user_id'], $expires_at]);

    // 返回 Token
    echo json_encode([
        'access_token' => $access_token,
        'token_type' => 'Bearer',
        'expires_in' => $expires_in,
        'refresh_token' => $refresh_token
    ]);
    exit;
}

// ---- 刷新 Token 模式 ----
if ($grant_type === 'refresh_token') {
    if (empty($refresh_token)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_request', 'error_description' => '缺少 refresh_token']);
        exit;
    }

    // 查找 Refresh Token
    $s = $pdo->prepare("SELECT * FROM oauth_tokens WHERE refresh_token = ? AND client_id = ? AND expires_at > NOW()");
    $s->execute([$refresh_token, $client_id]);
    $token = $s->fetch();

    if (!$token) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_grant', 'error_description' => 'refresh_token 无效或已过期']);
        exit;
    }

    // 生成新的 Access Token
    $new_access_token = bin2hex(random_bytes(32));
    $expires_in = OAUTH_TOKEN_EXPIRE;
    $expires_at = date('Y-m-d H:i:s', time() + $expires_in);

    // 更新 Token
    $s = $pdo->prepare("UPDATE oauth_tokens SET access_token = ?, expires_at = ? WHERE id = ?");
    $s->execute([$new_access_token, $expires_at, $token['id']]);

    // 返回新 Token（Refresh Token 不变）
    echo json_encode([
        'access_token' => $new_access_token,
        'token_type' => 'Bearer',
        'expires_in' => $expires_in,
        'refresh_token' => $refresh_token
    ]);
    exit;
}