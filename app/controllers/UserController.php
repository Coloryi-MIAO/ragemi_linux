<?php
class UserController {
    public function me() {
        $me = me();
        if ($me) { echo json_encode(['code' => 200, 'data' => $me]); }
        else { echo json_encode(['code' => 401, 'msg' => '未登录']); }
    }
    public function logout() {
        session_destroy();
        if (isset($_COOKIE['ragemi-token'])) { setcookie('ragemi-token', '', time() - 3600, '/', COOKIE_DOMAIN, false, true); }
        echo json_encode(['code' => 200, 'msg' => '已退出']);
    }
    public function sendVerification() {
        $email = $_POST['email'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['code' => 400, 'msg' => '邮箱格式不正确']); return; }
        $code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
        storeVerificationCode($email, $code);
        if (sendVerificationCode($email, $code)) { echo json_encode(['code' => 200, 'msg' => '验证码已发送']); }
        else { echo json_encode(['code' => 500, 'msg' => '发送失败']); }
    }
    public function resend2FA() {
        if (!isset($_SESSION['2fa_user_id'])) { echo json_encode(['code' => 401, 'msg' => '未授权']); return; }
        $userId = $_SESSION['2fa_user_id'];
        global $pdo;
        $s = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $s->execute([$userId]);
        $user = $s->fetch();
        if (!$user) { echo json_encode(['code' => 400, 'msg' => '用户不存在']); return; }
        $code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
        storeVerificationCode($user['email'], $code);
        if (sendVerificationCode($user['email'], $code)) { echo json_encode(['code' => 200, 'msg' => '验证码已发送']); }
        else { echo json_encode(['code' => 500, 'msg' => '发送失败']); }
    }
}
