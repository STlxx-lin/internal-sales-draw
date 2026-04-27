<?php
session_start();
require_once 'config.php';

// 管理员密码
define('ADMIN_PASSWORD', 'admin123');

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($_POST && isset($_POST['password'])) {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: expense.php');
            exit;
        } else {
            $login_error = '密码错误';
        }
    }
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>管理员登录 - 报销管理</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100 min-h-screen flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-md w-96">
                <h1 class="text-2xl font-bold text-center mb-6">报销管理登录</h1>
                <?php if (isset($login_error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($login_error) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="password">管理员密码</label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               id="password" name="password" type="password" placeholder="请输入管理员密码" required>
                    </div>
                    <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full" type="submit">登录</button>
                </form>
                <div class="mt-4 text-center">
                    <a href="index.php" class="text-blue-500 hover:text-blue-700">返回首页</a>
                    <span class="mx-2">|</span>
                    <a href="admin.php" class="text-blue-500 hover:text-blue-700">管理后台</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// 处理后操作
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$error = '';

// 权限检查：判断当前用户是否为授权用户（管理员角色）
$is_authorized = true; // 已登录的管理员即为授权用户

if ($_POST) {
    try {
        switch ($action) {
            case 'add':
                // 添加报销记录
                $project_id = (int)$_POST['project_id'];
                $expense_name = trim($_POST['expense_name']);
                $amount = (float)$_POST['amount'];
                $reason = trim($_POST['reason']);
                $applicant = trim($_POST['applicant']);
                $status = $_POST['status'] ?? 'pending';

                if (empty($expense_name)) throw new Exception('报销活动名称不能为空');
                if ($amount <= 0) throw new Exception('报销金额必须大于0');
                if (empty($applicant)) throw new Exception('申请人不能为空');

                // 处理附件上传
                $attachment = '';
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $filename = 'expense_' . time() . '_' . uniqid() . '.' . $ext;
                    $destination = __DIR__ . '/uploads/expenses/' . $filename;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
                        $attachment = 'uploads/expenses/' . $filename;
                    }
                }

                $stmt = $pdo->prepare("INSERT INTO expense_records (project_id, expense_name, amount, reason, status, applicant, applied_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$project_id, $expense_name, $amount, $reason, $status, $applicant]);
                header('Location: expense.php?message=' . urlencode('报销记录添加成功'));
                exit;
                break;

            case 'edit':
                // 编辑报销记录 - 后天编辑权限控制
                $id = (int)$_POST['id'];
                $new_amount = (float)$_POST['amount'];
                $new_reason = trim($_POST['reason']);
                $edit_reason = trim($_POST['edit_reason'] ?? '');

                if ($new_amount <= 0) throw new Exception('报销金额必须大于0');

                // 获取当前记录数据用于权限判断
                $stmt = $pdo->prepare("SELECT * FROM expense_records WHERE id = ?");
                $stmt->execute([$id]);
                $record = $stmt->fetch();

                if (!$record) throw new Exception('报销记录不存在');

                // 后天编辑权限控制：当前日期必须在申请日期的两天后才能编辑
                $applied_date = strtotime($record['applied_at']);
                $two_days_after = strtotime('+2 days', $applied_date);
                $now = time();

                if ($now < $two_days_after) {
                    throw new Exception('该报销记录需在申请日期两天后才能编辑（可编辑日期：' . date('Y-m-d H:i', $two_days_after) . '起）');
                }

                // 权限控制：只有未审批(pending)或被拒绝(rejected)的状态可以编辑
                if (!in_array($record['status'], ['pending', 'rejected'])) {
                    throw new Exception('该报销记录当前状态不允许编辑，仅未审批或被拒绝的记录可编辑');
                }

                if (!$is_authorized) throw new Exception('您没有编辑权限');

                // 保存修改前的数据
                $old_data = json_encode([
                    'amount' => $record['amount'],
                    'reason' => $record['reason'],
                    'attachment' => $record['attachment']
                ], JSON_UNESCAPED_UNICODE);

                // 处理新附件上传
                $attachment = $record['attachment']; // 默认保留原附件
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                    $filename = 'expense_' . time() . '_' . uniqid() . '.' . $ext;
                    $destination = __DIR__ . '/uploads/expenses/' . $filename;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destination)) {
                        $attachment = 'uploads/expenses/' . $filename;
                    }
                }

                // 更新报销记录
                $stmt = $pdo->prepare("UPDATE expense_records SET amount = ?, reason = ?, attachment = ? WHERE id = ?");
                $stmt->execute([$new_amount, $new_reason, $attachment, $id]);

                // 记录修改后的数据
                $new_data = json_encode([
                    'amount' => $new_amount,
                    'reason' => $new_reason,
                    'attachment' => $attachment
                ], JSON_UNESCAPED_UNICODE);

                // 保存修改历史
                $editor = '管理员'; // 实际项目中应从session获取用户名
                $stmt = $pdo->prepare("INSERT INTO expense_edit_history (expense_id, editor, edit_reason, old_data, new_data) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id, $editor, $edit_reason, $old_data, $new_data]);

                header('Location: expense.php?message=' . urlencode('报销记录编辑成功'));
                exit;
                break;

            case 'approve':
                // 审批报销记录
                $id = (int)$_POST['id'];
                $new_status = $_POST['status'] ?? 'approved';
                $approver = trim($_POST['approver'] ?? '管理员');

                $stmt = $pdo->prepare("UPDATE expense_records SET status = ?, approver = ?, approved_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $approver, $id]);
                header('Location: expense.php?message=' . urlencode('审批操作成功'));
                exit;
                break;

            case 'delete':
                // 删除报销记录
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("DELETE FROM expense_records WHERE id = ?");
                $stmt->execute([$id]);
                header('Location: expense.php?message=' . urlencode('报销记录删除成功'));
                exit;
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        $error = '数据库操作失败：' . $e->getMessage();
    }
}

// 从URL获取消息
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// === 数据获取与筛选排序逻辑 ===

// 筛选参数
$filter_status = $_GET['status'] ?? '';
$filter_project = $_GET['project_id'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_amount_min = $_GET['amount_min'] ?? '';
$filter_amount_max = $_GET['amount_max'] ?? '';
$search_keyword = $_GET['search'] ?? '';

// 排序参数
$sort_field = $_GET['sort'] ?? 'applied_at';
$sort_order = $_GET['order'] ?? 'desc';
$allowed_sorts = ['id', 'amount', 'applied_at', 'approved_at', 'status', 'expense_name'];
if (!in_array($sort_field, $allowed_sorts)) $sort_field = 'applied_at';
if (!in_array($sort_order, ['asc', 'desc'])) $sort_order = 'desc';

// 构建查询
$where_clauses = [];
$params = [];

if ($filter_status !== '') {
    $where_clauses[] = "er.status = ?";
    $params[] = $filter_status;
}

if ($filter_project !== '') {
    $where_clauses[] = "er.project_id = ?";
    $params[] = (int)$filter_project;
}

if ($filter_date_from !== '') {
    $where_clauses[] = "er.applied_at >= ?";
    $params[] = $filter_date_from . ' 00:00:00';
}

if ($filter_date_to !== '') {
    $where_clauses[] = "er.applied_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}

if ($filter_amount_min !== '') {
    $where_clauses[] = "er.amount >= ?";
    $params[] = (float)$filter_amount_min;
}

if ($filter_amount_max !== '') {
    $where_clauses[] = "er.amount <= ?";
    $params[] = (float)$filter_amount_max;
}

if ($search_keyword !== '') {
    $where_clauses[] = "(er.expense_name LIKE ? OR er.reason LIKE ? OR er.applicant LIKE ?)";
    $kw = '%' . $search_keyword . '%';
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// 查询报销记录总数
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_records er {$where_sql}");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();

// 分页
$records_per_page = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($total_records / $records_per_page);

// 查询报销记录列表（关联项目名称）
$query_sql = "
    SELECT er.*, p.name as project_name
    FROM expense_records er
    LEFT JOIN projects p ON er.project_id = p.id
    {$where_sql}
    ORDER BY er.{$sort_field} {$sort_order}
    LIMIT {$records_per_page} OFFSET {$offset}
";
$stmt = $pdo->prepare($query_sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// 获取所有项目用于筛选下拉
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY id DESC")->fetchAll();

// 构建筛选参数URL片段
function build_filter_url($extra = [], $remove_page = false) {
    $params = $_GET;
    if ($remove_page) unset($params['page']);
    foreach ($extra as $key => $val) {
        if ($val === null) {
            unset($params[$key]);
        } else {
            $params[$key] = $val;
        }
    }
    return 'expense.php?' . http_build_query($params);
}

// 排序链接辅助函数
function sort_url($field) {
    global $sort_field, $sort_order;
    $new_order = ($sort_field === $field && $sort_order === 'asc') ? 'desc' : 'asc';
    $params = $_GET;
    $params['sort'] = $field;
    $params['order'] = $new_order;
    return 'expense.php?' . http_build_query($params);
}

// 检查当前记录是否可编辑（后天规则）
function can_edit($record) {
    $applied_date = strtotime($record['applied_at']);
    $two_days_after = strtotime('+2 days', $applied_date);
    $now = time();
    // 时间条件和状态条件都满足时才可编辑
    return ($now >= $two_days_after) && in_array($record['status'], ['pending', 'rejected']);
}

// 计算后天可编辑日期
function get_editable_date($record) {
    $applied_date = strtotime($record['applied_at']);
    return date('Y-m-d H:i', strtotime('+2 days', $applied_date));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>报销管理 - <?php echo SITE_NAME; ?></title>
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
                <div class="flex space-x-3">
                    <button onclick="showModal('add-modal')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-plus mr-2"></i>新增报销记录
                    </button>
                    <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-home mr-2"></i>返回首页
                    </a>
                </div>
            </div>
        </div>

        <!-- 标签页导航 -->
        <div class="bg-white rounded-lg shadow-lg mb-8">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6">
                    <button onclick="window.location.href='admin.php#projects'" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-project-diagram mr-2"></i>项目管理
                    </button>
                    <button onclick="window.location.href='admin.php#prizes'" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-gift mr-2"></i>奖品管理
                    </button>
                    <button onclick="window.location.href='admin.php#users'" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-users mr-2"></i>用户管理
                    </button>
                    <button onclick="window.location.href='admin.php#user-times'" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-clock mr-2"></i>次数分配
                    </button>
                    <button onclick="window.location.href='admin.php#records'" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-history mr-2"></i>抽奖记录
                    </button>
                    <button onclick="window.location.href='expense.php'" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                        <i class="fa fa-money-bill-wave mr-2"></i>报销管理
                    </button>
                    <button onclick="logoutAdmin()" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fa fa-sign-out-alt mr-2"></i>退出登录
                    </button>
                </nav>
            </div>
        </div>

        <!-- 消息提示 -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" id="message-banner">
                <i class="fa fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button onclick="this.parentElement.remove()" class="float-right"><i class="fa fa-times"></i></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" id="error-banner">
                <i class="fa fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button onclick="this.parentElement.remove()" class="float-right"><i class="fa fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- 筛选区域 -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-700"><i class="fa fa-filter mr-2 text-blue-500"></i>筛选条件</h3>
                <a href="expense.php" class="text-sm text-blue-500 hover:text-blue-700"><i class="fa fa-refresh mr-1"></i>清除筛选</a>
            </div>
            <form method="GET" action="expense.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 关键词搜索 -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">关键词搜索</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="搜索活动名称/事由/申请人" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <!-- 项目筛选 -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">抽奖项目</label>
                    <select name="project_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">全部项目</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>" <?php echo $filter_project == $proj['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($proj['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- 状态筛选 -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">报销状态</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">全部状态</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>待审批</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>已通过</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>已拒绝</option>
                    </select>
                </div>
                <!-- 日期范围 -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">申请日期（起）</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">申请日期（止）</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <!-- 金额范围 -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">金额（最低）</label>
                    <input type="number" name="amount_min" value="<?php echo htmlspecialchars($filter_amount_min); ?>" placeholder="0" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">金额（最高）</label>
                    <input type="number" name="amount_max" value="<?php echo htmlspecialchars($filter_amount_max); ?>" placeholder="无上限" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <!-- 排序 -->
                <div>
                    <label class="block text-xs text-gray-500 mb-1">排序字段</label>
                    <select name="sort" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="applied_at" <?php echo $sort_field === 'applied_at' ? 'selected' : ''; ?>>申请日期</option>
                        <option value="amount" <?php echo $sort_field === 'amount' ? 'selected' : ''; ?>>报销金额</option>
                        <option value="status" <?php echo $sort_field === 'status' ? 'selected' : ''; ?>>报销状态</option>
                        <option value="expense_name" <?php echo $sort_field === 'expense_name' ? 'selected' : ''; ?>>活动名称</option>
                        <option value="id" <?php echo $sort_field === 'id' ? 'selected' : ''; ?>>记录ID</option>
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition text-sm">
                        <i class="fa fa-search mr-1"></i>查询
                    </button>
                </div>
            </form>
        </div>

        <!-- 数据统计概览 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-sm text-gray-500">总记录数</div>
                <div class="text-2xl font-bold text-blue-600"><?php echo $total_records; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-sm text-gray-500">待审批</div>
                <div class="text-2xl font-bold text-yellow-600">
                    <?php
                    $cnt = $pdo->query("SELECT COUNT(*) FROM expense_records WHERE status='pending'")->fetchColumn();
                    echo $cnt;
                    ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-sm text-gray-500">已通过</div>
                <div class="text-2xl font-bold text-green-600">
                    <?php
                    $cnt = $pdo->query("SELECT COUNT(*) FROM expense_records WHERE status='approved'")->fetchColumn();
                    echo $cnt;
                    ?>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-sm text-gray-500">报销总额</div>
                <div class="text-2xl font-bold text-red-600">
                    <?php
                    $total_amount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expense_records")->fetchColumn();
                    echo '¥' . number_format($total_amount, 2);
                    ?>
                </div>
            </div>
        </div>

        <!-- 报销记录列表 -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">
                                <a href="<?php echo sort_url('expense_name'); ?>" class="hover:text-blue-600">
                                    活动名称 <?php if($sort_field==='expense_name'): ?><i class="fa fa-sort-<?php echo $sort_order==='asc'?'up':'down'; ?>"></i><?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3">所属项目</th>
                            <th class="px-4 py-3">
                                <a href="<?php echo sort_url('amount'); ?>" class="hover:text-blue-600">
                                    报销金额 <?php if($sort_field==='amount'): ?><i class="fa fa-sort-<?php echo $sort_order==='asc'?'up':'down'; ?>"></i><?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3">
                                <a href="<?php echo sort_url('status'); ?>" class="hover:text-blue-600">
                                    报销状态 <?php if($sort_field==='status'): ?><i class="fa fa-sort-<?php echo $sort_order==='asc'?'up':'down'; ?>"></i><?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3">申请人</th>
                            <th class="px-4 py-3">审批人</th>
                            <th class="px-4 py-3">
                                <a href="<?php echo sort_url('applied_at'); ?>" class="hover:text-blue-600">
                                    申请日期 <?php if($sort_field==='applied_at'): ?><i class="fa fa-sort-<?php echo $sort_order==='asc'?'up':'down'; ?>"></i><?php endif; ?>
                                </a>
                            </th>
                            <th class="px-4 py-3">可编辑时间</th>
                            <th class="px-4 py-3">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                                <i class="fa fa-inbox text-5xl mb-3 block"></i>
                                <div class="text-lg">暂无报销记录</div>
                                <div class="text-sm mt-1">点击"新增报销记录"添加数据</div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $exp): ?>
                            <tr class="bg-white border-b hover:bg-gray-50 transition">
                                <td class="px-4 py-3"><?php echo $exp['id']; ?></td>
                                <td class="px-4 py-3 font-medium">
                                    <?php echo htmlspecialchars($exp['expense_name']); ?>
                                    <?php if ($exp['attachment']): ?>
                                        <a href="<?php echo htmlspecialchars($exp['attachment']); ?>" target="_blank" class="text-blue-500 ml-1" title="查看附件"><i class="fa fa-paperclip"></i></a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($exp['project_name'] ?? '—'); ?></td>
                                <td class="px-4 py-3 font-semibold text-red-600">¥<?php echo number_format($exp['amount'], 2); ?></td>
                                <td class="px-4 py-3">
                                    <?php
                                    $status_map = [
                                        'pending' => ['bg-yellow-100 text-yellow-800', '待审批'],
                                        'approved' => ['bg-green-100 text-green-800', '已通过'],
                                        'rejected' => ['bg-red-100 text-red-800', '已拒绝'],
                                    ];
                                    $s = $status_map[$exp['status']] ?? ['bg-gray-100 text-gray-800', $exp['status']];
                                    ?>
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $s[0]; ?>"><?php echo $s[1]; ?></span>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($exp['applicant']); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($exp['approver'] ?? '—'); ?></td>
                                <td class="px-4 py-3 text-gray-600"><?php echo date('Y-m-d', strtotime($exp['applied_at'])); ?></td>
                                <td class="px-4 py-3">
                                    <?php $editable_date = get_editable_date($exp); ?>
                                    <?php if (can_edit($exp)): ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700" title="现在可以编辑">可编辑</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-500" title="需等到 <?php echo $editable_date; ?> 之后"><?php echo $editable_date; ?>起</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        <?php if (can_edit($exp)): ?>
                                            <button onclick="openEdit(<?php echo $exp['id']; ?>, '<?php echo htmlspecialchars($exp['expense_name'], ENT_QUOTES); ?>', <?php echo $exp['amount']; ?>, '<?php echo htmlspecialchars($exp['reason'] ?? '', ENT_QUOTES); ?>')" class="text-blue-600 hover:text-blue-900" title="编辑">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-300 cursor-not-allowed" title="暂不可编辑"><i class="fa fa-edit"></i></span>
                                        <?php endif; ?>
                                        <button onclick="viewHistory(<?php echo $exp['id']; ?>)" class="text-purple-600 hover:text-purple-900" title="修改历史">
                                            <i class="fa fa-history"></i>
                                        </button>
                                        <?php if ($exp['status'] === 'pending'): ?>
                                        <button onclick="openApprove(<?php echo $exp['id']; ?>, 'approved')" class="text-green-600 hover:text-green-900" title="通过">
                                            <i class="fa fa-check-circle"></i>
                                        </button>
                                        <button onclick="openApprove(<?php echo $exp['id']; ?>, 'rejected')" class="text-red-600 hover:text-red-900" title="拒绝">
                                            <i class="fa fa-times-circle"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="deleteExpense(<?php echo $exp['id']; ?>)" class="text-gray-600 hover:text-gray-900" title="删除">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center items-center py-4 border-t border-gray-200 space-x-1">
                <?php if ($page > 1): ?>
                    <a href="<?php echo build_filter_url(['page' => $page - 1]); ?>" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fa fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="<?php echo build_filter_url(['page' => $i]); ?>" class="px-3 py-2 text-sm <?php echo $i == $page ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo build_filter_url(['page' => $page + 1]); ?>" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        <i class="fa fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
                <span class="text-sm text-gray-500 ml-4">共 <?php echo $total_records; ?> 条 / <?php echo $total_pages; ?> 页</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============ 模态框 ============ -->

    <!-- 新增报销记录 -->
    <div id="add-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-bold mb-4"><i class="fa fa-plus-circle text-green-500 mr-2"></i>新增报销记录</h3>
            <form method="POST" action="expense.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">所属项目</label>
                    <select name="project_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                        <option value="">请选择抽奖项目</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj['id']; ?>"><?php echo htmlspecialchars($proj['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">报销活动名称</label>
                    <input type="text" name="expense_name" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="如：2025年会抽奖活动报销" required>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">报销金额（元）</label>
                    <input type="number" name="amount" min="0.01" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="0.00" required>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">报销事由</label>
                    <textarea name="reason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="请详细描述报销事由"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">申请人</label>
                    <input type="text" name="applicant" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="申请人姓名" required>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">初始状态</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="pending">待审批</option>
                        <option value="approved">已通过</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">附件（可选）</label>
                    <input type="file" name="attachment" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('add-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">取消</button>
                    <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">提交报销</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑报销记录 -->
    <div id="edit-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-lg font-bold mb-4"><i class="fa fa-edit text-blue-500 mr-2"></i>编辑报销记录</h3>
            <div class="bg-yellow-50 border border-yellow-200 rounded p-2 mb-3 text-xs text-yellow-700">
                <i class="fa fa-info-circle mr-1"></i>仅可在申请日期两天后编辑未审批或被拒绝的记录，修改将保存历史记录。
            </div>
            <form method="POST" action="expense.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">活动名称</label>
                    <input type="text" id="edit-name" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" readonly>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">报销金额（元）</label>
                    <input type="number" name="amount" id="edit-amount" min="0.01" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">报销事由</label>
                    <textarea name="reason" id="edit-reason" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">新附件（可选）</label>
                    <input type="file" name="attachment" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">修改原因（必填）</label>
                    <input type="text" name="edit_reason" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="请说明修改原因，以供后续追溯" required>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('edit-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">取消</button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">保存修改</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 审批模态框 -->
    <div id="approve-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-bold mb-4" id="approve-title">审批操作</h3>
            <form method="POST" action="expense.php">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="approve-id">
                <input type="hidden" name="status" id="approve-status">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">审批人</label>
                    <input type="text" name="approver" class="w-full border border-gray-300 rounded-lg px-3 py-2" value="管理员" required>
                </div>
                <div class="mb-4" id="approve-message"></div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="hideModal('approve-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">取消</button>
                    <button type="submit" id="approve-submit-btn" class="px-4 py-2 text-white rounded-lg">确认</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 修改历史模态框 -->
    <div id="history-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[80vh] overflow-y-auto">
            <h3 class="text-lg font-bold mb-4"><i class="fa fa-history text-purple-500 mr-2"></i>修改历史记录</h3>
            <div id="history-content">
                <div class="text-center text-gray-400 py-8">
                    <i class="fa fa-spinner fa-spin text-2xl"></i>
                    <p class="mt-2">加载中...</p>
                </div>
            </div>
            <div class="flex justify-end mt-4">
                <button type="button" onclick="hideModal('history-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">关闭</button>
            </div>
        </div>
    </div>

    <!-- 修改详情模态框 -->
    <div id="detail-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[80vh] overflow-y-auto">
            <h3 class="text-lg font-bold mb-4"><i class="fa fa-search text-blue-500 mr-2"></i>修改详情对比</h3>
            <div id="detail-content"></div>
            <div class="flex justify-end mt-4">
                <button type="button" onclick="hideModal('detail-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">关闭</button>
            </div>
        </div>
    </div>

    <script>
    // 模态框控制
    function showModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function hideModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = ''; }

    // 点击遮罩关闭
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) hideModal(this.id);
        });
    });

    // 退出登录
    function logoutAdmin() {
        if (confirm('确定要退出登录吗？')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'admin.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'logout';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // 打开编辑模态框
    function openEdit(id, name, amount, reason) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-amount').value = amount;
        document.getElementById('edit-reason').value = reason || '';
        showModal('edit-modal');
    }

    // 打开审批模态框
    function openApprove(id, status) {
        document.getElementById('approve-id').value = id;
        document.getElementById('approve-status').value = status;
        const title = document.getElementById('approve-title');
        const msg = document.getElementById('approve-message');
        const btn = document.getElementById('approve-submit-btn');

        if (status === 'approved') {
            title.innerHTML = '<i class="fa fa-check-circle text-green-500 mr-2"></i>审批通过';
            msg.innerHTML = '<p class="text-sm text-green-600">确认将该报销记录标记为"已通过"吗？</p>';
            btn.className = 'px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600';
            btn.textContent = '确认通过';
        } else {
            title.innerHTML = '<i class="fa fa-times-circle text-red-500 mr-2"></i>审批拒绝';
            msg.innerHTML = '<p class="text-sm text-red-600">确认拒绝该报销记录吗？拒绝后仍可编辑。</p>';
            btn.className = 'px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600';
            btn.textContent = '确认拒绝';
        }
        showModal('approve-modal');
    }

    // 删除报销记录
    function deleteExpense(id) {
        if (!confirm('确定要删除这条报销记录吗？相关的修改历史也会被删除。')) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'expense.php';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }

    // 查看修改历史
    function viewHistory(expenseId) {
        showModal('history-modal');
        fetch('expense_ajax.php?action=history&id=' + expenseId)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.history.length === 0) {
                        document.getElementById('history-content').innerHTML = '<div class="text-center text-gray-400 py-8"><i class="fa fa-info-circle text-2xl"></i><p class="mt-2">暂无修改历史记录</p></div>';
                        return;
                    }
                    let html = '<div class="space-y-3">';
                    data.history.forEach((h, index) => {
                        html += `
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-sm font-medium text-gray-700"><i class="fa fa-user-edit mr-1 text-purple-500"></i>${h.editor}</span>
                                <span class="text-xs text-gray-400">${h.created_at}</span>
                            </div>
                            <div class="text-xs text-gray-500 mb-2">
                                <i class="fa fa-pencil-alt mr-1"></i>修改原因：${h.edit_reason || '（未填写）'}
                            </div>
                            <button onclick="viewDetail('${h.old_data}', '${h.new_data}', '${h.created_at}', '${h.editor}')" class="text-xs text-blue-600 hover:text-blue-800">
                                <i class="fa fa-search mr-1"></i>查看修改详情
                            </button>
                        </div>`;
                    });
                    html += '</div>';
                    document.getElementById('history-content').innerHTML = html;
                } else {
                    document.getElementById('history-content').innerHTML = '<div class="text-center text-red-400 py-8"><i class="fa fa-exclamation-circle text-2xl"></i><p class="mt-2">加载失败</p></div>';
                }
            })
            .catch(err => {
                document.getElementById('history-content').innerHTML = '<div class="text-center text-red-400 py-8"><i class="fa fa-exclamation-circle text-2xl"></i><p class="mt-2">网络错误,请重试</p></div>';
            });
    }

    // 查看修改详情对比
    function viewDetail(oldDataStr, newDataStr, time, editor) {
        let oldData = {}, newData = {};
        try { oldData = JSON.parse(oldDataStr); } catch(e) {}
        try { newData = JSON.parse(newDataStr); } catch(e) {}

        let html = `<div class="mb-4 text-sm text-gray-500">编辑人：${editor} | 时间：${time}</div>`;
        html += '<div class="overflow-x-auto"><table class="w-full text-sm border-collapse"><thead><tr class="bg-gray-100"><th class="px-4 py-2 text-left border">字段</th><th class="px-4 py-2 text-left border bg-red-50">修改前</th><th class="px-4 py-2 text-left border bg-green-50">修改后</th></tr></thead><tbody>';

        const fields = {amount: '报销金额', reason: '报销事由', attachment: '附件'};
        for (const [key, label] of Object.entries(fields)) {
            let oldVal = oldData[key] || '（无）';
            let newVal = newData[key] || '（无）';
            const changed = oldVal !== newVal;
            html += `<tr class="border-b ${changed ? 'bg-yellow-50' : ''}">
                <td class="px-4 py-2 border font-medium">${label} ${changed ? '<span class="text-orange-500 text-xs ml-1">[已修改]</span>' : ''}</td>
                <td class="px-4 py-2 border text-red-700">${oldVal}</td>
                <td class="px-4 py-2 border text-green-700">${newVal}</td>
            </tr>`;
        }
        html += '</tbody></table></div>';
        document.getElementById('detail-content').innerHTML = html;
        showModal('detail-modal');
    }

    // 自动隐藏消息横幅
    setTimeout(() => {
        const banner = document.getElementById('message-banner');
        const errBanner = document.getElementById('error-banner');
        if (banner) banner.style.display = 'none';
        if (errBanner) errBanner.style.display = 'none';
    }, 5000);
    </script>
</body>
</html>
