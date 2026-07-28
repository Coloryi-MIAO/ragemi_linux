<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

$path = isset($_GET['path']) ? $_GET['path'] : '';
$path = trim($path, '/');

if (empty($path)) {
    echo json_encode(['code' => 200, 'msg' => 'Ragemi API', 'version' => '2.0']);
    exit;
}

$routes = [
    'like'              => 'LikeController@toggle',
    'post_create'       => 'PostController@create',
    'post_delete'       => 'PostController@delete',
    'timeline'          => 'PostController@timeline',
    'comment_create'    => 'CommentController@create',
    'follow'            => 'FollowController@toggle',
    'notifications'     => 'NotificationController@list',
    'notifications_read'=> 'NotificationController@readAll',
    'user_me'           => 'UserController@me',
    'logout'            => 'UserController@logout',
    'send_verification' => 'UserController@sendVerification',
    'resend_2fa'        => 'UserController@resend2FA',
];

if (!isset($routes[$path])) {
    http_response_code(404);
    echo json_encode(['code' => 404, 'msg' => 'API endpoint not found']);
    exit;
}

list($controllerName, $method) = explode('@', $routes[$path]);
$controllerFile = __DIR__ . '/../app/controllers/' . $controllerName . '.php';

if (!file_exists($controllerFile)) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => 'Controller not found: ' . $controllerName]);
    exit;
}

require_once $controllerFile;

$controller = new $controllerName();
if (!method_exists($controller, $method)) {
    http_response_code(500);
    echo json_encode(['code' => 500, 'msg' => 'Method not found: ' . $method]);
    exit;
}

$controller->$method();