<?php
class NotificationController {
    public function list() {
        $me = me();
        if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); return; }
        $notifications = getNotifications($me['id'], 50);
        echo json_encode(['code' => 200, 'data' => ['list' => $notifications]]);
    }
    public function readAll() {
        $me = me();
        if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); return; }
        markNotificationsRead($me['id']);
        echo json_encode(['code' => 200, 'msg' => '已全部标记已读']);
    }
}
