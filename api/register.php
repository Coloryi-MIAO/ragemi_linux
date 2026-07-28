<?php
// api/register.php - 注册
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$username = trim($_POST['username'] ?? '');
$display_name = trim($_POST['display_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$code = trim($_POST['code'] ?? '');

$result = createUser($username, $display_name, $email, $password, $code);
if (isset($result['success'])) {
    global $pdo;
    $s = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $s->execute([$username]);
    $user = $s->fetch();
    if ($user) {
        $_SESSION['uid'] = $user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
        $s = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
        $s->execute([$user['id'], $token, $expires, $token, $expires]);
        json_out(['code' => 200, 'data' => ['token' => $token, 'user' => $user]]);
    } else {
        json_out(['code' => 500, 'msg' => '注册成功但自动登录失败']);
    }
} else {
    json_out(['code' => 400, 'msg' => $result['error'] ?? '注册失败']);
}