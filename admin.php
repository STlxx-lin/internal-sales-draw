<?php
session_start();
require_once 'config.php';

// 管理员密码（实际使用时应该存储在数据库中并加密）
define('ADMIN_PASSWORD', 'admin123');

$admin_log_dir = __DIR__ . '/uploads';
if (!is_dir($admin_log_dir)) {
    mkdir($admin_log_dir, 0755, true);
}
$admin_log_file = $admin_log_dir . '/admin_actions.log';

function logAdminAction($action, $status, $detail, $log_file) {
    $time = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true ? 'admin' : 'guest';
    $detail_text = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : (string)$detail;
    $detail_text = str_replace(["\r", "\n"], ' ', $detail_text);
    $line = $time . "\t" . $ip . "\t" . $user . "\t" . $action . "\t" . $status . "\t" . $detail_text . PHP_EOL;
    @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);
}

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // 处理登录
    if ($_POST && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            logAdminAction('login', '成功', '管理员登录成功', $admin_log_file);
            
            // 如果是AJAX请求，返回JSON响应而不是重定向
            if (isset($_POST['action']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => '登录成功']);
                exit;
            }
            
            header('Location: admin.php');
            exit;
        } else {
            $login_error = '密码错误';
            logAdminAction('login', '失败', '管理员密码错误', $admin_log_file);
            
            // 如果是AJAX请求，返回JSON错误响应
            if (isset($_POST['action']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => '密码错误']);
                exit;
            }
        }
    }
    
    // 如果是AJAX请求，返回JSON响应
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '请先登录']);
        exit;
    }
    
    // 显示登录页面
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>管理员登录 - 销售部门抽奖系统</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100 min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-96">
                <h1 class="text-2xl font-bold text-center mb-6">管理员登录</h1>
                <?php if (isset($login_error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($login_error) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                            管理员密码
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                               id="password" name="password" type="password" placeholder="请输入管理员密码" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full" 
                                type="submit">
                            登录
                        </button>
                    </div>
                </form>
                <div class="mt-4 text-center">
                    <a href="index.php" class="text-blue-500 hover:text-blue-700">返回首页</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// 处理各种操作 - 支持从POST和GET中获取action参数
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 处理退出登录
if ($action === 'logout') {
    logAdminAction('logout', '成功', '管理员退出登录', $admin_log_file);
    session_destroy();
    header('Location: admin.php');
    exit;
}
$message = '';
$error = '';

if ($_POST) {
    try {
        switch ($action) {
            case 'add_project':
                $name = trim($_POST['name']);
                if (empty($name)) {
                    throw new Exception('项目名称不能为空');
                }
                $stmt = $pdo->prepare("INSERT INTO projects (name) VALUES (?)");
                $stmt->execute([$name]);
                logAdminAction('add_project', '成功', ['name' => $name], $admin_log_file);
                header('Location: admin.php#projects');
                exit;
                break;
                
            case 'edit_project':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                if (empty($name)) {
                    throw new Exception('项目名称不能为空');
                }
                $stmt = $pdo->prepare("UPDATE projects SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                logAdminAction('edit_project', '成功', ['id' => $id, 'name' => $name], $admin_log_file);
                header('Location: admin.php#projects');
                exit;
                break;
                
            case 'delete_project':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->execute([$id]);
                logAdminAction('delete_project', '成功', ['id' => $id], $admin_log_file);
                header('Location: admin.php#projects');
                exit;
                break;
                
            case 'add_prize':
                $project_id = (int)$_POST['project_id'];
                $name = trim($_POST['name']);
                $is_unlimited = isset($_POST['is_unlimited']) && $_POST['is_unlimited'] == '1';
                $quantity = $is_unlimited ? 999999 : (int)$_POST['quantity'];
                $probability = (float)$_POST['probability'];
                
                if (empty($name)) {
                    throw new Exception('奖品名称不能为空');
                }
                if (!$is_unlimited && $quantity <= 0) {
                    throw new Exception('奖品数量必须大于0');
                }
                if ($probability < 0 || $probability > 100) {
                    throw new Exception('中奖概率必须在0-100之间');
                }
                
                $stmt = $pdo->prepare("INSERT INTO prizes (project_id, name, total_quantity, remaining_quantity, probability) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $name, $quantity, $quantity, $probability]);
                logAdminAction('add_prize', '成功', ['project_id' => $project_id, 'name' => $name, 'quantity' => $quantity, 'probability' => $probability], $admin_log_file);
                header('Location: admin.php?project_id=' . $project_id . '#prizes');
                exit;
                break;
                
            case 'edit_prize':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $is_unlimited = isset($_POST['is_unlimited']) && $_POST['is_unlimited'] == '1';
                $quantity = $is_unlimited ? 999999 : (int)$_POST['quantity'];
                $probability = (float)$_POST['probability'];
                
                if (empty($name)) {
                    throw new Exception('奖品名称不能为空');
                }
                if (!$is_unlimited && $quantity < 0) {
                    throw new Exception('奖品数量不能小于0');
                }
                if ($probability < 0 || $probability > 100) {
                    throw new Exception('中奖概率必须在0-100之间');
                }
                $stmt = $pdo->prepare("SELECT project_id FROM prizes WHERE id = ?");
                $stmt->execute([$id]);
                $project_id = (int)($stmt->fetchColumn() ?: 0);
                if (!$project_id) {
                    throw new Exception('奖品不存在');
                }
                
                $stmt = $pdo->prepare("UPDATE prizes SET name = ?, remaining_quantity = ?, probability = ? WHERE id = ?");
                $stmt->execute([$name, $quantity, $probability, $id]);
                logAdminAction('edit_prize', '成功', ['id' => $id, 'project_id' => $project_id, 'name' => $name, 'quantity' => $quantity, 'probability' => $probability], $admin_log_file);
                header('Location: admin.php?project_id=' . $project_id . '#prizes');
                exit;
                break;
                
            case 'delete_prize':
                $id = (int)$_POST['id'];
                $project_id = $_GET['project_id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM prizes WHERE id = ?");
                $stmt->execute([$id]);
                logAdminAction('delete_prize', '成功', ['id' => $id, 'project_id' => $project_id], $admin_log_file);
                header('Location: admin.php?project_id=' . $project_id . '#prizes');
                exit;
                break;
                
            case 'add_user':
                $name = trim($_POST['name']);
                $ip = trim($_POST['ip']);
                
                if (empty($name)) {
                    throw new Exception('用户姓名不能为空');
                }
                if (empty($ip)) {
                    throw new Exception('IP地址不能为空');
                }
                
                $stmt = $pdo->prepare("INSERT INTO users (name, ip_address) VALUES (?, ?)");
                $stmt->execute([$name, $ip]);
                logAdminAction('add_user', '成功', ['name' => $name, 'ip_address' => $ip], $admin_log_file);
                header('Location: admin.php#users');
                exit;
                break;
                
            case 'edit_user':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $ip = trim($_POST['ip']);
                
                if (empty($name)) {
                    throw new Exception('用户姓名不能为空');
                }
                if (empty($ip)) {
                    throw new Exception('IP地址不能为空');
                }
                
                $stmt = $pdo->prepare("UPDATE users SET name = ?, ip_address = ? WHERE id = ?");
                $stmt->execute([$name, $ip, $id]);
                logAdminAction('edit_user', '成功', ['id' => $id, 'name' => $name, 'ip_address' => $ip], $admin_log_file);
                header('Location: admin.php#users');
                exit;
                break;
                
            case 'delete_user':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                logAdminAction('delete_user', '成功', ['id' => $id], $admin_log_file);
                header('Location: admin.php#users');
                exit;
                break;
                
            case 'get_user_detail':
                $user_id = (int)($_POST['user_id'] ?? 0);
                if (!$user_id) {
                    throw new Exception('用户ID不能为空');
                }
                
                $stmt = $pdo->prepare("SELECT id, name, ip_address, created_at FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if (!$user) {
                    throw new Exception('用户不存在');
                }
                
                $stmt = $pdo->prepare("
                    SELECT upt.*, pr.name as project_name
                    FROM user_project_times upt
                    JOIN projects pr ON upt.project_id = pr.id
                    WHERE upt.user_id = ?
                    ORDER BY pr.name
                ");
                $stmt->execute([$user_id]);
                $times_list = $stmt->fetchAll();
                
                $stmt = $pdo->prepare("
                    SELECT lr.created_at, pr.name as project_name, p.name as prize_name
                    FROM lottery_records lr
                    JOIN projects pr ON lr.project_id = pr.id
                    LEFT JOIN prizes p ON lr.prize_id = p.id
                    WHERE lr.user_id = ?
                    ORDER BY lr.created_at DESC
                    LIMIT 50
                ");
                $stmt->execute([$user_id]);
                $record_list = $stmt->fetchAll();

                $project_name_map = [];
                $project_rows = $pdo->query("SELECT id, name FROM projects")->fetchAll();
                foreach ($project_rows as $project_row) {
                    $project_name_map[(int)$project_row['id']] = $project_row['name'];
                }

                $times_records = [];
                if (is_file($admin_log_file)) {
                    $lines = file($admin_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $lines = array_slice($lines, -2000);
                    $lines = array_reverse($lines);
                    foreach ($lines as $line) {
                        $parts = explode("\t", $line);
                        if (count($parts) < 6) {
                            continue;
                        }
                        $log_time = $parts[0];
                        $log_ip = $parts[1];
                        $log_action = $parts[3];
                        $log_status = $parts[4];
                        $detail_text = $parts[5];
                        if (!in_array($log_action, ['set_user_times', 'batch_set_user_times'], true)) {
                            continue;
                        }
                        $detail = json_decode($detail_text, true);
                        if (!is_array($detail)) {
                            continue;
                        }
                        $match = false;
                        if ($log_action === 'set_user_times' && (int)($detail['user_id'] ?? 0) === $user_id) {
                            $match = true;
                        }
                        if ($log_action === 'batch_set_user_times') {
                            $detail_user_ids = $detail['user_ids'] ?? [];
                            if (is_array($detail_user_ids) && in_array($user_id, $detail_user_ids, true)) {
                                $match = true;
                            }
                        }
                        if (!$match) {
                            continue;
                        }
                        $project_id = (int)($detail['project_id'] ?? 0);
                        $times_records[] = [
                            'time' => $log_time,
                            'ip' => $log_ip,
                            'project_name' => $project_name_map[$project_id] ?? ('项目' . $project_id),
                            'times' => (int)($detail['times'] ?? 0),
                            'status' => $log_status,
                            'action' => $log_action === 'set_user_times' ? '单人分配' : '批量分配'
                        ];
                        if (count($times_records) >= 50) {
                            break;
                        }
                    }
                }
                
                ob_start();
                ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-xs text-gray-500">姓名</div>
                        <div class="text-base font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-xs text-gray-500">IP地址</div>
                        <div class="text-base font-semibold text-gray-800"><?php echo htmlspecialchars($user['ip_address']); ?></div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-xs text-gray-500">注册时间</div>
                        <div class="text-base font-semibold text-gray-800"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-700 mb-2">次数分配</div>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2">项目</th>
                                    <th class="px-4 py-2">总次数</th>
                                    <th class="px-4 py-2">剩余次数</th>
                                    <th class="px-4 py-2">是否显示</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($times_list)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">暂无次数分配记录</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($times_list as $times_row): ?>
                                <tr class="bg-white border-b">
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($times_row['project_name']); ?></td>
                                    <td class="px-4 py-2"><?php echo $times_row['total_times']; ?></td>
                                    <td class="px-4 py-2"><?php echo $times_row['remaining_times']; ?></td>
                                    <td class="px-4 py-2"><?php echo $times_row['is_visible'] ? '显示' : '隐藏'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-700 mb-2">次数分配记录（最近50条）</div>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2">时间</th>
                                    <th class="px-4 py-2">操作IP</th>
                                    <th class="px-4 py-2">项目</th>
                                    <th class="px-4 py-2">变更次数</th>
                                    <th class="px-4 py-2">方式</th>
                                    <th class="px-4 py-2">状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($times_records)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">暂无次数分配记录</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($times_records as $times_record): ?>
                                <tr class="bg-white border-b">
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($times_record['time']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($times_record['ip']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($times_record['project_name']); ?></td>
                                    <td class="px-4 py-2"><?php echo $times_record['times']; ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($times_record['action']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($times_record['status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-700 mb-2">抽奖记录（最近50条）</div>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2">时间</th>
                                    <th class="px-4 py-2">项目</th>
                                    <th class="px-4 py-2">奖品</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($record_list)): ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-500">暂无抽奖记录</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($record_list as $record): ?>
                                <tr class="bg-white border-b">
                                    <td class="px-4 py-2"><?php echo date('Y-m-d H:i', strtotime($record['created_at'])); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($record['project_name']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($record['prize_name'] ?? '未中奖'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php
                $detail_html = ob_get_clean();
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'title' => $user['name'] . ' 详情',
                    'html' => $detail_html
                ]);
                logAdminAction('get_user_detail', '成功', ['user_id' => $user_id], $admin_log_file);
                exit;
                break;
                
            case 'set_user_times':
                $user_id = (int)$_POST['user_id'];
                $project_id = (int)$_POST['project_id'];
                $times = (int)$_POST['times'];
                $is_visible = isset($_POST['is_visible']) ? 1 : 0;
                
                $stmt = $pdo->prepare("SELECT total_times, remaining_times FROM user_project_times WHERE user_id = ? AND project_id = ?");
                $stmt->execute([$user_id, $project_id]);
                $row = $stmt->fetch();
                
                if ($row) {
                    $new_total = max(0, (int)$row['total_times'] + $times);
                    $new_remaining = max(0, (int)$row['remaining_times'] + $times);
                    $stmt = $pdo->prepare("UPDATE user_project_times SET total_times = ?, remaining_times = ?, is_visible = ? WHERE user_id = ? AND project_id = ?");
                    $stmt->execute([$new_total, $new_remaining, $is_visible, $user_id, $project_id]);
                } else {
                    $init_total = $times >= 0 ? $times : 0;
                    $init_remaining = $times >= 0 ? $times : 0;
                    $stmt = $pdo->prepare("INSERT INTO user_project_times (user_id, project_id, total_times, remaining_times, is_visible) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $project_id, $init_total, $init_remaining, $is_visible]);
                }
                logAdminAction('set_user_times', '成功', ['user_id' => $user_id, 'project_id' => $project_id, 'times' => $times, 'is_visible' => $is_visible], $admin_log_file);
                header('Location: admin.php?project_id=' . $project_id . '#user-times');
                exit;
                break;
                
            case 'batch_set_user_times':
                $project_id = (int)$_POST['project_id'];
                $times = (int)$_POST['times'];
                $is_visible = isset($_POST['is_visible']) ? 1 : 0;
                $user_ids = $_POST['user_ids'] ?? [];
                
                if (empty($user_ids)) {
                    throw new Exception('请至少选择一个用户');
                }
                
                $pdo->beginTransaction();
                
                try {
                    $success_count = 0;
                    foreach ($user_ids as $user_id) {
                        $user_id = (int)$user_id;
                        
                        $stmt = $pdo->prepare("SELECT total_times, remaining_times FROM user_project_times WHERE user_id = ? AND project_id = ?");
                        $stmt->execute([$user_id, $project_id]);
                        $row = $stmt->fetch();
                        
                        if ($row) {
                            $new_total = max(0, (int)$row['total_times'] + $times);
                            $new_remaining = max(0, (int)$row['remaining_times'] + $times);
                            $stmt = $pdo->prepare("UPDATE user_project_times SET total_times = ?, remaining_times = ?, is_visible = ? WHERE user_id = ? AND project_id = ?");
                            $stmt->execute([$new_total, $new_remaining, $is_visible, $user_id, $project_id]);
                        } else {
                            $init_total = $times >= 0 ? $times : 0;
                            $init_remaining = $times >= 0 ? $times : 0;
                            $stmt = $pdo->prepare("INSERT INTO user_project_times (user_id, project_id, total_times, remaining_times, is_visible) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$user_id, $project_id, $init_total, $init_remaining, $is_visible]);
                        }
                        
                        $success_count++;
                    }
                    
                    $pdo->commit();
                    logAdminAction('batch_set_user_times', '成功', ['project_id' => $project_id, 'times' => $times, 'is_visible' => $is_visible, 'count' => $success_count, 'user_ids' => array_values(array_map('intval', $user_ids))], $admin_log_file);
                    header('Location: admin.php?project_id=' . $project_id . '#user-times');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            case 'get_user_times':
                $project_id = (int)($_POST['project_id'] ?? 0);
                $rows_html = '';
                
                if ($project_id) {
                    $stmt = $pdo->prepare("
                        SELECT upt.*, u.name as user_name, u.ip_address 
                        FROM user_project_times upt 
                        JOIN users u ON upt.user_id = u.id 
                        WHERE upt.project_id = ? 
                        ORDER BY u.name
                    ");
                    $stmt->execute([$project_id]);
                    $user_project_times = $stmt->fetchAll();
                    
                    ob_start();
                    if (empty($user_project_times)) {
                        ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <i class="fa fa-inbox text-4xl mb-2"></i>
                                <div>暂无用户次数</div>
                            </td>
                        </tr>
                        <?php
                    } else {
                        foreach ($user_project_times as $upt) {
                            ?>
                            <tr class="bg-white border-b" data-name="<?php echo htmlspecialchars($upt['user_name']); ?>" data-total="<?php echo $upt['total_times']; ?>" data-remaining="<?php echo $upt['remaining_times']; ?>">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($upt['user_name']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($upt['ip_address']); ?></td>
                                <td class="px-6 py-4"><?php echo $upt['total_times']; ?></td>
                                <td class="px-6 py-4"><?php echo $upt['remaining_times']; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $upt['is_visible'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $upt['is_visible'] ? '显示' : '隐藏'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick="editUserTimes(<?php echo $upt['user_id']; ?>, <?php echo $project_id; ?>, <?php echo $upt['remaining_times']; ?>, <?php echo $upt['is_visible']; ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    $rows_html = ob_get_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'rows_html' => $rows_html
                ]);
                logAdminAction('get_user_times', '成功', ['project_id' => $project_id], $admin_log_file);
                exit;
                break;
                
            case 'get_records':
                $records_per_page = 20;
                $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
                $offset = ($page - 1) * $records_per_page;
                $keyword = trim($_POST['keyword'] ?? '');
                $use_search = $keyword !== '';
                $like_keyword = '%' . $keyword . '%';
                
                if ($use_search) {
                    $total_records_stmt = $pdo->prepare("
                        SELECT COUNT(*)
                        FROM lottery_records lr
                        JOIN users u ON lr.user_id = u.id
                        WHERE u.name LIKE ?
                    ");
                    $total_records_stmt->execute([$like_keyword]);
                    $total_records = $total_records_stmt->fetchColumn();
                } else {
                    $total_records_stmt = $pdo->query("SELECT COUNT(*) FROM lottery_records");
                    $total_records = $total_records_stmt->fetchColumn();
                }
                $total_pages = $total_records > 0 ? (int)ceil($total_records / $records_per_page) : 0;
                
                $records_sql = "
                    SELECT lr.*, u.name as user_name, u.ip_address, 
                           p.name as prize_name, pr.name as project_name
                    FROM lottery_records lr
                    JOIN users u ON lr.user_id = u.id
                    JOIN projects pr ON lr.project_id = pr.id
                    LEFT JOIN prizes p ON lr.prize_id = p.id
                ";
                if ($use_search) {
                    $records_sql .= " WHERE u.name LIKE ? ";
                }
                $records_sql .= " ORDER BY lr.created_at DESC ";
                $records_sql .= " LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset;
                $records_stmt = $pdo->prepare($records_sql);
                if ($use_search) {
                    $records_stmt->execute([$like_keyword]);
                } else {
                    $records_stmt->execute();
                }
                $lottery_records = $records_stmt->fetchAll();
                
                ob_start();
                if (empty($lottery_records)) {
                    ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            <i class="fa fa-inbox text-4xl mb-2"></i>
                            <div>暂无抽奖记录</div>
                        </td>
                    </tr>
                    <?php
                } else {
                    foreach ($lottery_records as $record) {
                        ?>
                        <tr class="bg-white border-b hover:bg-gray-50" data-user="<?php echo htmlspecialchars($record['user_name']); ?>">
                            <td class="px-6 py-4"><?php echo $record['id']; ?></td>
                            <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($record['user_name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($record['ip_address']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($record['project_name']); ?></td>
                            <td class="px-6 py-4">
                                <?php if ($record['prize_id']): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        <i class="fa fa-gift mr-1"></i><?php echo htmlspecialchars($record['prize_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                        <i class="fa fa-times-circle mr-1"></i>未中奖
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4"><?php echo date('Y-m-d H:i:s', strtotime($record['created_at'])); ?></td>
                        </tr>
                        <?php
                    }
                }
                $rows_html = ob_get_clean();
                
                ob_start();
                if ($total_pages > 1) {
                    ?>
                    <div class="flex justify-center mt-6">
                        <nav class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <button type="button" onclick="refreshLotteryRecords(<?php echo $page - 1; ?>)" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <button type="button" onclick="refreshLotteryRecords(<?php echo $i; ?>)" class="px-3 py-2 text-sm <?php echo $i == $page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <button type="button" onclick="refreshLotteryRecords(<?php echo $page + 1; ?>)" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php
                }
                $pagination_html = ob_get_clean();
                
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'total_records' => (int)$total_records,
                    'rows_html' => $rows_html,
                    'pagination_html' => $pagination_html
                ]);
                logAdminAction('get_records', '成功', ['page' => $page, 'keyword' => $keyword], $admin_log_file);
                exit;
                break;
                
            case 'update_probabilities':
                // 处理概率批量更新 - 独立的异常处理确保始终返回JSON
                try {
                    $project_id = (int)$_POST['project_id'];
                    $probabilities_json = $_POST['probabilities'] ?? '';
                    
                    if (empty($probabilities_json)) {
                        throw new Exception('概率数据不能为空');
                    }
                    
                    $probabilities = json_decode($probabilities_json, true);
                    if (!is_array($probabilities)) {
                        throw new Exception('概率数据格式错误');
                    }
                    
                    // 开始事务
                    $pdo->beginTransaction();
                    
                    try {
                        foreach ($probabilities as $prize_id => $probability) {
                            $prize_id = (int)$prize_id;
                            $probability = (float)$probability;
                            
                            if ($probability < 0 || $probability > 100) {
                                throw new Exception("奖品ID {$prize_id} 的概率必须在0-100之间");
                            }
                            
                            // 验证奖品是否属于指定项目
                            $stmt = $pdo->prepare("SELECT id FROM prizes WHERE id = ? AND project_id = ?");
                            $stmt->execute([$prize_id, $project_id]);
                            if (!$stmt->fetch()) {
                                throw new Exception("奖品ID {$prize_id} 不存在或不属于当前项目");
                            }
                            
                            // 更新概率
                            $stmt = $pdo->prepare("UPDATE prizes SET probability = ? WHERE id = ?");
                            $stmt->execute([$probability, $prize_id]);
                        }
                        
                        $pdo->commit();
                        
                        // 返回成功的JSON响应
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => '概率更新成功']);
                        logAdminAction('update_probabilities', '成功', ['project_id' => $project_id], $admin_log_file);
                        exit;
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    
                } catch (Exception $e) {
                    // 返回错误的JSON响应
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    logAdminAction('update_probabilities', '失败', $e->getMessage(), $admin_log_file);
                    exit;
                } catch (PDOException $e) {
                    // 返回数据库错误的JSON响应
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => '数据库操作失败：' . $e->getMessage()]);
                    logAdminAction('update_probabilities', '失败', '数据库操作失败：' . $e->getMessage(), $admin_log_file);
                    exit;
                }
                break;
                
            case 'get_prizes_data':
                // 获取奖品数据的AJAX处理
                try {
                    $project_id = (int)$_POST['project_id'];
                    
                    // 获取奖品数据
                    $stmt = $pdo->prepare("SELECT id, name, probability FROM prizes WHERE project_id = ? ORDER BY sort_order, id");
                    $stmt->execute([$project_id]);
                    $prizes = $stmt->fetchAll();
                    
                    // 计算总概率
                    $total_probability = 0;
                    foreach ($prizes as $prize) {
                        $total_probability += $prize['probability'];
                    }
                    
                    // 返回JSON响应
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'prizes' => $prizes,
                        'total_probability' => number_format($total_probability, 2)
                    ]);
                    logAdminAction('get_prizes_data', '成功', ['project_id' => $project_id], $admin_log_file);
                    exit;
                    
                } catch (Exception $e) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    logAdminAction('get_prizes_data', '失败', $e->getMessage(), $admin_log_file);
                    exit;
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if ($action !== '') {
            logAdminAction($action, '失败', $error, $admin_log_file);
        }
    } catch (PDOException $e) {
        $error = '数据库操作失败：' . $e->getMessage();
        if ($action !== '') {
            logAdminAction($action, '失败', $error, $admin_log_file);
        }
    }
}

// 获取数据
$projects = $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();

// 获取奖品（如果选择了项目）
$selected_project_id = $_GET['project_id'] ?? ($projects[0]['id'] ?? 0);
$prizes = [];
$total_probability = 0; // 初始化概率合计变量
if ($selected_project_id) {
    $stmt = $pdo->prepare("SELECT * FROM prizes WHERE project_id = ? ORDER BY sort_order, id");
    $stmt->execute([$selected_project_id]);
    $prizes = $stmt->fetchAll();
    
    // 计算所有奖品的概率合计
    foreach ($prizes as $prize) {
        $total_probability += $prize['probability'];
    }
}

// 获取用户项目次数
$user_project_times = [];
if ($selected_project_id) {
    $stmt = $pdo->prepare("
        SELECT upt.*, u.name as user_name, u.ip_address 
        FROM user_project_times upt 
        JOIN users u ON upt.user_id = u.id 
        WHERE upt.project_id = ? 
        ORDER BY u.name
    ");
    $stmt->execute([$selected_project_id]);
    $user_project_times = $stmt->fetchAll();
}

// 获取抽奖记录
$records_per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// 获取总记录数
$total_records_stmt = $pdo->query("SELECT COUNT(*) FROM lottery_records");
$total_records = $total_records_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// 获取抽奖记录
$records_stmt = $pdo->prepare("
    SELECT lr.*, u.name as user_name, u.ip_address, 
           p.name as prize_name, pr.name as project_name
    FROM lottery_records lr
    JOIN users u ON lr.user_id = u.id
    JOIN projects pr ON lr.project_id = pr.id
    LEFT JOIN prizes p ON lr.prize_id = p.id
    ORDER BY lr.created_at DESC
    LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset
);
$records_stmt->execute();
$lottery_records = $records_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- 头部 -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fa fa-cog text-blue-500 mr-2"></i>
                    管理后台
                </h1>
                <a href="index.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">
                    <i class="fa fa-home mr-2"></i>返回首页
                </a>
            </div>
        </div>

        <!-- 消息提示 -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fa fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fa fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- 标签页导航 -->
        <div class="bg-white rounded-lg shadow-lg mb-8">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button onclick="showTab('projects')" id="tab-projects" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                        <i class="fa fa-project-diagram mr-2"></i>项目管理
                    </button>
                    <button onclick="showTab('prizes')" id="tab-prizes" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-gift mr-2"></i>奖品管理
                    </button>
                    <button onclick="showTab('users')" id="tab-users" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-users mr-2"></i>用户管理
                    </button>
                    <button onclick="showTab('user-times')" id="tab-user-times" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-clock mr-2"></i>次数分配
                    </button>
                    <button onclick="showTab('records')" id="tab-records" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-history mr-2"></i>抽奖记录
                    </button>
                    <button onclick="showTab('logout')" id="tab-logout" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-sign-out-alt mr-2"></i>退出登录
                    </button>
                </nav>
            </div>

            <!-- 项目管理 -->
            <div id="content-projects" class="tab-content p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">项目管理</h2>
                    <button onclick="showModal('add-project-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-plus mr-2"></i>添加项目
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">项目名称</th>
                                <th class="px-6 py-3">状态</th>
                                <th class="px-6 py-3">创建时间</th>
                                <th class="px-6 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                            <tr class="bg-white border-b">
                                <td class="px-6 py-4"><?php echo $project['id']; ?></td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($project['name']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $project['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $project['status'] ? '启用' : '禁用'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?php echo date('Y-m-d H:i', strtotime($project['created_at'])); ?></td>
                                <td class="px-6 py-4">
                                    <button onclick="editProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <button onclick="deleteProject(<?php echo $project['id']; ?>)" class="text-red-600 hover:text-red-900">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 奖品管理 -->
            <div id="content-prizes" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-xl font-bold text-gray-800">奖品管理</h2>
                        <select onchange="location.href='?project_id='+this.value+'#prizes'" class="border border-gray-300 rounded-lg px-3 py-2">
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $selected_project_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="showModal('add-prize-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                            <i class="fa fa-plus mr-2"></i>添加奖品
                        </button>
                        <button onclick="showModal('probability-adjust-modal')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">
                            <i class="fa fa-sliders mr-2"></i>快速调整概率
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">奖品名称</th>
                                <th class="px-6 py-3">剩余数量</th>
                                <th class="px-6 py-3">中奖概率</th>
                                <th class="px-6 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prizes as $prize): ?>
                            <tr class="bg-white border-b" data-prize-id="<?php echo $prize['id']; ?>">
                                <td class="px-6 py-4"><?php echo $prize['id']; ?></td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($prize['name']); ?></td>
                                <td class="px-6 py-4"><?php echo $prize['remaining_quantity'] >= 999999 ? '无限' : $prize['remaining_quantity']; ?></td>
                                <td class="px-6 py-4 probability-cell"><?php echo $prize['probability']; ?>%</td>
                                <td class="px-6 py-4">
                                    <button onclick="editPrize(<?php echo $prize['id']; ?>, '<?php echo htmlspecialchars($prize['name'], ENT_QUOTES); ?>', <?php echo $prize['remaining_quantity']; ?>, <?php echo $prize['probability']; ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <button onclick="deletePrize(<?php echo $prize['id']; ?>)" class="text-red-600 hover:text-red-900">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- 合计概率显示行 -->
                            <?php if (!empty($prizes)): ?>
                            <tr class="bg-red-50 border-b-2 border-red-200">
                                <td class="px-6 py-4 font-bold text-red-700" colspan="3">合计概率</td>
                                <td class="px-6 py-4 font-bold text-red-700 text-lg total-probability-cell"><?php echo number_format($total_probability, 2); ?>%</td>
                                <td class="px-6 py-4"></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 用户管理 -->
            <div id="content-users" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">用户管理</h2>
                    <div class="flex items-center space-x-3">
                        <input type="text" id="users-filter" placeholder="筛选姓名" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" oninput="applyUsersFilter()">
                        <button onclick="showModal('add-user-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                            <i class="fa fa-plus mr-2"></i>添加用户
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">姓名</th>
                                <th class="px-6 py-3">IP地址</th>
                                <th class="px-6 py-3">注册时间</th>
                                <th class="px-6 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody id="users-body">
                            <?php foreach ($users as $user): ?>
                            <tr class="bg-white border-b" data-name="<?php echo htmlspecialchars($user['name']); ?>">
                                <td class="px-6 py-4"><?php echo $user['id']; ?></td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($user['name']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($user['ip_address']); ?></td>
                                <td class="px-6 py-4"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                <td class="px-6 py-4">
                                    <button onclick="showUserDetail(<?php echo $user['id']; ?>)" class="text-gray-600 hover:text-gray-900 mr-3">
                                        <i class="fa fa-info-circle"></i>
                                    </button>
                                    <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['ip_address'], ENT_QUOTES); ?>')" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="text-red-600 hover:text-red-900">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 次数分配 -->
            <div id="content-user-times" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-xl font-bold text-gray-800">用户抽奖次数分配</h2>
                        <select onchange="location.href='?project_id='+this.value+'#user-times'" class="border border-gray-300 rounded-lg px-3 py-2">
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $selected_project_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex items-center space-x-3">
                        <input type="text" id="user-times-filter" placeholder="筛选姓名" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" oninput="applyUserTimesFilter()">
                        <button type="button" onclick="refreshUserTimes()" class="px-3 py-1 text-sm text-blue-600 border border-blue-300 rounded hover:bg-blue-50">
                            刷新
                        </button>
                        <button onclick="showModal('batch-set-times-modal')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">
                            <i class="fa fa-users mr-2"></i>批量分配
                        </button>
                        <button onclick="showModal('set-times-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                            <i class="fa fa-plus mr-2"></i>分配次数
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">
                                    <button type="button" class="flex items-center space-x-2" onclick="sortUserTimesTable('name')">
                                        <span>用户姓名</span>
                                        <i id="sort-icon-name" class="fa fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3">IP地址</th>
                                <th class="px-6 py-3">
                                    <button type="button" class="flex items-center space-x-2" onclick="sortUserTimesTable('total')">
                                        <span>总次数</span>
                                        <i id="sort-icon-total" class="fa fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3">
                                    <button type="button" class="flex items-center space-x-2" onclick="sortUserTimesTable('remaining')">
                                        <span>剩余次数</span>
                                        <i id="sort-icon-remaining" class="fa fa-sort text-xs"></i>
                                    </button>
                                </th>
                                <th class="px-6 py-3">是否显示</th>
                                <th class="px-6 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody id="user-times-body">
                            <?php foreach ($user_project_times as $upt): ?>
                            <tr class="bg-white border-b" data-name="<?php echo htmlspecialchars($upt['user_name']); ?>" data-total="<?php echo $upt['total_times']; ?>" data-remaining="<?php echo $upt['remaining_times']; ?>">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($upt['user_name']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($upt['ip_address']); ?></td>
                                <td class="px-6 py-4"><?php echo $upt['total_times']; ?></td>
                                <td class="px-6 py-4"><?php echo $upt['remaining_times']; ?></td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $upt['is_visible'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $upt['is_visible'] ? '显示' : '隐藏'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick="editUserTimes(<?php echo $upt['user_id']; ?>, <?php echo $selected_project_id; ?>, <?php echo $upt['remaining_times']; ?>, <?php echo $upt['is_visible']; ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 抽奖记录 -->
            <div id="content-records" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">抽奖记录</h2>
                    <div class="flex items-center space-x-3">
                        <input type="text" id="lottery-records-filter" placeholder="搜索用户" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" oninput="scheduleLotteryRecordsSearch()">
                        <div class="text-sm text-gray-500">
                            共 <span id="total-records-count"><?php echo $total_records; ?></span> 条记录
                        </div>
                        <button type="button" onclick="refreshLotteryRecords()" class="px-3 py-1 text-sm text-blue-600 border border-blue-300 rounded hover:bg-blue-50">
                            刷新
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">ID</th>
                                <th class="px-6 py-3">用户</th>
                                <th class="px-6 py-3">IP地址</th>
                                <th class="px-6 py-3">项目</th>
                                <th class="px-6 py-3">抽奖结果</th>
                                <th class="px-6 py-3">抽奖时间</th>
                            </tr>
                        </thead>
                        <tbody id="lottery-records-body">
                            <?php if (empty($lottery_records)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fa fa-inbox text-4xl mb-2"></i>
                                    <div>暂无抽奖记录</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($lottery_records as $record): ?>
                                <tr class="bg-white border-b hover:bg-gray-50" data-user="<?php echo htmlspecialchars($record['user_name']); ?>">
                                    <td class="px-6 py-4"><?php echo $record['id']; ?></td>
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($record['user_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($record['ip_address']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($record['project_name']); ?></td>
                                    <td class="px-6 py-4">
                                        <?php if ($record['prize_id']): ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                                <i class="fa fa-gift mr-1"></i><?php echo htmlspecialchars($record['prize_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-600">
                                                <i class="fa fa-times-circle mr-1"></i>未中奖
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4"><?php echo date('Y-m-d H:i:s', strtotime($record['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- 分页 -->
                <div id="lottery-records-pagination">
                    <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <nav class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>#records" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <i class="fa fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>#records" class="px-3 py-2 text-sm <?php echo $i == $page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>#records" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <i class="fa fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 模态框 -->
    <!-- 添加项目模态框 -->
    <div id="add-project-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">添加项目</h3>
            <form method="POST" action="?action=add_project#projects">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">项目名称</label>
                    <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('add-project-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        添加
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑项目模态框 -->
    <div id="edit-project-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">编辑项目</h3>
            <form method="POST" action="?action=edit_project#projects">
                <input type="hidden" name="id" id="edit-project-id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">项目名称</label>
                    <input type="text" name="name" id="edit-project-name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('edit-project-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        更新
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 添加奖品模态框 -->
    <div id="add-prize-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">添加奖品</h3>
            <form method="POST" action="?action=add_prize&project_id=<?php echo $selected_project_id; ?>#prizes">
                <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">奖品名称</label>
                    <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">奖品数量</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="quantity_type" value="unlimited" class="mr-2" checked onchange="toggleQuantityInput(this)">
                            <span>无限数量</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="quantity_type" value="limited" class="mr-2" onchange="toggleQuantityInput(this)">
                            <span>限定数量</span>
                        </label>
                    </div>
                    <input type="hidden" name="is_unlimited" value="1">
                    <input type="number" name="quantity" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-2 hidden" placeholder="请输入数量">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">中奖概率(%)</label>
                    <input type="number" name="probability" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('add-prize-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        添加
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑奖品模态框 -->
    <div id="edit-prize-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">编辑奖品</h3>
            <form method="POST" action="?action=edit_prize&project_id=<?php echo $selected_project_id; ?>#prizes">
                <input type="hidden" name="id" id="edit-prize-id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">奖品名称</label>
                    <input type="text" name="name" id="edit-prize-name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">剩余数量</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="quantity_type" value="unlimited" class="mr-2" onchange="toggleEditQuantityInput(this)">
                            <span>无限数量</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="quantity_type" value="limited" class="mr-2" onchange="toggleEditQuantityInput(this)">
                            <span>限定数量</span>
                        </label>
                    </div>
                    <input type="hidden" name="is_unlimited" value="0">
                    <input type="number" name="quantity" id="edit-prize-quantity" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">中奖概率(%)</label>
                    <input type="number" name="probability" id="edit-prize-probability" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('edit-prize-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        更新
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 添加用户模态框 -->
    <div id="add-user-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">添加用户</h3>
            <form method="POST" action="?action=add_user#users">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">用户姓名</label>
                    <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">IP地址</label>
                    <input type="text" name="ip" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('add-user-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        添加
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑用户模态框 -->
    <div id="edit-user-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">编辑用户</h3>
            <form method="POST" action="?action=edit_user#users">
                <input type="hidden" name="id" id="edit-user-id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">用户姓名</label>
                    <input type="text" name="name" id="edit-user-name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">IP地址</label>
                    <input type="text" name="ip" id="edit-user-ip" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="saveScrollPosition(); location.reload();" class="px-4 py-2 text-blue-600 border border-blue-300 rounded-lg hover:bg-blue-50">
                        刷新
                    </button>
                    <button type="button" onclick="hideModal('edit-user-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        更新
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="user-detail-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 id="user-detail-title" class="text-lg font-bold text-gray-800">用户详情</h3>
                <button type="button" onclick="hideModal('user-detail-modal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div id="user-detail-body" class="space-y-4 text-sm"></div>
            <div class="flex justify-end mt-4">
                <button type="button" onclick="hideModal('user-detail-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    关闭
                </button>
            </div>
        </div>
    </div>

    <!-- 设置抽奖次数模态框 -->
    <div id="set-times-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">增加抽奖次数</h3>
            <form method="POST" action="?action=set_user_times&project_id=<?php echo $selected_project_id; ?>#user-times">
                <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">选择用户</label>
                    <div class="relative">
                        <input type="text" id="user-search" placeholder="搜索用户..." class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-2" onkeyup="filterUsers()">
                        <select name="user_id" id="user-select" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                            <option value="">请选择用户</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" data-name="<?php echo htmlspecialchars($user['name']); ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">增加抽奖次数</label>
                    <input type="number" name="times" min="-999999" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_visible" checked class="mr-2">
                        <span class="text-sm text-gray-700">在该项目中显示此用户</span>
                    </label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('set-times-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        增加
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑用户次数模态框 -->
    <div id="edit-times-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">增加抽奖次数</h3>
            <form method="POST" action="?action=set_user_times#user-times">
                <input type="hidden" name="user_id" id="edit-times-user-id">
                <input type="hidden" name="project_id" id="edit-times-project-id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">增加抽奖次数</label>
                    <input type="number" name="times" id="edit-times-times" min="-999999" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4 flex space-x-2">
                    <button type="button" onclick="incrementTimes('edit-times-times', 1)" class="px-3 py-1 text-sm text-blue-600 border border-blue-300 rounded hover:bg-blue-50">+1</button>
                    <button type="button" onclick="incrementTimes('edit-times-times', 3)" class="px-3 py-1 text-sm text-blue-600 border border-blue-300 rounded hover:bg-blue-50">+3</button>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_visible" id="edit-times-visible" class="mr-2">
                        <span class="text-sm text-gray-700">在该项目中显示此用户</span>
                    </label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('edit-times-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        增加
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 批量分配次数模态框 -->
    <div id="batch-set-times-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
            <h3 class="text-lg font-bold mb-4">批量分配抽奖次数</h3>
            <form method="POST" action="?action=batch_set_user_times#user-times">
                <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">选择用户</label>
                    <div class="border border-gray-300 rounded-lg p-3 max-h-40 overflow-y-auto">
                        <?php foreach ($users as $user): ?>
                        <div class="flex items-center mb-2">
                            <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" id="user-<?php echo $user['id']; ?>" class="mr-2">
                            <label for="user-<?php echo $user['id']; ?>" class="text-sm flex-1">
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['ip_address']); ?>)
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-2">
                        <button type="button" onclick="toggleAllUsers(true)" class="text-xs text-blue-600 hover:text-blue-800 mr-3">全选</button>
                        <button type="button" onclick="toggleAllUsers(false)" class="text-xs text-blue-600 hover:text-blue-800">取消全选</button>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">抽奖次数</label>
                    <input type="number" name="times" min="-999999" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_visible" checked class="mr-2">
                        <span class="text-sm text-gray-700">在该项目中显示这些用户</span>
                    </label>
                </div>
                
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                    <p class="text-sm text-blue-800">
                        <i class="fa fa-info-circle mr-1"></i>
                        提示：批量分配将为所有选中的用户增加相同的抽奖次数。如果用户已有该项目的抽奖次数，将进行累加。
                    </p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('batch-set-times-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                        批量设置
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 概率调整模态框 -->
    <div id="probability-adjust-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
            <h3 class="text-lg font-bold mb-4">快速调整概率</h3>
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">当前项目：<span class="font-medium"><?php echo htmlspecialchars($projects[array_search($selected_project_id, array_column($projects, 'id'))]['name'] ?? '未选择'); ?></span></p>
                <p class="text-sm text-gray-600 mb-4">当前总概率：<span id="current-total-probability" class="font-medium text-red-600"><?php echo number_format($total_probability, 2); ?>%</span></p>
            </div>
            
            <div class="max-h-96 overflow-y-auto mb-4">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">奖品名称</th>
                            <th class="px-4 py-2 text-left">当前概率</th>
                            <th class="px-4 py-2 text-left">新概率</th>
                            <th class="px-4 py-2 text-left">滑动调整</th>
                        </tr>
                    </thead>
                    <tbody id="probability-adjust-table">
                        <?php foreach ($prizes as $prize): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($prize['name']); ?></td>
                            <td class="px-4 py-2"><?php echo $prize['probability']; ?>%</td>
                            <td class="px-4 py-2">
                                <input type="number" 
                                       class="probability-input w-20 border border-gray-300 rounded px-2 py-1 text-sm" 
                                       data-prize-id="<?php echo $prize['id']; ?>"
                                       value="<?php echo $prize['probability']; ?>" 
                                       min="0" 
                                       max="100" 
                                       step="0.01"
                                       onchange="updateProbabilitySlider(this)">
                                <span class="text-xs text-gray-500">%</span>
                            </td>
                            <td class="px-4 py-2">
                                <input type="range" 
                                       class="probability-slider w-24" 
                                       data-prize-id="<?php echo $prize['id']; ?>"
                                       value="<?php echo $prize['probability']; ?>" 
                                       min="0" 
                                       max="100" 
                                       step="0.01"
                                       oninput="updateProbabilityInput(this)">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-sm text-yellow-800">
                    <i class="fa fa-exclamation-triangle mr-1"></i>
                    提示：调整后的总概率为 <span id="new-total-probability" class="font-bold">0.00%</span>
                </p>
            </div>
            
            <div class="flex justify-between items-center">
                <div class="flex space-x-2">
                    <button type="button" onclick="resetProbabilities()" class="px-3 py-2 text-sm bg-gray-500 text-white rounded hover:bg-gray-600">
                        <i class="fa fa-refresh mr-1"></i>重置
                    </button>
                    <button type="button" onclick="averageProbabilities()" class="px-3 py-2 text-sm bg-orange-500 text-white rounded hover:bg-orange-600">
                        <i class="fa fa-balance-scale mr-1"></i>平均分配
                    </button>
                </div>
                <div class="flex space-x-3">
                    <button type="button" onclick="hideModal('probability-adjust-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                        取消
                    </button>
                    <button type="button" onclick="saveProbabilities()" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                        <i class="fa fa-save mr-1"></i>保存调整
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // 标签页切换
    function showTab(tabName) {
        // 处理退出登录
        if (tabName === 'logout') {
            if (confirm('确定要退出登录吗？')) {
                window.location.href = '?action=logout';
            }
            return;
        }
        
        // 隐藏所有内容
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        // 重置所有标签按钮
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('border-blue-500', 'text-blue-600');
            button.classList.add('border-transparent', 'text-gray-500');
        });
        
        // 显示选中的内容
        document.getElementById('content-' + tabName).classList.remove('hidden');
        
        // 激活选中的标签
        const activeTab = document.getElementById('tab-' + tabName);
        activeTab.classList.remove('border-transparent', 'text-gray-500');
        activeTab.classList.add('border-blue-500', 'text-blue-600');
    }

    function saveScrollPosition() {
        sessionStorage.setItem('admin_scroll_y', String(window.scrollY));
        sessionStorage.setItem('admin_scroll_hash', window.location.hash || '');
    }

    function restoreScrollPosition() {
        const savedY = sessionStorage.getItem('admin_scroll_y');
        if (savedY !== null) {
            const y = parseInt(savedY, 10);
            if (!Number.isNaN(y)) {
                window.scrollTo(0, y);
            }
            sessionStorage.removeItem('admin_scroll_y');
            sessionStorage.removeItem('admin_scroll_hash');
        }
    }

    function refreshUserTimes() {
        const formData = new FormData();
        formData.append('action', 'get_user_times');
        formData.append('project_id', '<?php echo (int)$selected_project_id; ?>');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('刷新失败：' + (data.message || '未知错误'));
                return;
            }
            const tbody = document.getElementById('user-times-body');
            if (tbody) {
                tbody.innerHTML = data.rows_html || '';
            }
            applyUserTimesFilter();
        })
        .catch(() => {
            alert('刷新失败，请重试');
        });
    }

    let lotteryRecordsSearchTimer = null;
    let currentRecordsPage = <?php echo (int)$page; ?>;

    function scheduleLotteryRecordsSearch() {
        if (lotteryRecordsSearchTimer) {
            clearTimeout(lotteryRecordsSearchTimer);
        }
        lotteryRecordsSearchTimer = setTimeout(() => {
            refreshLotteryRecords(1);
        }, 300);
    }

    function refreshLotteryRecords(page) {
        const formData = new FormData();
        formData.append('action', 'get_records');
        if (typeof page === 'number' && !Number.isNaN(page)) {
            currentRecordsPage = Math.max(1, page);
        }
        formData.append('page', String(currentRecordsPage));
        const keyword = document.getElementById('lottery-records-filter')?.value.trim() || '';
        formData.append('keyword', keyword);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('刷新失败：' + (data.message || '未知错误'));
                return;
            }
            const tbody = document.getElementById('lottery-records-body');
            const pagination = document.getElementById('lottery-records-pagination');
            const totalCount = document.getElementById('total-records-count');
            if (tbody) {
                tbody.innerHTML = data.rows_html || '';
            }
            if (pagination) {
                pagination.innerHTML = data.pagination_html || '';
            }
            if (totalCount) {
                totalCount.textContent = data.total_records;
            }
            applyLotteryRecordsFilter();
        })
        .catch(() => {
            alert('刷新失败，请重试');
        });
    }

    const userTimesSortState = { column: '', order: 'asc' };

    function getUserTimesRows() {
        const tbody = document.getElementById('user-times-body');
        if (!tbody) {
            return [];
        }
        return Array.from(tbody.querySelectorAll('tr'));
    }

    function updateUserTimesSortIcons(activeColumn, order) {
        const iconMap = {
            name: document.getElementById('sort-icon-name'),
            total: document.getElementById('sort-icon-total'),
            remaining: document.getElementById('sort-icon-remaining')
        };
        Object.keys(iconMap).forEach(key => {
            const icon = iconMap[key];
            if (!icon) {
                return;
            }
            if (key !== activeColumn) {
                icon.className = 'fa fa-sort text-xs';
                return;
            }
            icon.className = order === 'asc' ? 'fa fa-sort-up text-xs' : 'fa fa-sort-down text-xs';
        });
    }

    function sortUserTimesTable(column) {
        const tbody = document.getElementById('user-times-body');
        if (!tbody) {
            return;
        }
        const rows = getUserTimesRows();
        const nextOrder = userTimesSortState.column === column && userTimesSortState.order === 'asc' ? 'desc' : 'asc';
        userTimesSortState.column = column;
        userTimesSortState.order = nextOrder;
        rows.sort((a, b) => {
            let aValue = a.dataset[column] || '';
            let bValue = b.dataset[column] || '';
            if (column === 'name') {
                const result = aValue.localeCompare(bValue, 'zh-Hans-CN', { numeric: true, sensitivity: 'base' });
                return nextOrder === 'asc' ? result : -result;
            }
            const aNum = parseInt(aValue || '0', 10);
            const bNum = parseInt(bValue || '0', 10);
            return nextOrder === 'asc' ? aNum - bNum : bNum - aNum;
        });
        rows.forEach(row => tbody.appendChild(row));
        updateUserTimesSortIcons(column, nextOrder);
        applyUserTimesFilter();
    }

    function applyUserTimesFilter() {
        const input = document.getElementById('user-times-filter');
        const keyword = (input ? input.value : '').trim().toLowerCase();
        const rows = getUserTimesRows();
        rows.forEach(row => {
            const name = (row.dataset.name || '').toLowerCase();
            row.style.display = keyword === '' || name.includes(keyword) ? '' : 'none';
        });
    }

    function applyUsersFilter() {
        const input = document.getElementById('users-filter');
        const keyword = (input ? input.value : '').trim().toLowerCase();
        const tbody = document.getElementById('users-body');
        if (!tbody) {
            return;
        }
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.forEach(row => {
            const name = (row.dataset.name || '').toLowerCase();
            row.style.display = keyword === '' || name.includes(keyword) ? '' : 'none';
        });
    }

    function applyLotteryRecordsFilter() {
        const input = document.getElementById('lottery-records-filter');
        const keyword = (input ? input.value : '').trim().toLowerCase();
        const tbody = document.getElementById('lottery-records-body');
        if (!tbody) {
            return;
        }
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.forEach(row => {
            const name = (row.dataset.user || '').toLowerCase();
            row.style.display = keyword === '' || name.includes(keyword) ? '' : 'none';
        });
    }

    // 模态框控制
    function showModal(modalId) {
        document.getElementById(modalId).classList.remove('hidden');
    }

    function hideModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }

    function incrementTimes(inputId, delta) {
        const input = document.getElementById(inputId);
        const current = parseInt(input.value || '0', 10);
        input.value = (Number.isNaN(current) ? 0 : current) + delta;
        input.focus();
    }

    // 编辑项目
    function editProject(id, name) {
        document.getElementById('edit-project-id').value = id;
        document.getElementById('edit-project-name').value = name;
        showModal('edit-project-modal');
    }

    // 删除项目
    function deleteProject(id) {
        if (confirm('确定要删除这个项目吗？删除后相关的奖品和记录也会被删除。')) {
            saveScrollPosition();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=delete_project#projects';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // 编辑奖品
    function editPrize(id, name, quantity, probability) {
        document.getElementById('edit-prize-id').value = id;
        document.getElementById('edit-prize-name').value = name;
        document.getElementById('edit-prize-probability').value = probability;
        
        // 判断是否为无限数量（999999表示无限）
        const isUnlimited = quantity >= 999999;
        const quantityRadios = document.querySelectorAll('#edit-prize-modal input[name="quantity_type"]');
        const quantityInput = document.getElementById('edit-prize-quantity');
        const isUnlimitedInput = document.querySelector('#edit-prize-modal input[name="is_unlimited"]');
        
        if (isUnlimited) {
            quantityRadios[0].checked = true; // 选择无限数量
            quantityInput.style.display = 'none';
            quantityInput.required = false;
            isUnlimitedInput.value = '1';
        } else {
            quantityRadios[1].checked = true; // 选择限定数量
            quantityInput.style.display = 'block';
            quantityInput.required = true;
            quantityInput.value = quantity;
            isUnlimitedInput.value = '0';
        }
        
        showModal('edit-prize-modal');
    }

    // 切换数量输入框显示（添加奖品）
    function toggleQuantityInput(radio) {
        const quantityInput = radio.closest('form').querySelector('input[name="quantity"]');
        const isUnlimitedInput = radio.closest('form').querySelector('input[name="is_unlimited"]');
        
        if (radio.value === 'unlimited') {
            quantityInput.style.display = 'none';
            quantityInput.required = false;
            isUnlimitedInput.value = '1';
        } else {
            quantityInput.style.display = 'block';
            quantityInput.required = true;
            isUnlimitedInput.value = '0';
        }
    }

    // 切换数量输入框显示（编辑奖品）
    function toggleEditQuantityInput(radio) {
        const quantityInput = document.getElementById('edit-prize-quantity');
        const isUnlimitedInput = document.querySelector('#edit-prize-modal input[name="is_unlimited"]');
        
        if (radio.value === 'unlimited') {
            quantityInput.style.display = 'none';
            quantityInput.required = false;
            isUnlimitedInput.value = '1';
        } else {
            quantityInput.style.display = 'block';
            quantityInput.required = true;
            isUnlimitedInput.value = '0';
        }
    }

    // 删除奖品
    function deletePrize(id) {
        if (confirm('确定要删除这个奖品吗？')) {
            saveScrollPosition();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=delete_prize&project_id=<?php echo $selected_project_id; ?>#prizes';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function showUserDetail(userId) {
        const title = document.getElementById('user-detail-title');
        const body = document.getElementById('user-detail-body');
        if (title) {
            title.textContent = '用户详情';
        }
        if (body) {
            body.innerHTML = '<div class="py-6 text-center text-gray-500">加载中...</div>';
        }
        const formData = new FormData();
        formData.append('action', 'get_user_detail');
        formData.append('user_id', String(userId));
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('获取失败：' + (data.message || '未知错误'));
                return;
            }
            if (title) {
                title.textContent = data.title || '用户详情';
            }
            if (body) {
                body.innerHTML = data.html || '';
            }
            showModal('user-detail-modal');
        })
        .catch(() => {
            alert('获取失败，请重试');
        });
    }

    // 编辑用户
    function editUser(id, name, ip) {
        document.getElementById('edit-user-id').value = id;
        document.getElementById('edit-user-name').value = name;
        document.getElementById('edit-user-ip').value = ip;
        showModal('edit-user-modal');
    }

    // 删除用户
    function deleteUser(id) {
        if (confirm('确定要删除这个用户吗？删除后相关的抽奖记录也会被删除。')) {
            saveScrollPosition();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=delete_user#users';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // 编辑用户抽奖次数
    function editUserTimes(userId, projectId, times, isVisible) {
        document.getElementById('edit-times-user-id').value = userId;
        document.getElementById('edit-times-project-id').value = projectId;
        document.getElementById('edit-times-times').value = 0;
        document.getElementById('edit-times-visible').checked = isVisible == 1;
        showModal('edit-times-modal');
    }

    // 全选/取消全选用户
    function toggleAllUsers(selectAll) {
        const checkboxes = document.querySelectorAll('#batch-set-times-modal input[name="user_ids[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll;
        });
    }

    // 用户搜索过滤功能
    function filterUsers() {
        const searchInput = document.getElementById('user-search');
        const userSelect = document.getElementById('user-select');
        const searchTerm = searchInput.value.toLowerCase();
        
        // 获取所有选项
        const options = userSelect.querySelectorAll('option');
        
        options.forEach(option => {
            if (option.value === '') {
                // 保留默认选项
                option.style.display = '';
                return;
            }
            
            const userName = option.getAttribute('data-name').toLowerCase();
            if (userName.includes(searchTerm)) {
                option.style.display = '';
            } else {
                option.style.display = 'none';
            }
        });
    }

    window.addEventListener('load', function() {
        const hash = window.location.hash.substring(1);
        if (hash && ['projects', 'prizes', 'users', 'user-times', 'records'].includes(hash)) {
            showTab(hash);
        }
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', saveScrollPosition);
        });
        requestAnimationFrame(restoreScrollPosition);
    });

    // 概率调整相关函数
    // 更新滑动条值（当输入框改变时）
    function updateProbabilitySlider(input) {
        const prizeId = input.getAttribute('data-prize-id');
        const slider = document.querySelector(`.probability-slider[data-prize-id="${prizeId}"]`);
        slider.value = input.value;
        updateTotalProbability();
    }

    // 更新输入框值（当滑动条改变时）
    function updateProbabilityInput(slider) {
        const prizeId = slider.getAttribute('data-prize-id');
        const input = document.querySelector(`.probability-input[data-prize-id="${prizeId}"]`);
        input.value = slider.value;
        updateTotalProbability();
    }

    // 计算并更新总概率显示
    function updateTotalProbability() {
        const inputs = document.querySelectorAll('.probability-input');
        let total = 0;
        inputs.forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        document.getElementById('new-total-probability').textContent = total.toFixed(2) + '%';
        
        // 根据总概率改变提示颜色
        const tipElement = document.getElementById('new-total-probability').parentElement.parentElement;
        tipElement.className = 'mb-4 p-3 border rounded';
        if (total > 100) {
            tipElement.classList.add('bg-red-50', 'border-red-200');
            tipElement.querySelector('p').className = 'text-sm text-red-800';
        } else if (total < 100) {
            tipElement.classList.add('bg-yellow-50', 'border-yellow-200');
            tipElement.querySelector('p').className = 'text-sm text-yellow-800';
        } else {
            tipElement.classList.add('bg-green-50', 'border-green-200');
            tipElement.querySelector('p').className = 'text-sm text-green-800';
        }
    }

    // 重置所有概率到原始值
    function resetProbabilities() {
        const inputs = document.querySelectorAll('.probability-input');
        const sliders = document.querySelectorAll('.probability-slider');
        
        inputs.forEach((input, index) => {
            const originalValue = input.defaultValue;
            input.value = originalValue;
            sliders[index].value = originalValue;
        });
        updateTotalProbability();
    }

    // 平均分配概率
    function averageProbabilities() {
        const inputs = document.querySelectorAll('.probability-input');
        const sliders = document.querySelectorAll('.probability-slider');
        const averageValue = (100 / inputs.length).toFixed(2);
        
        inputs.forEach((input, index) => {
            input.value = averageValue;
            sliders[index].value = averageValue;
        });
        updateTotalProbability();
    }

    // 保存概率调整
    function saveProbabilities() {
        const inputs = document.querySelectorAll('.probability-input');
        const probabilities = {};
        
        inputs.forEach(input => {
            const prizeId = input.getAttribute('data-prize-id');
            probabilities[prizeId] = parseFloat(input.value) || 0;
        });

        // 发送AJAX请求保存概率
        const formData = new FormData();
        formData.append('action', 'update_probabilities');
        formData.append('project_id', '<?php echo $selected_project_id; ?>');
        formData.append('probabilities', JSON.stringify(probabilities));

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('概率调整成功！');
                hideModal('probability-adjust-modal');
                
                // 局部更新概率显示，而不是刷新整个页面
                updateProbabilityDisplay();
            } else {
                alert('保存失败：' + (data.message || '未知错误'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('保存失败，请重试');
        });
    }

    // 局部更新概率显示函数
    function updateProbabilityDisplay() {
        // 重新获取当前项目的奖品数据并更新显示
        const projectId = '<?php echo $selected_project_id; ?>';
        
        // 发送AJAX请求获取最新的奖品数据
        const formData = new FormData();
        formData.append('action', 'get_prizes_data');
        formData.append('project_id', projectId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 更新奖品表格中的概率显示
                data.prizes.forEach(prize => {
                    const probabilityCell = document.querySelector(`tr[data-prize-id="${prize.id}"] .probability-cell`);
                    if (probabilityCell) {
                        probabilityCell.textContent = prize.probability + '%';
                    }
                });
                
                // 更新总概率显示
                const totalProbabilityCell = document.querySelector('.total-probability-cell');
                if (totalProbabilityCell) {
                    totalProbabilityCell.textContent = data.total_probability + '%';
                }
                
                // 更新概率调整弹窗中的当前概率显示
                const currentTotalSpan = document.getElementById('current-total-probability');
                if (currentTotalSpan) {
                    currentTotalSpan.textContent = data.total_probability + '%';
                }
            }
        })
        .catch(error => {
            console.error('更新概率显示失败:', error);
            // 如果局部更新失败，则刷新页面
            location.reload();
        });
    }

    // 初始化概率调整弹窗
    document.addEventListener('DOMContentLoaded', function() {
        // 为所有概率输入框和滑动条添加事件监听器
        document.querySelectorAll('.probability-input').forEach(input => {
            input.addEventListener('input', function() {
                updateProbabilitySlider(this);
            });
        });
        
        document.querySelectorAll('.probability-slider').forEach(slider => {
            slider.addEventListener('input', function() {
                updateProbabilityInput(this);
            });
        });
        
        // 初始化总概率显示
        updateTotalProbability();
    });
    </script>
</body>
</html>
