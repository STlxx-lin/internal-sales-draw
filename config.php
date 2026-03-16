<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'asd669076');
//define('DB_PASS', '1SmiTWW91kDbXSw8');
define('DB_NAME', 'lottery_system');

// 系统配置
define('SITE_NAME', '抽奖系统');
define('UPLOAD_PATH', 'uploads/');

// 数据库连接
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 获取用户IP
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// 检查用户是否存在
function checkUser($pdo, $ip) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE ip_address = ?");
    $stmt->execute([$ip]);
    return $stmt->fetch();
}

// 获取用户剩余抽奖次数
function getUserRemainingTimes($pdo, $user_id, $project_id) {
    $stmt = $pdo->prepare("SELECT remaining_times FROM user_project_times WHERE user_id = ? AND project_id = ?");
    $stmt->execute([$user_id, $project_id]);
    $result = $stmt->fetch();
    return $result ? $result['remaining_times'] : 0;
}

function getUserLogFile() {
    $log_dir = __DIR__ . '/' . trim(UPLOAD_PATH, '/');
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    return $log_dir . '/user_actions.log';
}

function logUserAction($action, $status, $detail) {
    $log_file = getUserLogFile();
    $time = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $detail_text = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : (string)$detail;
    $detail_text = str_replace(["\r", "\n"], ' ', $detail_text);
    $line = $time . "\t" . $ip . "\t" . $action . "\t" . $status . "\t" . $detail_text . PHP_EOL;
    @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}
?>
