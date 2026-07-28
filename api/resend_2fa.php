<?php
// /api/resend_2fa.php - 重发2FA
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['2fa_user_id'])) { echo json_encode(['code' => 401, 'msg' => '未授权']); exit; }
$userId = $_SESSION['2fa_user_id'];
$s = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$s->execute([$userId]);
$user = $s->fetch();
if (!$user) { echo json_encode(['code' => 400, 'msg' => '用户不存在']); exit; }
$code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
storeVerificationCode($user['email'], $code);
if (sendVerificationCode($user['email'], $code)) { echo json_encode(['code' => 200, 'msg' => '验证码已发送']); }
else { echo json_encode(['code' => 500, 'msg' => '发送失败']); }