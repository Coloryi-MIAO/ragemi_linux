<?php
// /api/send_verification.php - 发送验证码
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$email = $_POST['email'] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['code' => 400, 'msg' => '邮箱格式不正确']); exit; }
$code = generateVerificationCode(VERIFICATION_CODE_LENGTH);
storeVerificationCode($email, $code);
if (sendVerificationCode($email, $code)) { echo json_encode(['code' => 200, 'msg' => '验证码已发送']); }
else { echo json_encode(['code' => 500, 'msg' => '发送失败']); }