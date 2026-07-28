<?php
// api/admin.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$me = me();
if (!$me || !isAdmin($me)) json_out(['code' => 403, 'msg' => '权限不足']);
if (!verifyCsrf($_POST['csrf'] ?? '')) json_out(['code' => 403, 'msg' => 'CSRF 验证失败']);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'set_role':
        $userId = intval($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if (setUserRole($me['id'], $userId, $role)) {
            json_out(['code' => 200, 'msg' => '角色已更新']);
        } else {
            json_out(['code' => 500, 'msg' => '更新失败']);
        }
        break;
    case 'ban':
        $userId = intval($_POST['user_id'] ?? 0);
        if (banUser($me['id'], $userId)) {
            json_out(['code' => 200, 'msg' => '用户已封禁']);
        } else {
            json_out(['code' => 500, 'msg' => '操作失败']);
        }
        break;
    case 'unban':
        $userId = intval($_POST['user_id'] ?? 0);
        if (unbanUser($me['id'], $userId)) {
            json_out(['code' => 200, 'msg' => '用户已解封']);
        } else {
            json_out(['code' => 500, 'msg' => '操作失败']);
        }
        break;
    case 'moderate':
        $postId = intval($_POST['post_id'] ?? 0);
        $modAction = $_POST['mod_action'] ?? '';
        if (moderatePost($me['id'], $postId, $modAction)) {
            json_out(['code' => 200, 'msg' => '操作成功']);
        } else {
            json_out(['code' => 500, 'msg' => '操作失败']);
        }
        break;
    default:
        json_out(['code' => 404, 'msg' => '接口不存在']);
}