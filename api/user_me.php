<?php
// /api/user_me.php - 当前用户
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if ($me) { echo json_encode(['code' => 200, 'data' => $me]); }
else { echo json_encode(['code' => 401, 'msg' => '未登录']); }