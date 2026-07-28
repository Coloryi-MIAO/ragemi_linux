<?php
// /api/logout.php - 退出
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
session_destroy();
if (isset($_COOKIE['ragemi-token'])) { setcookie('ragemi-token', '', time() - 3600, '/', COOKIE_DOMAIN, false, true); }
echo json_encode(['code' => 200, 'msg' => '已退出']);