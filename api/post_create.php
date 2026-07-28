<?php
// /api/post_create.php - 发帖
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
header('Content-Type: application/json; charset=utf-8');
$me = me();
if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); exit; }
if (!verifyCsrf($_POST['csrf'] ?? '')) { echo json_encode(['code' => 403, 'msg' => '非法请求']); exit; }
$content = trim($_POST['content'] ?? '');
if (empty($content) && empty($_FILES['images']['name'][0])) { echo json_encode(['code' => 400, 'msg' => '请输入内容或添加图片']); exit; }
if (mb_strlen($content) > 2000) { echo json_encode(['code' => 400, 'msg' => '内容不能超过2000字']); exit; }
$images = [];
if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
    if (!is_dir(IMAGE_DIR)) mkdir(IMAGE_DIR, 0755, true);
    foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowed)) continue;
            if ($_FILES['images']['size'][$i] > 2 * 1024 * 1024) continue;
            $file = ['name' => $_FILES['images']['name'][$i], 'tmp_name' => $tmp, 'size' => $_FILES['images']['size'][$i], 'error' => $_FILES['images']['error'][$i]];
            $result = uploadImage($file, IMAGE_DIR);
            if (isset($result['success'])) $images[] = $result['filename'];
        }
    }
}
$postId = createPost($me['id'], $content, !empty($images) ? $images : null);
if ($postId) { echo json_encode(['code' => 200, 'msg' => '发布成功', 'data' => ['post_id' => $postId]]); }
else { echo json_encode(['code' => 500, 'msg' => '发布失败']); }