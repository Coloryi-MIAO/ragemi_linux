<?php
// api/recommend_posts.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$me = me();
if (!$me) {
    json_out(['code' => 401, 'msg' => '请先登录']);
}

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$posts = getRecommendedPosts($me['id'], $limit, $offset);
foreach ($posts as &$post) {
    $post['top_replies'] = getTopReplies($post['id'], 2, $me['id'] ?? null);
}

json_out(['code' => 200, 'data' => ['posts' => $posts]]);