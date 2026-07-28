<?php
// api/check_email.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

$email = trim($_GET['email'] ?? '');
$exclude = isset($_GET['exclude']) ? intval($_GET['exclude']) : 0;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(['code' => 400, 'msg' => '邮箱格式无效']);
}

global $pdo;
$sql = "SELECT id FROM users WHERE email = ?";
$params = [$email];
if ($exclude > 0) {
    $sql .= " AND id != ?";
    $params[] = $exclude;
}
$s = $pdo->prepare($sql);
$s->execute($params);

if ($s->fetch()) {
    json_out(['code' => 400, 'msg' => '该邮箱已被注册']);
} else {
    json_out(['code' => 200, 'msg' => '邮箱可用']);
}