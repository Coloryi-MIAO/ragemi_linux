<?php
// /api/notifications.php - 通知列表
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); exit; }
$notifications = getNotifications($me['id'], 50);
echo json_encode(['code' => 200, 'data' => ['list' => $notifications]]);