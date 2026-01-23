<?php
session_start();
require_once 'config.php';

// 管理员密码（实际使用时应该存储在数据库中并加密）
define('ADMIN_PASSWORD', 'admin123');

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // 处理登录
    if ($_POST && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $login_error = '密码错误';
        }
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

// 处理各种操作
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 处理退出登录
if ($action === 'logout') {
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
                $message = '项目添加成功';
                break;
                
            case 'edit_project':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                if (empty($name)) {
                    throw new Exception('项目名称不能为空');
                }
                $stmt = $pdo->prepare("UPDATE projects SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                $message = '项目更新成功';
                break;
                
            case 'delete_project':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->execute([$id]);
                $message = '项目删除成功';
                break;
                
            case 'add_prize':
                $project_id = (int)$_POST['project_id'];
                $name = trim($_POST['name']);
                $quantity = (int)$_POST['quantity'];
                $probability = (float)$_POST['probability'];
                
                if (empty($name)) {
                    throw new Exception('奖品名称不能为空');
                }
                if ($quantity <= 0) {
                    throw new Exception('奖品数量必须大于0');
                }
                if ($probability < 0 || $probability > 100) {
                    throw new Exception('中奖概率必须在0-100之间');
                }
                
                $stmt = $pdo->prepare("INSERT INTO prizes (project_id, name, total_quantity, remaining_quantity, probability) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $name, $quantity, $quantity, $probability]);
                $message = '奖品添加成功';
                break;
                
            case 'edit_prize':
                $id = (int)$_POST['id'];
                $name = trim($_POST['name']);
                $quantity = (int)$_POST['quantity'];
                $probability = (float)$_POST['probability'];
                
                if (empty($name)) {
                    throw new Exception('奖品名称不能为空');
                }
                if ($quantity < 0) {
                    throw new Exception('奖品数量不能小于0');
                }
                if ($probability < 0 || $probability > 100) {
                    throw new Exception('中奖概率必须在0-100之间');
                }
                
                $stmt = $pdo->prepare("UPDATE prizes SET name = ?, remaining_quantity = ?, probability = ? WHERE id = ?");
                $stmt->execute([$name, $quantity, $probability, $id]);
                $message = '奖品更新成功';
                break;
                
            case 'delete_prize':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM prizes WHERE id = ?");
                $stmt->execute([$id]);
                $message = '奖品删除成功';
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
                $message = '用户添加成功';
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
                $message = '用户更新成功';
                break;
                
            case 'delete_user':
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = '用户删除成功';
                break;
                
            case 'set_user_times':
                $user_id = (int)$_POST['user_id'];
                $project_id = (int)$_POST['project_id'];
                $times = (int)$_POST['times'];
                $is_visible = isset($_POST['is_visible']) ? 1 : 0;
                
                if ($times < 0) {
                    throw new Exception('抽奖次数不能小于0');
                }
                
                // 检查记录是否存在
                $stmt = $pdo->prepare("SELECT id FROM user_project_times WHERE user_id = ? AND project_id = ?");
                $stmt->execute([$user_id, $project_id]);
                $exists = $stmt->fetch();
                
                if ($exists) {
                    $stmt = $pdo->prepare("UPDATE user_project_times SET total_times = total_times + ?, remaining_times = remaining_times + ?, is_visible = ? WHERE user_id = ? AND project_id = ?");
                    $stmt->execute([$times, $times, $is_visible, $user_id, $project_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO user_project_times (user_id, project_id, total_times, remaining_times, is_visible) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $project_id, $times, $times, $is_visible]);
                }
                
                $message = '用户抽奖次数设置成功';
                break;

            case 'get_records':
                $records_per_page = 20;
                $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
                $offset = ($page - 1) * $records_per_page;
                
                $total_records_stmt = $pdo->query("SELECT COUNT(*) FROM lottery_records");
                $total_records = $total_records_stmt->fetchColumn();
                $total_pages = ceil($total_records / $records_per_page);
                
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
                        <tr class="bg-white border-b hover:bg-gray-50">
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
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = '数据库操作失败：' . $e->getMessage();
    }
}

// 获取数据
$projects = $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();

// 获取奖品（如果选择了项目）
$selected_project_id = $_GET['project_id'] ?? ($projects[0]['id'] ?? 0);
$prizes = [];
if ($selected_project_id) {
    $stmt = $pdo->prepare("SELECT * FROM prizes WHERE project_id = ? ORDER BY sort_order, id");
    $stmt->execute([$selected_project_id]);
    $prizes = $stmt->fetchAll();
}

// 获取用户项目次数
$user_project_times = [];
if ($selected_project_id) {
    $stmt = $pdo->prepare("
        SELECT upt.*, u.name as user_name 
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
                    <i class="fa fa-cogs text-blue-500 mr-2"></i>
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
                    <button onclick="showModal('add-prize-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-plus mr-2"></i>添加奖品
                    </button>
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
                            <tr class="bg-white border-b">
                                <td class="px-6 py-4"><?php echo $prize['id']; ?></td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($prize['name']); ?></td>
                                <td class="px-6 py-4"><?php echo $prize['remaining_quantity']; ?></td>
                                <td class="px-6 py-4"><?php echo $prize['probability']; ?>%</td>
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
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 用户管理 -->
            <div id="content-users" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">用户管理</h2>
                    <button onclick="showModal('add-user-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-plus mr-2"></i>添加用户
                    </button>
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
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="bg-white border-b">
                                <td class="px-6 py-4"><?php echo $user['id']; ?></td>
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($user['name']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($user['ip_address']); ?></td>
                                <td class="px-6 py-4"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                <td class="px-6 py-4">
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
                    <button onclick="showModal('set-times-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-plus mr-2"></i>分配次数
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">用户姓名</th>
                                <th class="px-6 py-3">总次数</th>
                                <th class="px-6 py-3">剩余次数</th>
                                <th class="px-6 py-3">是否显示</th>
                                <th class="px-6 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_project_times as $upt): ?>
                            <tr class="bg-white border-b">
                                <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($upt['user_name']); ?></td>
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
                                <tr class="bg-white border-b hover:bg-gray-50">
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
            <form method="POST" action="?action=add_project">
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
            <form method="POST" action="?action=edit_project">
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
                        增加
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 添加奖品模态框 -->
    <div id="add-prize-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">添加奖品</h3>
            <form method="POST" action="?action=add_prize">
                <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">奖品名称</label>
                    <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">奖品数量</label>
                    <input type="number" name="quantity" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
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
            <form method="POST" action="?action=edit_prize">
                <input type="hidden" name="id" id="edit-prize-id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">奖品名称</label>
                    <input type="text" name="name" id="edit-prize-name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">剩余数量</label>
                    <input type="number" name="quantity" id="edit-prize-quantity" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
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
            <form method="POST" action="?action=add_user">
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
            <form method="POST" action="?action=edit_user">
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

    <!-- 设置抽奖次数模态框 -->
    <div id="set-times-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4">增加抽奖次数</h3>
            <form method="POST" action="?action=set_user_times">
                <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">选择用户</label>
                    <select name="user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <option value="">请选择用户</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">增加抽奖次数</label>
                    <input type="number" name="times" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
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
            <form method="POST" action="?action=set_user_times">
                <input type="hidden" name="user_id" id="edit-times-user-id">
                <input type="hidden" name="project_id" id="edit-times-project-id">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">增加抽奖次数</label>
                    <input type="number" name="times" id="edit-times-times" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
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
        sessionStorage.setItem('admin1_scroll_y', String(window.scrollY));
        sessionStorage.setItem('admin1_scroll_hash', window.location.hash || '');
    }

    function restoreScrollPosition() {
        const savedY = sessionStorage.getItem('admin1_scroll_y');
        if (savedY !== null) {
            const y = parseInt(savedY, 10);
            if (!Number.isNaN(y)) {
                window.scrollTo(0, y);
            }
            sessionStorage.removeItem('admin1_scroll_y');
            sessionStorage.removeItem('admin1_scroll_hash');
        }
    }

    function refreshLotteryRecords() {
        const formData = new FormData();
        formData.append('action', 'get_records');
        formData.append('page', '<?php echo (int)$page; ?>');

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
        })
        .catch(() => {
            alert('刷新失败，请重试');
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
        input.value = Math.max(0, (Number.isNaN(current) ? 0 : current) + delta);
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
            form.action = '?action=delete_project';
            
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
        document.getElementById('edit-prize-quantity').value = quantity;
        document.getElementById('edit-prize-probability').value = probability;
        showModal('edit-prize-modal');
    }

    // 删除奖品
    function deletePrize(id) {
        if (confirm('确定要删除这个奖品吗？')) {
            saveScrollPosition();
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=delete_prize';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'id';
            input.value = id;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
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
            form.action = '?action=delete_user';
            
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
    </script>
</body>
</html>
