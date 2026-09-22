<?php
// admin_auth.php - 管理员统一鉴权与日志中台
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/expense_service.php';

// 管理员密码定义
if (!defined('ADMIN_PASSWORD')) {
    define('ADMIN_PASSWORD', 'admin123');
}

$admin_log_dir = dirname(__DIR__) . '/uploads';
if (!is_dir($admin_log_dir)) {
    @mkdir($admin_log_dir, 0755, true);
}
$admin_log_file = $admin_log_dir . '/admin_actions.log';

if (!function_exists('logAdminAction')) {
    function logAdminAction($action, $status, $detail, $log_file = null) {
        if ($log_file === null) {
            $log_file = dirname(__DIR__) . '/uploads/admin_actions.log';
        }
        $time = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true ? 'admin' : 'guest';
        $detail_text = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : (string)$detail;
        $detail_text = str_replace(["\r", "\n"], ' ', $detail_text);
        $line = $time . "\t" . $ip . "\t" . $user . "\t" . $action . "\t" . $status . "\t" . $detail_text . PHP_EOL;
        @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
    }
}

// 退出登录处理
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logAdminAction('logout', '成功', '管理员退出登录', $admin_log_file);
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // 处理登录请求
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            logAdminAction('login', '成功', '管理员登录成功', $admin_log_file);
            
            if (isset($_POST['action']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => '登录成功']);
                exit;
            }
            
            $redirect_url = $_SERVER['REQUEST_URI'] ?: 'index.php';
            header('Location: ' . $redirect_url);
            exit;
        } else {
            $login_error = '密码错误';
            logAdminAction('login', '失败', '管理员密码错误', $admin_log_file);
            
            if (isset($_POST['action']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => '密码错误']);
                exit;
            }
        }
    }
    
    // AJAX 请求未登录时拦截返回 JSON
    if (isset($_POST['action']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }
    
    // 渲染统一登录界面
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录 - <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : '抽奖系统' ?></title>
        <script src="../assets/css/browser@4.js"></script>
        <link rel="stylesheet" href="../assets/css/admin.css">
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96 border border-gray-200">
            <h1 class="text-2xl font-bold text-center mb-6 text-gray-800">管理员登录</h1>
            <?php if (!empty($login_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
                    <?= htmlspecialchars($login_error) ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        管理员密码
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                           id="password" name="password" type="password" placeholder="请输入管理员密码" required autofocus>
                </div>
                <div class="flex items-center justify-between">
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full transition" 
                            type="submit">
                        登录
                    </button>
                </div>
            </form>
            <div class="mt-4 text-center">
                <a href="../index.php" class="text-teal-700 hover:underline text-sm">返回抽奖首页</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$expense_csrf_token = expenseCsrfToken();
