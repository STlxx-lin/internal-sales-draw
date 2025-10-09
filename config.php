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
?>