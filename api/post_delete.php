<?php
// /api/post_delete.php - 删除帖子
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); exit; }
$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
if ($postId <= 0) { echo json_encode(['code' => 400, 'msg' => '无效的帖子ID']); exit; }
$result = deletePost($postId, $me['id']);
echo json_encode(['code' => $result ? 200 : 500, 'msg' => $result ? '已撤回' : '撤回失败']);