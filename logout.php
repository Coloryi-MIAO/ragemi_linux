<?php
// logout.php - 退出登录
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 初始化安全头
initSecurityHeaders();

// 检查 CSRF（仅 POST 请求需要）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        die('CSRF 验证失败');
    }
}

// 销毁服务端会话
session_destroy();

// 清除客户端 Session Cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 清除 ragemi-token Cookie
if (isset($_COOKIE['ragemi-token'])) {
    setcookie('ragemi-token', '', time() - 3600, '/', COOKIE_DOMAIN, true, true);
}

// 重定向到首页
header('Location: /');
exit;