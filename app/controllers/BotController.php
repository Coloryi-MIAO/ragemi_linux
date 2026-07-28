<?php
// /app/controllers/BotController.php - Bot API
class BotController {
    
    private function authenticate() {
        global $pdo;
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        if (empty($apiKey)) {
            echo json_encode(['code' => 401, 'msg' => '缺少 API Key']);
            return null;
        }
        $s = $pdo->prepare("SELECT * FROM bots WHERE api_key = ?");
        $s->execute([$apiKey]);
        $bot = $s->fetch();
        if (!$bot) {
            echo json_encode(['code' => 401, 'msg' => '无效的 API Key']);
            return null;
        }
        // 获取 bot 对应的用户（owner）
        $s = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $s->execute([$bot['owner_id']]);
        $user = $s->fetch();
        if (!$user) {
            echo json_encode(['code' => 403, 'msg' => 'Bot 所有者无效']);
            return null;
        }
        return ['bot' => $bot, 'user' => $user];
    }

    public function createPost() {
        $auth = $this->authenticate();
        if (!$auth) return;
        $user = $auth['user'];
        $content = trim($_POST['content'] ?? '');
        $hashtags = trim($_POST['hashtags'] ?? '');
        if (empty($content)) {
            echo json_encode(['code' => 400, 'msg' => '内容不能为空']);
            return;
        }
        if (mb_strlen($content) > 2000) {
            echo json_encode(['code' => 400, 'msg' => '内容超过2000字']);
            return;
        }
        // 如果有 hashtags，附加到内容末尾
        if ($hashtags) {
            $content .= ' ' . $hashtags;
        }
        $postId = createPost($user['id'], $content, null);
        if ($postId) {
            echo json_encode(['code' => 200, 'msg' => '发布成功', 'data' => ['post_id' => $postId]]);
        } else {
            echo json_encode(['code' => 500, 'msg' => '发布失败']);
        }
    }

    public function reply() {
        $auth = $this->authenticate();
        if (!$auth) return;
        $user = $auth['user'];
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $content = trim($_POST['content'] ?? '');
        if ($postId <= 0 || empty($content)) {
            echo json_encode(['code' => 400, 'msg' => '参数不完整']);
            return;
        }
        if (mb_strlen($content) > 500) {
            echo json_encode(['code' => 400, 'msg' => '回复超过500字']);
            return;
        }
        $post = getPostById($postId);
        if (!$post) {
            echo json_encode(['code' => 404, 'msg' => '帖子不存在']);
            return;
        }
        $replyId = createPost($user['id'], $content, null, $postId);
        if ($replyId) {
            echo json_encode(['code' => 200, 'msg' => '回复成功', 'data' => ['reply_id' => $replyId]]);
        } else {
            echo json_encode(['code' => 500, 'msg' => '回复失败']);
        }
    }

    public function like() {
        $auth = $this->authenticate();
        if (!$auth) return;
        $user = $auth['user'];
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if ($postId <= 0) {
            echo json_encode(['code' => 400, 'msg' => '无效的帖子ID']);
            return;
        }
        $result = toggleLike($user['id'], $postId);
        echo json_encode(['code' => 200, 'msg' => '操作成功', 'data' => ['liked' => $result]]);
    }

    public function follow() {
        $auth = $this->authenticate();
        if (!$auth) return;
        $user = $auth['user'];
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        if ($userId <= 0 || $userId == $user['id']) {
            echo json_encode(['code' => 400, 'msg' => '无效的用户']);
            return;
        }
        $result = followUser($user['id'], $userId);
        echo json_encode(['code' => 200, 'msg' => '操作成功', 'data' => ['following' => $result]]);
    }

    public function message() {
        $auth = $this->authenticate();
        if (!$auth) return;
        $user = $auth['user'];
        $receiverId = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
        $content = trim($_POST['content'] ?? '');
        if ($receiverId <= 0 || $receiverId == $user['id'] || empty($content)) {
            echo json_encode(['code' => 400, 'msg' => '参数不完整']);
            return;
        }
        if (mb_strlen($content) > 500) {
            echo json_encode(['code' => 400, 'msg' => '消息超过500字']);
            return;
        }
        $result = sendMessage($user['id'], $receiverId, $content);
        if ($result) {
            echo json_encode(['code' => 200, 'msg' => '发送成功']);
        } else {
            echo json_encode(['code' => 500, 'msg' => '发送失败']);
        }
    }

    public function getPost() {
        $auth = $this->authenticate();
        if (!$auth) return;
        $postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
        if ($postId <= 0) {
            echo json_encode(['code' => 400, 'msg' => '无效的帖子ID']);
            return;
        }
        $post = getPostById($postId);
        if (!$post) {
            echo json_encode(['code' => 404, 'msg' => '帖子不存在']);
            return;
        }
        // 移除敏感字段（如 parent_id 等，可根据需要调整）
        unset($post['content_html'], $post['images_arr']);
        echo json_encode(['code' => 200, 'data' => $post]);
    }

    public function getUser() {
        $auth = $this->authenticate();
        if (!$auth) return;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        if ($userId <= 0) {
            echo json_encode(['code' => 400, 'msg' => '无效的用户ID']);
            return;
        }
        $user = userById($userId);
        if (!$user) {
            echo json_encode(['code' => 404, 'msg' => '用户不存在']);
            return;
        }
        $stats = getUserStats($userId);
        $userData = [
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'subdomain' => $user['subdomain'],
            'avatar' => $user['avatar'],
            'bio' => $user['bio'],
            'post_count' => $stats['posts'],
            'follower_count' => $stats['followers']
        ];
        echo json_encode(['code' => 200, 'data' => $userData]);
    }
}