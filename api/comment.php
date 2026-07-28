<?php
// api/comment.php - 获取评论列表
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$postId = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
if (!$postId) {
    json_out(['code' => 400, 'msg' => '缺少帖子ID']);
}

$userId = $_SESSION['uid'] ?? null;
$comments = getPostReplies($postId, 50, $userId);
json_out(['code' => 200, 'data' => $comments]);