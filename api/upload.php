<?php
// api/upload.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$me = me();
if (!$me) json_out(['code' => 401, 'msg' => '请先登录']);
if (!verifyCsrf($_POST['csrf'] ?? '')) json_out(['code' => 403, 'msg' => 'CSRF 验证失败']);

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_out(['code' => 400, 'msg' => '上传失败']);
}

$result = uploadImage($_FILES['image'], IMAGE_DIR);
if (isset($result['success'])) {
    json_out(['code' => 200, 'data' => ['filename' => $result['filename'], 'url' => '/uploads/images/' . $result['filename']]]);
} else {
    json_out(['code' => 400, 'msg' => $result['error']]);
}