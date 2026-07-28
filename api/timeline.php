<?php
// /api/timeline.php - 加载更多
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$posts = getPosts(null, $page, PAGE_SIZE, $me['id'] ?? null);
$stmt = $pdo->query("SELECT COUNT(*) FROM posts WHERE status='normal' AND parent_id IS NULL");
$total = $stmt->fetchColumn();
$hasMore = ($page * PAGE_SIZE) < $total;
foreach ($posts as &$post) { $post['top_replies'] = getTopReplies($post['id'], 2, $me['id'] ?? null); }
echo json_encode(['code' => 200, 'data' => ['posts' => $posts, 'has_more' => $hasMore, 'page' => $page]]);