<?php
// /api/comment_create.php - 创建评论
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); exit; }
$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$content = trim($_POST['content'] ?? '');
if ($postId <= 0 || empty($content)) { echo json_encode(['code' => 400, 'msg' => '参数不完整']); exit; }
if (mb_strlen($content) > 500) { echo json_encode(['code' => 400, 'msg' => '评论不能超过500字']); exit; }
$post = getPostById($postId);
if (!$post) { echo json_encode(['code' => 404, 'msg' => '帖子不存在']); exit; }
$replyId = createPost($me['id'], $content, null, $postId);
if ($replyId) { createNotification($postId, $me['id'], 'reply'); echo json_encode(['code' => 200, 'msg' => '评论成功', 'data' => ['reply_id' => $replyId]]); }
else { echo json_encode(['code' => 500, 'msg' => '评论失败']); }