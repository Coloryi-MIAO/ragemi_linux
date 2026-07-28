<?php
// config.php - 瑞格米核心配置
// 数据库：rgmiblog / pYDZn3Cm5njWaye5

/* ========== 错误报告（开发环境开启，生产环境关闭） ========== */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ========== 会话安全配置 ========== */
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', 1);
ini_set('session.gc_maxlifetime', 7200);
ini_set('session.cookie_lifetime', 7200);

/* ========== 时区设置 ========== */
date_default_timezone_set('Asia/Shanghai');

/* ========== 会话启动 ========== */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '.ragemi.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/* ========== 数据库配置 ========== */
define('DB_HOST', 'localhost');
define('DB_NAME', 'rgmiblog');
define('DB_USER', 'rgmiblog');
define('DB_PASS', 'pYDZn3Cm5njWaye5');

/* ========== 站点配置 ========== */
define('SITE_NAME', '瑞格米-二次元帖子分享');
define('SITE_URL', 'https://www.ragemi.com');
define('SITE_TITLE', '瑞格米-二次元帖子分享');
define('ASSET_URL', SITE_URL . '/assets');
define('THEME_COLOR', '#7A5C2D');
define('COOKIE_DOMAIN', '.ragemi.com');

/* ========== 分页配置 ========== */
define('PAGE_SIZE', 20);
define('FOLD_AFTER', 10);
define('POST_MAX_LENGTH', 2000);

/* ========== 上传配置 ========== */
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('IMAGE_DIR', __DIR__ . '/uploads/images/');
define('AVATAR_DIR', __DIR__ . '/uploads/avatars/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

/* ========== 安全配置 ========== */
define('USERNAME_MIN_LENGTH', 2);
define('PASSWORD_MIN_LENGTH', 6);
define('VERIFICATION_CODE_LENGTH', 6);
define('VERIFICATION_CODE_EXPIRE', 600);
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_EXPIRE', 604800);
define('TOKEN_EXPIRE', 604800);
define('BIO_MAX_LENGTH', 200);
define('JWT_SECRET', 'ragemi_jwt_secret_2026_secure_key');

/* ========== OAuth 2.0 配置 ========== */
define('OAUTH_TOKEN_EXPIRE', 3600);
define('OAUTH_REFRESH_EXPIRE', 86400 * 30);

/* ========== Google OAuth 2.0 配置 ========== */
define('GOOGLE_CLIENT_ID', '656996971471-k9o8qo4faddn6are54tkb1uh9e2sfk4n.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'https://ragemi.com/oauth/callback');

/* ========== SEO配置 ========== */
define('SEO_DESCRIPTION', '瑞格米-二次元同好分享社区，动漫、游戏、绘画、COS日常');
define('SEO_KEYWORDS', '二次元,动漫,游戏,绘画,COS,分享社区,瑞格米');
define('SEO_OG_IMAGE', SITE_URL . '/s/og-image.png');

/* ========== PDO 连接 ========== */
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('[Ragemi] DB Error: ' . $e->getMessage());
    http_response_code(500);
    die('数据库连接失败，请稍后再试');
}

/* ============================================================
   全局安全函数
   ============================================================ */

/**
 * 设置安全响应头
 */
function setSecurityHeaders() {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

/**
 * 生成 CSP Nonce（仅用于安全的 script 标签）
 */
function getCSPNonce() {
    if (!isset($_SESSION['csp_nonce'])) {
        $_SESSION['csp_nonce'] = base64_encode(random_bytes(16));
    }
    return $_SESSION['csp_nonce'];
}

/**
 * 设置 CSP 头（修复版：允许内联事件，允许统计脚本）
 */
function setCSPHeaders() {
    $nonce = getCSPNonce();
    // 为了解决内联 onclick 事件被阻止，我们去掉 nonce，改用 'unsafe-inline' 和 'unsafe-eval'
    // 同时允许统计脚本的域名（HTTPS 版本）
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://accounts.google.com https://apis.google.com https://freenom.me.uk; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://freenom.me.uk; frame-ancestors 'none'; base-uri 'self'; form-action 'self';");
    // 注意：为了安全，生产环境应逐步移除 'unsafe-inline'，改用事件监听器绑定。
}

/**
 * 调用安全头
 */
function initSecurityHeaders() {
    setSecurityHeaders();
    if (strpos($_SERVER['REQUEST_URI'], '/api/') === false) {
        setCSPHeaders();
    }
}

/* ============================================================
   全局函数
   ============================================================ */

/**
 * 获取当前登录用户
 */
function me() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        $token = $_COOKIE['ragemi-token'] ?? null;
        if ($token) {
            $userId = verifyUserToken($token);
            if ($userId) {
                $_SESSION['user_id'] = $userId;
            }
        }
        if (!isset($_SESSION['user_id'])) return null;
    }
    $s = $pdo->prepare("SELECT id, username, display_name, subdomain, avatar, header_bg, bio, role, status, email, google_id, two_factor_enabled, created_at FROM users WHERE id = ? AND status = 'active'");
    $s->execute([$_SESSION['user_id']]);
    $user = $s->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

/**
 * 安全转义 HTML
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * 渲染帖子内容
 */
function renderPost($content) {
    $content = e($content);
    $content = preg_replace('/#([\p{L}\w]+)/u', '<span class="ht">#$1</span>', $content);
    $content = preg_replace('/@(\w+)/', '<span class="mention">@$1</span>', $content);
    $content = nl2br($content);
    return $content;
}

/**
 * 生成 CSRF Token
 */
function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH / 2));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF Token
 */
function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 获取子域名
 */
function getSubdomain() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $parts = explode('.', $host);
    return count($parts) > 2 ? $parts[0] : 'www';
}

/**
 * 获取客户端 IP
 */
function getClientIp() {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            return trim($ips[0]);
        }
    }
    return '0.0.0.0';
}

/**
 * 时间格式化
 */
function timeAgo($datetime) {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 604800) return floor($diff / 86400) . '天前';
    if ($diff < 2592000) return floor($diff / 604800) . '周前';
    return date('Y-m-d', $ts);
}

/**
 * 获取头像 URL
 */
function getAvatarUrl($avatar) {
    if (!$avatar || $avatar === '/assets/default-avatar.png') {
        return '/assets/default-avatar.png';
    }
    if (strpos($avatar, 'http') === 0) {
        return $avatar;
    }
    return '/uploads/avatars/' . $avatar;
}

/**
 * 格式化日期时间
 */
function formatDatetime($datetime) {
    return date('Y-m-d H:i', strtotime($datetime));
}

/**
 * 截断文本
 */
function truncateText($text, $length = 100) {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

/**
 * 日志记录
 */
function logError($msg) {
    error_log('[Ragemi] ' . $msg);
}

function logAccess($msg) {}

/**
 * 检查是否管理员
 */
function isAdmin($user = null) {
    global $user;
    if ($user === null) {
        $user = me();
    }
    if (!$user) return false;
    return $user['id'] == 1 || in_array($user['role'] ?? '', ['admin', 'moderator']);
}

/**
 * 检查是否超级管理员
 */
function isSuperAdmin($user = null) {
    if ($user === null) {
        $user = me();
    }
    if (!$user) return false;
    return $user['id'] == 1 || ($user['role'] ?? '') === 'admin';
}

/**
 * JSON 输出
 */
function json_out($data = null, $code = 200, $msg = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => $code,
        'msg' => $msg,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 验证用户 Token
 */
function verifyUserToken($token) {
    global $pdo;
    $s = $pdo->prepare("SELECT user_id FROM user_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $s->execute([$token]);
    $row = $s->fetch();
    return $row ? $row['user_id'] : null;
}

/**
 * 创建用户登录 Token
 */
function createUserToken($userId, $expiresIn = TOKEN_EXPIRE) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + $expiresIn);
    $s = $pdo->prepare("INSERT INTO user_tokens (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?");
    $s->execute([$userId, $token, $expires, $token, $expires]);
    return $token;
}

/**
 * 删除用户 Token
 */
function deleteUserToken($token) {
    global $pdo;
    $s = $pdo->prepare("DELETE FROM user_tokens WHERE token = ?");
    return $s->execute([$token]);
}

/**
 * 清理过期 Token
 */
function cleanupUserTokens() {
    global $pdo;
    $s = $pdo->prepare("DELETE FROM user_tokens WHERE expires_at < NOW()");
    return $s->execute();
}

/**
 * 渲染帖子卡片（通用，供首页、用户主页等调用）
 */
function renderPostCard($post, $currentUser = null) {
    $displayName = $post['display_name'] ?: $post['username'];
    $contentHtml = $post['content_html'] ?? $post['content'] ?? '';
    $imagesArr = isset($post['images_arr']) ? $post['images_arr'] : (isset($post['images']) && $post['images'] ? json_decode($post['images'], true) : []);
    $topReplies = $post['top_replies'] ?? [];
    $likedByMe = isset($post['liked_by_me']) && (int)$post['liked_by_me'] > 0;
    $isLong = mb_strlen(strip_tags($contentHtml)) > 200;
    $postId = $post['id'];
    
    $html = '<div class="post-card" id="post-' . $postId . '" data-post-id="' . $postId . '">';
    $html .= '<div class="post-header">';
    $html .= '<img src="' . getAvatarUrl($post['avatar']) . '" class="post-avatar" onclick="location.href=\'/@' . e($post['subdomain']) . '\'" onerror="this.src=\'/assets/default-avatar.png\'">';
    $html .= '<span class="post-author" onclick="location.href=\'/@' . e($post['subdomain']) . '\'">' . e($displayName) . '</span>';
    $html .= '<span class="post-badge">#' . $postId . '</span>';
    $html .= '<span class="post-time">' . timeAgo($post['created_at']) . '</span>';
    $html .= '</div>';
    $html .= '<div class="post-content" id="post-body-' . $postId . '">';
    if ($isLong) {
        $html .= '<span class="post-content-preview">' . mb_substr($contentHtml, 0, 600) . '...</span>';
        $html .= '<span class="read-more" onclick="event.stopPropagation();expandPost(' . $postId . ')"> 查看更多 →</span>';
        $html .= '<span class="post-content-full" style="display:none;">' . $contentHtml . '</span>';
    } else {
        $html .= $contentHtml;
    }
    $html .= '</div>';
    if (!empty($imagesArr)) {
        $cls = count($imagesArr) === 1 ? 'post-images single' : 'post-images';
        $html .= '<div class="' . $cls . '">';
        foreach ($imagesArr as $img) {
            $html .= '<img src="/uploads/images/' . e($img) . '" alt="图片" loading="lazy" onclick="event.stopPropagation();openImageViewer(this.src)">';
        }
        $html .= '</div>';
    }
    $html .= '<div class="post-actions">';
    $html .= '<button class="action-btn like-btn ' . ($likedByMe ? 'liked' : '') . '" onclick="likePost(' . $postId . ', this)">';
    $html .= '<span class="material-symbols-outlined">' . ($likedByMe ? 'favorite' : 'favorite_border') . '</span>';
    $html .= ' <span class="like-count">' . $post['like_count'] . '</span>';
    $html .= '</button>';
    $html .= '<button class="action-btn comment-btn" onclick="location.href=\'/post/' . $postId . '\'">';
    $html .= '<span class="material-symbols-outlined">chat_bubble_outline</span>';
    $html .= ' <span class="comment-count">' . $post['reply_count'] . '</span>';
    $html .= '</button>';
    $html .= '<button class="action-btn share-btn" onclick="sharePost(' . $postId . ')">';
    $html .= '<span class="material-symbols-outlined">share</span><span>分享</span>';
    $html .= '</button>';
    if ($currentUser && $currentUser['id'] == $post['user_id']) {
        $timeDiff = time() - strtotime($post['created_at']);
        if ($timeDiff < 86400) {
            $html .= '<button class="action-btn recall-btn" onclick="recallPost(' . $postId . ')">';
            $html .= '<span class="material-symbols-outlined">undo</span><span>撤回</span>';
            $html .= '</button>';
        }
    }
    $html .= '</div>';
    if (!empty($topReplies)) {
        $html .= '<div class="reply-preview">';
        foreach ($topReplies as $reply) {
            $replyName = $reply['display_name'] ?: $reply['username'];
            $html .= '<div class="reply-item">';
            $html .= '<img src="' . getAvatarUrl($reply['avatar']) . '" class="reply-avatar" onerror="this.src=\'/assets/default-avatar.png\'">';
            $html .= '<span class="reply-author">' . e($replyName) . '</span>';
            $html .= '<span class="reply-content">' . e(mb_substr($reply['content'], 0, 60)) . (mb_strlen($reply['content']) > 60 ? '...' : '') . '</span>';
            $html .= '</div>';
        }
        if ($post['reply_count'] > 2) {
            $html .= '<div class="view-all-replies" onclick="location.href=\'/post/' . $postId . '\'">查看全部 ' . $post['reply_count'] . ' 条回复 →</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
?>