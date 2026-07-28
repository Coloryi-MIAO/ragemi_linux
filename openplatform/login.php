<?php
// openplatform/login.php - 开发者登录（跳转主站）
require_once __DIR__ . '/../config.php';
header('Location: /login?redirect=' . urlencode('/openplatform'));
exit;