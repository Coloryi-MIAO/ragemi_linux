<?php
// /api/user_posts.php - 用户帖子
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($userId <= 0) { echo json_encode(['code' => 400, 'msg' => '无效的用户ID']); exit; }
$me = me();
$posts = getUserPosts($userId, $page, 20, $me['id'] ?? null);
foreach ($posts as &$post) { $post['top_replies'] = getTopReplies($post['id'], 2, $me['id'] ?? null); }
$s = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND status='normal' AND parent_id IS NULL");
$s->execute([$userId]);
$total = $s->fetchColumn();
$hasMore = ($page * 20) < $total;
echo json_encode(['code' => 200, 'data' => ['posts' => $posts, 'has_more' => $hasMore, 'page' => $page]]);