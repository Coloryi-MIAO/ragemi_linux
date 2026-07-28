<?php
// /app/controllers/AdminController.php
class AdminController {
    
    // 获取系统统计
    public function stats() {
        $me = me();
        if (!$me || !isAdmin($me)) {
            echo json_encode(['code' => 403, 'msg' => '权限不足']);
            return;
        }
        $stats = getSiteStats();
        echo json_encode(['code' => 200, 'data' => $stats]);
    }

    // 获取用户列表 (分页)
    public function users() {
        $me = me();
        if (!$me || !isAdmin($me)) {
            echo json_encode(['code' => 403, 'msg' => '权限不足']);
            return;
        }
        global $pdo;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        
        $sql = "SELECT id, username, display_name, subdomain, avatar, role, status, email, created_at FROM users WHERE 1=1";
        $params = [];
        if ($search) {
            $sql .= " AND (username LIKE ? OR display_name LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $users = $s->fetchAll();
        
        // 总数
        $countSql = "SELECT COUNT(*) FROM users WHERE 1=1";
        $countParams = [];
        if ($search) {
            $countSql .= " AND (username LIKE ? OR display_name LIKE ? OR email LIKE ?)";
            $countParams[] = "%$search%";
            $countParams[] = "%$search%";
            $countParams[] = "%$search%";
        }
        $s = $pdo->prepare($countSql);
        $s->execute($countParams);
        $total = $s->fetchColumn();
        
        echo json_encode([
            'code' => 200,
            'data' => [
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }

    // 获取帖子列表 (分页)
    public function posts() {
        $me = me();
        if (!$me || !isAdmin($me)) {
            echo json_encode(['code' => 403, 'msg' => '权限不足']);
            return;
        }
        global $pdo;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        $status = trim($_GET['status'] ?? '');
        
        $sql = "SELECT p.*, u.username, u.display_name, u.subdomain FROM posts p JOIN users u ON p.user_id = u.id WHERE 1=1";
        $params = [];
        if ($search) {
            $sql .= " AND p.content LIKE ?";
            $params[] = "%$search%";
        }
        if ($status) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY p.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $posts = $s->fetchAll();
        
        // 总数
        $countSql = "SELECT COUNT(*) FROM posts p WHERE 1=1";
        $countParams = [];
        if ($search) {
            $countSql .= " AND p.content LIKE ?";
            $countParams[] = "%$search%";
        }
        if ($status) {
            $countSql .= " AND p.status = ?";
            $countParams[] = $status;
        }
        $s = $pdo->prepare($countSql);
        $s->execute($countParams);
        $total = $s->fetchColumn();
        
        echo json_encode([
            'code' => 200,
            'data' => [
                'posts' => $posts,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }

    // 操作帖子 (隐藏/恢复/删除)
    public function moderatePost() {
        $me = me();
        if (!$me || !isAdmin($me)) {
            echo json_encode(['code' => 403, 'msg' => '权限不足']);
            return;
        }
        $postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        $action = $_POST['action'] ?? '';
        if ($postId <= 0 || !in_array($action, ['hide', 'unhide', 'delete'])) {
            echo json_encode(['code' => 400, 'msg' => '参数无效']);
            return;
        }
        $result = moderatePost($me['id'], $postId, $action);
        echo json_encode(['code' => $result ? 200 : 500, 'msg' => $result ? '操作成功' : '操作失败']);
    }

    // 操作用户 (封禁/解封/设置角色)
    public function moderateUser() {
        $me = me();
        if (!$me || !isAdmin($me)) {
            echo json_encode(['code' => 403, 'msg' => '权限不足']);
            return;
        }
        $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $action = $_POST['action'] ?? '';
        if ($userId <= 0 || $userId == 1) {
            echo json_encode(['code' => 400, 'msg' => '无效的用户']);
            return;
        }
        $result = false;
        switch ($action) {
            case 'ban': $result = banUser($me['id'], $userId); break;
            case 'unban': $result = unbanUser($me['id'], $userId); break;
            case 'set_admin': $result = setUserRole($me['id'], $userId, 'admin'); break;
            case 'set_moderator': $result = setUserRole($me['id'], $userId, 'moderator'); break;
            case 'set_user': $result = setUserRole($me['id'], $userId, 'user'); break;
            default: echo json_encode(['code' => 400, 'msg' => '未知操作']); return;
        }
        echo json_encode(['code' => $result ? 200 : 500, 'msg' => $result ? '操作成功' : '操作失败']);
    }
}