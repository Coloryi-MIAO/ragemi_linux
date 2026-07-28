<?php
// functions.php - 瑞格米完整业务逻辑函数
// 注意：config.php 已定义 me(), e(), renderPost(), json_out(), csrfToken(), verifyCsrf(),
// isAdmin(), isSuperAdmin(), getSubdomain(), getClientIp(), timeAgo(), getAvatarUrl(),
// formatDatetime(), truncateText(), logError(), logAccess(), verifyUserToken(),
// createUserToken(), deleteUserToken(), cleanupUserTokens()
// 本文件不再重复定义这些函数

/* ========================================
   1. 用户函数
   ======================================== */

/**
 * 根据ID获取用户信息
 */
function userById($id) {
    global $pdo;
    $s = $pdo->prepare("SELECT id, username, display_name, subdomain, avatar, header_bg, bio, role, status, email, two_factor_enabled, created_at FROM users WHERE id = ?");
    $s->execute([$id]);
    return $s->fetch();
}

/**
 * 根据子域名获取用户信息
 */
function userBySubdomain($sub) {
    global $pdo;
    $s = $pdo->prepare("SELECT id, username, display_name, subdomain, avatar, header_bg, bio, role, status, email, two_factor_enabled, created_at FROM users WHERE subdomain = ?");
    $s->execute([$sub]);
    return $s->fetch();
}

/**
 * 根据用户名获取用户信息
 */
function userByUsername($username) {
    global $pdo;
    $s = $pdo->prepare("SELECT id, username, display_name, subdomain, avatar, header_bg, bio, role, status, email, two_factor_enabled, created_at FROM users WHERE username = ?");
    $s->execute([$username]);
    return $s->fetch();
}

/**
 * 根据邮箱获取用户信息
 */
function userByEmail($email) {
    global $pdo;
    $s = $pdo->prepare("SELECT id, username, display_name, subdomain, avatar, header_bg, bio, role, status, email, two_factor_enabled, created_at FROM users WHERE email = ?");
    $s->execute([$email]);
    return $s->fetch();
}

/**
 * 获取用户统计信息
 */
function getUserStats($userId) {
    global $pdo;
    // 只统计顶层帖子（parent_id IS NULL）
    $s = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = ? AND status='normal' AND parent_id IS NULL");
    $s->execute([$userId]);
    $posts = $s->fetchColumn();
    
    $s = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE followee_id = ?");
    $s->execute([$userId]);
    $followers = $s->fetchColumn();
    
    $s = $pdo->prepare("SELECT COUNT(*) FROM follows WHERE follower_id = ?");
    $s->execute([$userId]);
    $following = $s->fetchColumn();
    
    $s = $pdo->prepare("SELECT COUNT(*) FROM likes l JOIN posts p ON l.post_id = p.id WHERE p.user_id = ? AND p.status = 'normal'");
    $s->execute([$userId]);
    $likes = $s->fetchColumn();
    
    return [
        'posts' => (int)$posts, 
        'followers' => (int)$followers, 
        'following' => (int)$following, 
        'likes' => (int)$likes
    ];
}

/**
 * 更新用户信息
 */
function updateUser($userId, $data) {
    global $pdo;
    $fields = []; 
    $params = [];
    
    if (isset($data['username'])) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) return false;
        $fields[] = "username = ?"; 
        $params[] = $data['username'];
    }
    if (isset($data['subdomain'])) {
        $fields[] = "subdomain = ?"; 
        $params[] = $data['subdomain'];
    }
    if (isset($data['display_name'])) { 
        $fields[] = "display_name = ?"; 
        $params[] = $data['display_name']; 
    }
    if (isset($data['bio'])) { 
        $fields[] = "bio = ?"; 
        $params[] = $data['bio']; 
    }
    if (isset($data['avatar'])) { 
        $fields[] = "avatar = ?"; 
        $params[] = $data['avatar']; 
    }
    if (isset($data['password_hash'])) { 
        $fields[] = "password_hash = ?"; 
        $params[] = $data['password_hash']; 
    }
    if (isset($data['email'])) { 
        $fields[] = "email = ?"; 
        $params[] = $data['email']; 
    }
    if (isset($data['header_bg'])) { 
        $fields[] = "header_bg = ?"; 
        $params[] = $data['header_bg']; 
    }
    if (isset($data['role'])) {
        $fields[] = "role = ?";
        $params[] = $data['role'];
    }
    if (isset($data['status'])) {
        $fields[] = "status = ?";
        $params[] = $data['status'];
    }
    if (isset($data['two_factor_enabled'])) {
        $fields[] = "two_factor_enabled = ?";
        $params[] = (int)$data['two_factor_enabled'];
    }
    
    if (empty($fields)) return false;
    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
    $s = $pdo->prepare($sql);
    return $s->execute($params);
}

/**
 * 创建用户（支持验证码）
 */
function createUser($username, $display_name, $email, $password, $code = null) {
    global $pdo;
    if (strlen($username) < USERNAME_MIN_LENGTH) return ['error' => '用户名至少' . USERNAME_MIN_LENGTH . '个字符'];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) return ['error' => '用户名只能包含英文字母、数字和下划线'];
    if (strlen($display_name) < 1) return ['error' => '请输入昵称'];
    if (mb_strlen($display_name) > 20) return ['error' => '昵称不能超过20个字符'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => '邮箱格式不正确'];
    if (strlen($password) < PASSWORD_MIN_LENGTH) return ['error' => '密码至少' . PASSWORD_MIN_LENGTH . '位'];
    
    // 验证验证码
    if ($code !== null && !verifyCode($email, $code)) {
        return ['error' => '验证码错误或已过期'];
    }
    
    $s = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $s->execute([$username, $email]);
    if ($s->fetch()) return ['error' => '用户名或邮箱已被使用'];
    
    $subdomain = strtolower($username);
    $s = $pdo->prepare("SELECT id FROM users WHERE subdomain = ?");
    $s->execute([$subdomain]);
    if ($s->fetch()) $subdomain = $subdomain . rand(100, 999);
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $s = $pdo->prepare("INSERT INTO users (username, display_name, email, subdomain, password_hash) VALUES (?, ?, ?, ?, ?)");
    if ($s->execute([$username, $display_name, $email, $subdomain, $hash])) {
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    }
    return ['error' => '注册失败，请稍后再试'];
}

/**
 * 获取关注状态
 */
function getFollowStatus($followerId, $followeeId) {
    global $pdo;
    if ($followerId == $followeeId) return 'self';
    $s = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?");
    $s->execute([$followerId, $followeeId]);
    return $s->fetch() ? 'following' : 'not_following';
}

/**
 * 获取推荐用户
 */
function getRecommendUsers($userId, $limit = 3) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.subdomain, u.avatar, u.bio 
        FROM users u 
        WHERE u.id != ? 
        AND u.id NOT IN (SELECT followee_id FROM follows WHERE follower_id = ?)
        AND u.status = 'active'
        ORDER BY RAND() 
        LIMIT ?
    ");
    $s->execute([$userId, $userId, $limit]);
    return $s->fetchAll();
}

/**
 * 搜索用户
 */
function searchUsers($query, $limit = 20) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT id, username, display_name, subdomain, avatar, bio, role, status 
        FROM users 
        WHERE (username LIKE ? OR display_name LIKE ? OR subdomain LIKE ?) 
        AND status = 'active'
        ORDER BY username ASC
        LIMIT ?
    ");
    $s->execute(["%$query%", "%$query%", "%$query%", $limit]);
    return $s->fetchAll();
}


/* ========================================
   2. 帖子函数（完整版）
   ======================================== */

/**
 * 获取帖子列表（只获取顶层帖子，不包含回复）
 */
function getPosts($userId = null, $page = 1, $limit = PAGE_SIZE, $currentUserId = null, $onlyFollowed = false) {
    global $pdo;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT p.*, u.username, u.display_name, u.subdomain, u.avatar,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM posts WHERE parent_id = p.id AND status='normal') as reply_count,
            (SELECT CASE WHEN EXISTS (SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) THEN 1 ELSE 0 END) as liked_by_me
            FROM posts p 
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'normal' AND p.parent_id IS NULL";
    
    $params = [$currentUserId ?: 0];
    
    if ($userId) { 
        $sql .= " AND p.user_id = ?"; 
        $params[] = $userId; 
    }
    
    if ($onlyFollowed && $currentUserId) { 
        $sql .= " AND p.user_id IN (SELECT followee_id FROM follows WHERE follower_id = ?)"; 
        $params[] = $currentUserId; 
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit; 
    $params[] = $offset;
    
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $rows = $s->fetchAll();
    
    foreach ($rows as &$row) {
        $row['content_html'] = renderPost($row['content']);
        $row['images_arr'] = isset($row['images']) && $row['images'] ? json_decode($row['images'], true) : [];
        if (!is_array($row['images_arr'])) {
            $row['images_arr'] = [];
        }
        $row['liked_by_me'] = (int)($row['liked_by_me'] ?? 0);
    }
    return $rows;
}

/**
 * 获取单篇帖子（包含 liked_by_me）
 */
function getPostById($postId, $currentUserId = null) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT p.*, u.username, u.display_name, u.subdomain, u.avatar,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM posts WHERE parent_id = p.id AND status='normal') as reply_count,
        (SELECT CASE WHEN EXISTS (SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) THEN 1 ELSE 0 END) as liked_by_me
        FROM posts p 
        JOIN users u ON p.user_id = u.id
        WHERE p.id = ? AND p.status != 'deleted'
    ");
    $s->execute([$currentUserId ?: 0, $postId]);
    $row = $s->fetch();
    
    if ($row) {
        $row['content_html'] = renderPost($row['content']);
        $row['images_arr'] = isset($row['images']) && $row['images'] ? json_decode($row['images'], true) : [];
        if (!is_array($row['images_arr'])) {
            $row['images_arr'] = [];
        }
        $row['liked_by_me'] = (int)($row['liked_by_me'] ?? 0);
    }
    return $row;
}

/**
 * 创建帖子（支持图片）
 */
function createPost($userId, $content, $images = null, $parentId = null) {
    global $pdo;
    preg_match_all('/#([\p{L}\w]+)/u', $content, $matches);
    $hashtags = implode(',', $matches[1] ?? []);
    $images_json = $images ? json_encode($images) : null;
    
    $s = $pdo->prepare("INSERT INTO posts (user_id, content, hashtags, images, parent_id) VALUES (?, ?, ?, ?, ?)");
    $s->execute([$userId, $content, $hashtags, $images_json, $parentId]);
    return $pdo->lastInsertId();
}

/**
 * 删除帖子（软删除）
 */
function deletePost($postId, $userId = null) {
    global $pdo;
    $sql = "UPDATE posts SET status = 'deleted' WHERE id = ?";
    $params = [$postId];
    if ($userId) { 
        $sql .= " AND user_id = ?"; 
        $params[] = $userId; 
    }
    $s = $pdo->prepare($sql);
    return $s->execute($params);
}

/**
 * 获取帖子回复（包含 liked_by_me 和 like_count）
 */
function getPostReplies($postId, $limit = 50, $currentUserId = null) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT p.*, u.username, u.display_name, u.subdomain, u.avatar,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT CASE WHEN EXISTS (SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) THEN 1 ELSE 0 END) as liked_by_me
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.parent_id = ? AND p.status = 'normal'
        ORDER BY p.created_at ASC
        LIMIT ?
    ");
    $s->execute([$currentUserId ?: 0, $postId, $limit]);
    $rows = $s->fetchAll();
    
    foreach ($rows as &$row) {
        $row['content_html'] = renderPost($row['content']);
        $row['images_arr'] = isset($row['images']) && $row['images'] ? json_decode($row['images'], true) : [];
        if (!is_array($row['images_arr'])) {
            $row['images_arr'] = [];
        }
        $row['liked_by_me'] = (int)($row['liked_by_me'] ?? 0);
    }
    return $rows;
}

/**
 * 获取热门回复（点赞最多的2条）
 */
function getTopReplies($postId, $limit = 2, $currentUserId = null) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT p.*, u.username, u.display_name, u.subdomain, u.avatar,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT CASE WHEN EXISTS (SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) THEN 1 ELSE 0 END) as liked_by_me
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.parent_id = ? AND p.status = 'normal'
        ORDER BY like_count DESC, p.created_at ASC
        LIMIT ?
    ");
    $s->execute([$currentUserId ?: 0, $postId, $limit]);
    $rows = $s->fetchAll();
    
    foreach ($rows as &$row) {
        $row['content_html'] = renderPost($row['content']);
        $row['images_arr'] = isset($row['images']) && $row['images'] ? json_decode($row['images'], true) : [];
        if (!is_array($row['images_arr'])) {
            $row['images_arr'] = [];
        }
        $row['liked_by_me'] = (int)($row['liked_by_me'] ?? 0);
    }
    return $rows;
}

/**
 * 获取新帖数量（只统计顶层帖子）
 */
function getNewPosts($lastPostId, $userId = null) {
    global $pdo;
    $sql = "SELECT COUNT(*) as count FROM posts WHERE id > ? AND status = 'normal' AND parent_id IS NULL";
    $params = [$lastPostId];
    if ($userId) {
        $sql .= " AND user_id IN (SELECT followee_id FROM follows WHERE follower_id = ?)";
        $params[] = $userId;
    }
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return (int)$s->fetchColumn();
}

/**
 * 获取用户帖子（包含回复）
 */
function getUserPosts($userId, $page = 1, $limit = PAGE_SIZE, $currentUserId = null) {
    global $pdo;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT p.*, u.username, u.display_name, u.subdomain, u.avatar,
            (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
            (SELECT COUNT(*) FROM posts WHERE parent_id = p.id AND status='normal') as reply_count,
            (SELECT CASE WHEN EXISTS (SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) THEN 1 ELSE 0 END) as liked_by_me
            FROM posts p 
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ? AND p.status = 'normal'
            ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
    
    $s = $pdo->prepare($sql);
    $s->execute([$currentUserId ?: 0, $userId, $limit, $offset]);
    $rows = $s->fetchAll();
    
    foreach ($rows as &$row) {
        $row['content_html'] = renderPost($row['content']);
        $row['images_arr'] = isset($row['images']) && $row['images'] ? json_decode($row['images'], true) : [];
        if (!is_array($row['images_arr'])) {
            $row['images_arr'] = [];
        }
        $row['liked_by_me'] = (int)($row['liked_by_me'] ?? 0);
    }
    return $rows;
}


/* ========================================
   3. 互动函数
   ======================================== */

/**
 * 切换点赞状态
 */
function toggleLike($userId, $postId) {
    global $pdo;
    
    // 检查帖子是否存在
    $s = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = ? AND status != 'deleted'");
    $s->execute([$postId]);
    $post = $s->fetch();
    if (!$post) {
        return false;
    }
    
    // 检查是否已点赞
    $s = $pdo->prepare("SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?");
    $s->execute([$userId, $postId]);
    
    if ($s->fetch()) {
        // 取消点赞
        $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?")->execute([$userId, $postId]);
        $pdo->prepare("UPDATE posts SET like_count = like_count - 1 WHERE id = ?")->execute([$postId]);
        return false;
    } else {
        // 点赞
        $pdo->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)")->execute([$userId, $postId]);
        $pdo->prepare("UPDATE posts SET like_count = like_count + 1 WHERE id = ?")->execute([$postId]);
        // 通知作者
        if ($post['user_id'] != $userId) {
            createNotification($postId, $userId, 'like');
        }
        return true;
    }
}

/**
 * 检查用户是否已点赞
 */
function hasLiked($userId, $postId) {
    global $pdo;
    $s = $pdo->prepare("SELECT 1 FROM likes WHERE user_id = ? AND post_id = ?");
    $s->execute([$userId, $postId]);
    return (bool)$s->fetch();
}

/**
 * 切换关注状态
 */
function followUser($followerId, $followeeId) {
    global $pdo;
    if ($followerId == $followeeId) return false;
    
    $s = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?");
    $s->execute([$followerId, $followeeId]);
    
    if ($s->fetch()) {
        $pdo->prepare("DELETE FROM follows WHERE follower_id = ? AND followee_id = ?")->execute([$followerId, $followeeId]);
        return false;
    } else {
        $pdo->prepare("INSERT INTO follows (follower_id, followee_id) VALUES (?, ?)")->execute([$followerId, $followeeId]);
        createNotification(null, $followerId, 'follow', $followeeId);
        return true;
    }
}


/* ========================================
   4. 私信函数
   ======================================== */

function sendMessage($senderId, $receiverId, $content) {
    global $pdo;
    if ($senderId == $receiverId) return false;
    if (mb_strlen($content) > 500) return false;
    
    $s = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    if ($s->execute([$senderId, $receiverId, $content])) {
        createNotification(null, $senderId, 'message', $receiverId);
        return $pdo->lastInsertId();
    }
    return false;
}

function getMessages($userId, $withUserId = null, $limit = 50) {
    global $pdo;
    if ($withUserId) {
        $s = $pdo->prepare("
            SELECT m.*, 
                   u1.username as sender_name, u1.display_name as sender_display, u1.subdomain as sender_subdomain,
                   u2.username as receiver_name, u2.display_name as receiver_display, u2.subdomain as receiver_subdomain
            FROM messages m
            JOIN users u1 ON m.sender_id = u1.id
            JOIN users u2 ON m.receiver_id = u2.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $s->execute([$userId, $withUserId, $withUserId, $userId, $limit]);
        $messages = $s->fetchAll();
        $pdo->prepare("UPDATE messages SET `read` = 1 WHERE receiver_id = ? AND sender_id = ?")->execute([$userId, $withUserId]);
        return $messages;
    } else {
        $s = $pdo->prepare("
            SELECT m.*, 
                   u.username as sender_name, u.display_name as sender_display, u.subdomain as sender_subdomain
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ?
            ORDER BY m.created_at DESC
            LIMIT ?
        ");
        $s->execute([$userId, $limit]);
        return $s->fetchAll();
    }
}

function getUnreadMessageCount($userId) {
    global $pdo;
    $s = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND `read` = 0");
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}


/* ========================================
   5. 通知函数
   ======================================== */

function createNotification($postId, $fromUserId, $type, $targetUserId = null) {
    global $pdo;
    if ($type === 'like' || $type === 'reply') {
        $s = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
        $s->execute([$postId]);
        $post = $s->fetch();
        if (!$post) return false;
        $targetUserId = $post['user_id'];
    } elseif ($type === 'follow' || $type === 'message') {
        if (!$targetUserId) return false;
    }
    if ($targetUserId == $fromUserId) return false;
    
    $s = $pdo->prepare("INSERT INTO notifications (user_id, type, from_user_id, post_id) VALUES (?, ?, ?, ?)");
    return $s->execute([$targetUserId, $type, $fromUserId, $postId]);
}

function getNotifications($userId, $limit = 50, $onlyUnread = false) {
    global $pdo;
    $sql = "
        SELECT n.*, 
               u.username as from_username, 
               u.display_name as from_display,
               u.subdomain as from_subdomain,
               u.avatar as from_avatar
        FROM notifications n
        JOIN users u ON n.from_user_id = u.id
        WHERE n.user_id = ?
    ";
    $params = [$userId];
    if ($onlyUnread) $sql .= " AND n.read = 0";
    $sql .= " ORDER BY n.created_at DESC LIMIT ?";
    $params[] = $limit;
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function getUnreadNotificationCount($userId) {
    global $pdo;
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND `read` = 0");
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}

function markNotificationsRead($userId) {
    global $pdo;
    $s = $pdo->prepare("UPDATE notifications SET `read` = 1 WHERE user_id = ? AND `read` = 0");
    return $s->execute([$userId]);
}


/* ========================================
   6. 管理函数
   ======================================== */

function setUserRole($adminId, $targetId, $role) {
    global $pdo;
    if (!in_array($role, ['user', 'moderator', 'admin'])) return false;
    if ($targetId == 1) return false;
    $s = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
    return $s->execute([$role, $targetId]);
}

function banUser($adminId, $targetId) {
    global $pdo;
    if ($targetId == 1) return false;
    $s = $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
    return $s->execute([$targetId]);
}

function unbanUser($adminId, $targetId) {
    global $pdo;
    $s = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    return $s->execute([$targetId]);
}

function moderatePost($adminId, $postId, $action) {
    global $pdo;
    $allowed = ['hide', 'unhide', 'delete'];
    if (!in_array($action, $allowed)) return false;
    
    switch ($action) {
        case 'hide': $status = 'hidden'; break;
        case 'unhide': $status = 'normal'; break;
        case 'delete': $status = 'deleted'; break;
        default: return false;
    }
    $s = $pdo->prepare("UPDATE posts SET status = ? WHERE id = ?");
    return $s->execute([$status, $postId]);
}

function getReportedPosts($limit = 20) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT p.*, u.username, u.display_name, u.subdomain
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'hidden' OR p.status = 'deleted'
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    $s->execute([$limit]);
    return $s->fetchAll();
}


/* ========================================
   7. 统计函数
   ======================================== */

function getSiteStats() {
    global $pdo;
    $stats = [];
    $s = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
    $stats['users'] = (int)$s->fetchColumn();
    $s = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'normal' AND parent_id IS NULL");
    $stats['posts'] = (int)$s->fetchColumn();
    $s = $pdo->query("SELECT COUNT(*) FROM likes");
    $stats['likes'] = (int)$s->fetchColumn();
    return $stats;
}


/* ========================================
   8. 上传函数
   ======================================== */

function uploadImage($file, $targetDir, $maxSize = UPLOAD_MAX_SIZE) {
    // 检查错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => '文件超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止'
        ];
        return ['error' => $errors[$file['error']] ?? '上传失败'];
    }
    
    // 检查大小
    if ($file['size'] > $maxSize) {
        return ['error' => '文件过大（最大 ' . ($maxSize / 1024 / 1024) . 'MB）'];
    }
    
    // 检查是否为有效图片（真实MIME校验）
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($realMime, ALLOWED_IMAGE_TYPES)) {
        return ['error' => '不支持的文件类型：' . $realMime];
    }
    
    // 检查扩展名是否匹配真实MIME
    $extMap = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp']
    ];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $extMap[$realMime] ?? [])) {
        return ['error' => '文件扩展名与内容不匹配'];
    }
    
    // 确保目录存在
    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            return ['error' => '无法创建上传目录，请检查权限'];
        }
    }
    
    // 生成唯一文件名（防路径遍历）
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $path = $targetDir . $filename;
    
    // 移动文件
    if (!@move_uploaded_file($file['tmp_name'], $path)) {
        return ['error' => '保存失败，请检查目录权限'];
    }
    
    return ['success' => true, 'filename' => $filename, 'path' => $path];
}


/* ========================================
   9. 验证码函数
   ======================================== */

function generateVerificationCode($length = VERIFICATION_CODE_LENGTH) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function storeVerificationCode($email, $code) {
    global $pdo;
    $s = $pdo->prepare("DELETE FROM email_verifications WHERE email = ? AND used = FALSE");
    $s->execute([$email]);
    $expires = date('Y-m-d H:i:s', time() + VERIFICATION_CODE_EXPIRE);
    $s = $pdo->prepare("INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)");
    return $s->execute([$email, $code, $expires]);
}

function verifyCode($email, $code) {
    global $pdo;
    $s = $pdo->prepare("SELECT id FROM email_verifications WHERE email = ? AND code = ? AND used = FALSE AND expires_at > NOW() LIMIT 1");
    $s->execute([$email, $code]);
    $row = $s->fetch();
    if (!$row) return false;
    $s = $pdo->prepare("UPDATE email_verifications SET used = TRUE WHERE id = ?");
    $s->execute([$row['id']]);
    return true;
}


/* ========================================
   10. 邮件发送函数（调用 mailer.php）
   ======================================== */

function sendVerificationCode($to_email, $code) {
    require_once __DIR__ . '/mailer.php';
    return send_email_code($to_email, $code, 'verify');
}

function send2FACode($to_email, $code) {
    require_once __DIR__ . '/mailer.php';
    return send_email_code($to_email, $code, '2fa');
}

function sendEmailChangeCode($to_email, $code) {
    require_once __DIR__ . '/mailer.php';
    return send_email_code($to_email, $code, 'email_change');
}


/* ========================================
   11. 搜索函数
   ======================================== */

function searchPosts($query, $page = 1, $limit = 20, $currentUserId = null) {
    global $pdo;
    $offset = ($page - 1) * $limit;
    
    $s = $pdo->prepare("
        SELECT p.*, u.username, u.display_name, u.subdomain, u.avatar,
        (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
        (SELECT COUNT(*) FROM posts WHERE parent_id = p.id AND status='normal') as reply_count,
        (SELECT CASE WHEN EXISTS (SELECT 1 FROM likes WHERE post_id = p.id AND user_id = ?) THEN 1 ELSE 0 END) as liked_by_me
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.content LIKE ? AND p.status = 'normal' AND p.parent_id IS NULL
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $s->execute([$currentUserId ?: 0, "%$query%", $limit, $offset]);
    $rows = $s->fetchAll();
    
    foreach ($rows as &$row) {
        $row['content_html'] = renderPost($row['content']);
        $row['images_arr'] = isset($row['images']) && $row['images'] ? json_decode($row['images'], true) : [];
        if (!is_array($row['images_arr'])) {
            $row['images_arr'] = [];
        }
        $row['liked_by_me'] = (int)($row['liked_by_me'] ?? 0);
    }
    return $rows;
}


/* ========================================
   12. 推荐函数
   ======================================== */

function getPersonalizedRecommendations($userId, $limit = 5) {
    global $pdo;
    
    // 1. 获取用户关注的人的关注列表（二度关系）
    $s = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.display_name, u.subdomain, u.avatar, u.bio,
        (SELECT COUNT(*) FROM follows WHERE followee_id = u.id) as follower_count,
        (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND status='normal') as post_count
        FROM users u
        WHERE u.id != ?
        AND u.id NOT IN (SELECT followee_id FROM follows WHERE follower_id = ?)
        AND u.status = 'active'
        AND u.id IN (
            SELECT followee_id FROM follows 
            WHERE follower_id IN (
                SELECT followee_id FROM follows WHERE follower_id = ?
            )
        )
        ORDER BY follower_count DESC, post_count DESC
        LIMIT ?
    ");
    $s->execute([$userId, $userId, $userId, $limit]);
    $results = $s->fetchAll();
    
    if (count($results) >= $limit) {
        return $results;
    }
    
    // 2. 如果不够，补充热门用户
    $existingIds = array_column($results, 'id');
    $placeholders = !empty($existingIds) ? 'AND u.id NOT IN (' . implode(',', $existingIds) . ')' : '';
    
    $s = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.subdomain, u.avatar, u.bio,
        (SELECT COUNT(*) FROM follows WHERE followee_id = u.id) as follower_count,
        (SELECT COUNT(*) FROM posts WHERE user_id = u.id AND status='normal') as post_count
        FROM users u
        WHERE u.id != ?
        AND u.id NOT IN (SELECT followee_id FROM follows WHERE follower_id = ?)
        AND u.status = 'active'
        $placeholders
        ORDER BY follower_count DESC, post_count DESC
        LIMIT ?
    ");
    $remaining = $limit - count($results);
    $s->execute([$userId, $userId, $remaining]);
    $more = $s->fetchAll();
    
    return array_merge($results, $more);
}

function getTrendingHashtags($limit = 10) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT hashtags, COUNT(*) as count 
        FROM posts 
        WHERE hashtags != '' AND status = 'normal' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY hashtags
        ORDER BY count DESC
        LIMIT ?
    ");
    $s->execute([$limit * 2]);
    $results = $s->fetchAll();
    
    $tags = [];
    foreach ($results as $row) {
        $parts = explode(',', $row['hashtags']);
        foreach ($parts as $tag) {
            if (trim($tag)) {
                $tags[$tag] = ($tags[$tag] ?? 0) + $row['count'];
            }
        }
    }
    arsort($tags);
    return array_slice(array_keys($tags), 0, $limit);
}


/* ========================================
   13. 专用工具函数
   ======================================== */

/**
 * 提取 Hashtag
 */
function extractHashtags($content) {
    preg_match_all('/#([\p{L}\w]+)/u', $content, $matches);
    return $matches[1] ?? [];
}

/**
 * 提取 @提及
 */
function extractMentions($content) {
    preg_match_all('/@(\w+)/', $content, $matches);
    return $matches[1] ?? [];
}

/**
 * 获取帖子预览
 */
function getPostPreview($content, $length = 100) {
    $text = strip_tags($content);
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

/**
 * 检查用户是否已关注
 */
function isFollowing($followerId, $followeeId) {
    global $pdo;
    $s = $pdo->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?");
    $s->execute([$followerId, $followeeId]);
    return (bool)$s->fetch();
}

/**
 * 获取用户粉丝列表
 */
function getFollowers($userId, $limit = 20) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.subdomain, u.avatar
        FROM follows f
        JOIN users u ON f.follower_id = u.id
        WHERE f.followee_id = ?
        ORDER BY f.created_at DESC
        LIMIT ?
    ");
    $s->execute([$userId, $limit]);
    return $s->fetchAll();
}

/**
 * 获取用户关注列表
 */
function getFollowing($userId, $limit = 20) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.subdomain, u.avatar
        FROM follows f
        JOIN users u ON f.followee_id = u.id
        WHERE f.follower_id = ?
        ORDER BY f.created_at DESC
        LIMIT ?
    ");
    $s->execute([$userId, $limit]);
    return $s->fetchAll();
}

/**
 * 获取帖子图片（返回数组）
 */
function getPostImages($postId) {
    global $pdo;
    $s = $pdo->prepare("SELECT images FROM posts WHERE id = ?");
    $s->execute([$postId]);
    $row = $s->fetch();
    if (!$row || empty($row['images'])) return [];
    return json_decode($row['images'], true) ?: [];
}

/**
 * 检查帖子是否被当前用户点赞
 */
function isPostLiked($postId, $userId) {
    global $pdo;
    $s = $pdo->prepare("SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?");
    $s->execute([$postId, $userId]);
    return (bool)$s->fetch();
}

/**
 * 获取点赞该帖子的用户列表
 */
function getPostLikeUsers($postId, $limit = 12) {
    global $pdo;
    $s = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.subdomain, u.avatar
        FROM likes l
        JOIN users u ON l.user_id = u.id
        WHERE l.post_id = ?
        ORDER BY l.created_at DESC
        LIMIT ?
    ");
    $s->execute([$postId, $limit]);
    return $s->fetchAll();
}


/* ========================================
   14. 2FA 相关函数
   ======================================== */

/**
 * 检查用户是否开启 2FA
 */
function isTwoFactorEnabled($userId) {
    global $pdo;
    $s = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $s->execute([$userId]);
    $row = $s->fetch();
    return $row ? (bool)$row['two_factor_enabled'] : false;
}

/**
 * 开启/关闭 2FA
 */
function toggleTwoFactor($userId, $enabled) {
    global $pdo;
    $s = $pdo->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
    return $s->execute([$enabled ? 1 : 0, $userId]);
}