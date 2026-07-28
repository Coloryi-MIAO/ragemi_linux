<?php
// api/message.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$me = me();
if (!$me) json_out(['code' => 401, 'msg' => '请先登录']);

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $with = $_GET['with'] ?? null;
    $messages = $with ? getMessages($me['id'], userByUsername($with)['id'] ?? 0) : getMessages($me['id']);
    json_out(['code' => 200, 'data' => $messages]);
} elseif ($action === 'send') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) json_out(['code' => 403, 'msg' => 'CSRF 验证失败']);
    $receiver = trim($_POST['receiver'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!$receiver || !$content) json_out(['code' => 400, 'msg' => '请填写完整信息']);
    $target = userByUsername($receiver) ?: userBySubdomain($receiver);
    if (!$target) json_out(['code' => 404, 'msg' => '用户不存在']);
    if ($target['id'] == $me['id']) json_out(['code' => 400, 'msg' => '不能给自己发消息']);
    if (sendMessage($me['id'], $target['id'], $content)) {
        json_out(['code' => 200, 'msg' => '发送成功']);
    } else {
        json_out(['code' => 500, 'msg' => '发送失败']);
    }
} else {
    json_out(['code' => 404, 'msg' => '接口不存在']);
}