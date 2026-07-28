<?php
// /api/follow.php - 关注
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); exit; }
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($userId <= 0 || $userId == $me['id']) { echo json_encode(['code' => 400, 'msg' => '无效的用户']); exit; }
$result = followUser($me['id'], $userId);
echo json_encode(['code' => 200, 'msg' => '操作成功', 'data' => ['following' => $result]]);