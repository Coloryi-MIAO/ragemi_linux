<?php
class PostController {
    public function create() {
        global $pdo;
        $me = me();
        if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); return; }
        if (!verifyCsrf($_POST['csrf'] ?? '')) { echo json_encode(['code' => 403, 'msg' => '非法请求']); return; }
        $content = trim($_POST['content'] ?? '');
        if (empty($content) && empty($_FILES['images']['name'][0])) { echo json_encode(['code' => 400, 'msg' => '请输入内容或添加图片']); return; }
        if (mb_strlen($content) > 2000) { echo json_encode(['code' => 400, 'msg' => '内容不能超过2000字']); return; }
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
    }
    public function delete() {
        $me = me();
        if (!$me) { echo json_encode(['code' => 401, 'msg' => '请先登录']); return; }
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if ($postId <= 0) { echo json_encode(['code' => 400, 'msg' => '无效的帖子ID']); return; }
        $result = deletePost($postId, $me['id']);
        echo json_encode(['code' => $result ? 200 : 500, 'msg' => $result ? '已撤回' : '撤回失败']);
    }
    public function timeline() {
        global $pdo;
        $me = me();
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $posts = getPosts(null, $page, PAGE_SIZE, $me['id'] ?? null);
        $stmt = $pdo->query("SELECT COUNT(*) FROM posts WHERE status='normal' AND parent_id IS NULL");
        $total = $stmt->fetchColumn();
        $hasMore = ($page * PAGE_SIZE) < $total;
        foreach ($posts as &$post) { $post['top_replies'] = getTopReplies($post['id'], 2, $me['id'] ?? null); }
        echo json_encode(['code' => 200, 'data' => ['posts' => $posts, 'has_more' => $hasMore, 'page' => $page]]);
    }
}
