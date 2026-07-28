<?php
// api/login.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    json_out(['code' => 400, 'msg' => '用户名和密码不能为空']);
}

global $pdo;
$s = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ? OR email = ?");
$s->execute([$username, $username]);
$user = $s->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_out(['code' => 401, 'msg' => '用户名或密码错误']);
}

$_SESSION['uid'] = $user['id'];
$_SESSION['csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
$s = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
$s->execute([$user['id'], $token, $expires, $token, $expires]);

json_out(['code' => 200, 'data' => ['token' => $token]]);