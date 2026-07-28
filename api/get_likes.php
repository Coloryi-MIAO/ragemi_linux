<?php
// api/get_likes.php - 批量获取点赞数
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$ids = $_GET['ids'] ?? '';
if (empty($ids)) {
    json_out(['code' => 400, 'msg' => '缺少参数']);
}

$idArray = array_filter(array_map('intval', explode(',', $ids)));
if (empty($idArray)) {
    json_out(['code' => 400, 'msg' => '无效参数']);
}

$placeholders = implode(',', array_fill(0, count($idArray), '?'));
$s = $pdo->prepare("SELECT id, like_count FROM posts WHERE id IN ($placeholders) AND status='normal'");
$s->execute($idArray);
$results = $s->fetchAll();

$data = [];
foreach ($results as $row) {
    $data[] = ['id' => (int)$row['id'], 'count' => (int)$row['like_count']];
}

json_out(['code' => 200, 'data' => $data]);