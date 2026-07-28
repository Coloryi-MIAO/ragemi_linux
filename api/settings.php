<?php
// api/settings.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$me = me();
if (!$me) json_out(['code' => 401, 'msg' => '请先登录']);
if (!verifyCsrf($_POST['csrf'] ?? '')) json_out(['code' => 403, 'msg' => 'CSRF 验证失败']);

$data = [];
if (isset($_POST['username'])) $data['username'] = trim($_POST['username']);
if (isset($_POST['subdomain'])) $data['subdomain'] = trim($_POST['subdomain']);
if (isset($_POST['bio'])) $data['bio'] = trim($_POST['bio']);
if (!empty($_POST['password'])) {
    if (strlen($_POST['password']) < PASSWORD_MIN_LENGTH) {
        json_out(['code' => 400, 'msg' => '密码至少6位']);
    }
    $data['password'] = $_POST['password'];
}

if (updateUser($me['id'], $data)) {
    json_out(['code' => 200, 'msg' => '更新成功']);
} else {
    json_out(['code' => 500, 'msg' => '更新失败']);
}