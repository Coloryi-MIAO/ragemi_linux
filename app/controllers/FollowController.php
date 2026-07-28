<?php
class FollowController {
    public function toggle() {
        $me = me();
        if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); return; }
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        if ($userId <= 0 || $userId == $me['id']) { echo json_encode(['code' => 400, 'msg' => '无效的用户']); return; }
        $result = followUser($me['id'], $userId);
        echo json_encode(['code' => 200, 'msg' => '操作成功', 'data' => ['following' => $result]]);
    }
}
