<?php
require_once 'config.php';

$user_ip = getUserIP();
$user = checkUser($pdo, $user_ip);

// 如果用户不存在，重定向到用户注册页面
if (!$user) {
    header('Location: register.php');
    exit;
}

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 筛选参数
$project_filter = $_GET['project'] ?? '';
$result_filter = $_GET['result'] ?? ''; // 'won' 或 'lost'

// 构建查询条件
$where_conditions = ['lr.user_id = ?'];
$params = [$user['id']];

if ($project_filter) {
    $where_conditions[] = 'lr.project_id = ?';
    $params[] = $project_filter;
}

if ($result_filter === 'won') {
    $where_conditions[] = 'lr.prize_id IS NOT NULL';
} elseif ($result_filter === 'lost') {
    $where_conditions[] = 'lr.prize_id IS NULL';
}

$where_clause = implode(' AND ', $where_conditions);

// 获取总记录数
$count_sql = "
    SELECT COUNT(*) as total 
    FROM lottery_records lr 
    WHERE {$where_clause}
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// 获取抽奖记录
$sql = "
    SELECT lr.*, p.name as prize_name, pr.name as project_name 
    FROM lottery_records lr 
    LEFT JOIN prizes p ON lr.prize_id = p.id 
    JOIN projects pr ON lr.project_id = pr.id
    WHERE {$where_clause}
    ORDER BY lr.created_at DESC 
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 获取所有项目用于筛选
$projects = $pdo->query("SELECT * FROM projects ORDER BY name")->fetchAll();

// 统计信息
$stats_sql = "
    SELECT 
        COUNT(*) as total_draws,
        COUNT(lr.prize_id) as total_wins,
        COUNT(CASE WHEN lr.prize_id IS NULL THEN 1 END) as total_losses
    FROM lottery_records lr 
    WHERE lr.user_id = ?
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$win_rate = $stats['total_draws'] > 0 ? round(($stats['total_wins'] / $stats['total_draws']) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>抽奖历史 - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-purple-400 via-pink-500 to-red-500 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- 头部导航 -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fa fa-history text-green-500 mr-2"></i>
                    抽奖历史
                </h1>
                <div class="flex space-x-4">
                    <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-home mr-2"></i>返回首页
                    </a>
                    <a href="admin.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-cog mr-2"></i>管理后台
                    </a>
                </div>
            </div>
        </div>

        <!-- 用户信息和统计 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- 用户信息 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fa fa-user text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['name']); ?></h3>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['ip_address']); ?></p>
                    </div>
                </div>
            </div>

            <!-- 总抽奖次数 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fa fa-dice text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['total_draws']; ?></h3>
                        <p class="text-sm text-gray-500">总抽奖次数</p>
                    </div>
                </div>
            </div>

            <!-- 中奖次数 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fa fa-trophy text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['total_wins']; ?></h3>
                        <p class="text-sm text-gray-500">中奖次数</p>
                    </div>
                </div>
            </div>

            <!-- 中奖率 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center">
                    <div class="bg-yellow-100 p-3 rounded-full">
                        <i class="fa fa-percentage text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-2xl font-bold text-gray-800"><?php echo $win_rate; ?>%</h3>
                        <p class="text-sm text-gray-500">中奖率</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 筛选器 -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fa fa-filter text-blue-500 mr-2"></i>筛选条件
            </h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">项目</label>
                    <select name="project" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">所有项目</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $project_filter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">结果</label>
                    <select name="result" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">所有结果</option>
                        <option value="won" <?php echo $result_filter === 'won' ? 'selected' : ''; ?>>中奖</option>
                        <option value="lost" <?php echo $result_filter === 'lost' ? 'selected' : ''; ?>>未中奖</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fa fa-search mr-2"></i>筛选
                    </button>
                </div>
            </form>
        </div>

        <!-- 抽奖记录 -->
        <div class="bg-white rounded-lg shadow-lg">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">
                        <i class="fa fa-list text-purple-500 mr-2"></i>抽奖记录
                    </h2>
                    <div class="text-sm text-gray-500">
                        共 <?php echo $total_records; ?> 条记录，第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页
                    </div>
                </div>
            </div>

            <?php if (empty($records)): ?>
                <div class="p-12 text-center">
                    <i class="fa fa-inbox text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-600 mb-2">暂无抽奖记录</h3>
                    <p class="text-gray-500">您还没有参与过抽奖活动</p>
                    <a href="index.php" class="inline-block mt-4 bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition">
                        <i class="fa fa-dice mr-2"></i>立即抽奖
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                            <tr>
                                <th class="px-6 py-3">时间</th>
                                <th class="px-6 py-3">项目</th>
                                <th class="px-6 py-3">结果</th>
                                <th class="px-6 py-3">IP地址</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo date('Y-m-d', strtotime($record['created_at'])); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('H:i:s', strtotime($record['created_at'])); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo htmlspecialchars($record['project_name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($record['prize_name']): ?>
                                        <div class="flex items-center">
                                            <i class="fa fa-trophy text-yellow-500 mr-2"></i>
                                            <span class="text-green-600 font-medium"><?php echo htmlspecialchars($record['prize_name']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center">
                                            <i class="fa fa-times-circle text-gray-400 mr-2"></i>
                                            <span class="text-gray-500">未中奖</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($record['ip_address']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            显示第 <?php echo $offset + 1; ?> 到 <?php echo min($offset + $per_page, $total_records); ?> 条，共 <?php echo $total_records; ?> 条记录
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&project=<?php echo urlencode($project_filter); ?>&result=<?php echo urlencode($result_filter); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <i class="fa fa-chevron-left mr-1"></i>上一页
                                </a>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?>&project=<?php echo urlencode($project_filter); ?>&result=<?php echo urlencode($result_filter); ?>" 
                                   class="px-3 py-2 text-sm font-medium <?php echo $i == $page ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50'; ?> border rounded-lg">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&project=<?php echo urlencode($project_filter); ?>&result=<?php echo urlencode($result_filter); ?>" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    下一页<i class="fa fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // 自动提交筛选表单
    document.querySelectorAll('select[name="project"], select[name="result"]').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });
    </script>
</body>
</html>