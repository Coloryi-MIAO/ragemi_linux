<?php
// /api/notifications_read.php - 标记已读
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); exit; }
markNotificationsRead($me['id']);
echo json_encode(['code' => 200, 'msg' => '已全部标记已读']);