<?php
// openplatform/logout.php - 退出开发者平台
require_once __DIR__ . '/../config.php';
session_destroy();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
header('Location: /login');
exit;