<?php
// /api/like.php - 点赞
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); exit; }
$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
if ($postId <= 0) { echo json_encode(['code' => 400, 'msg' => '无效的帖子ID']); exit; }
$result = toggleLike($me['id'], $postId);
echo json_encode(['code' => 200, 'msg' => '操作成功', 'data' => ['liked' => $result]]);