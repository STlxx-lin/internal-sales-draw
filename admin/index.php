<?php
// index.php - 管理后台仪表盘首页与系统概览中枢
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/admin_records.php';

// 如果旧请求带有 #锚点或特定action参数，兼容重定向至对应子页面
if (isset($_GET['action'])) {
    $act = $_GET['action'];
    if (in_array($act, ['add_project', 'edit_project', 'delete_project'])) {
        header('Location: projects.php');
        exit;
    }
    if (in_array($act, ['add_prize', 'edit_prize', 'delete_prize', 'update_probabilities', 'get_prizes_data'])) {
        $pid = isset($_GET['project_id']) ? '?project_id=' . (int)$_GET['project_id'] : '';
        header('Location: prizes.php' . $pid);
        exit;
    }
    if (in_array($act, ['add_user', 'edit_user', 'delete_user', 'get_user_detail'])) {
        header('Location: users.php');
        exit;
    }
    if (in_array($act, ['set_user_times', 'batch_set_user_times', 'get_user_times'])) {
        $pid = isset($_GET['project_id']) ? '?project_id=' . (int)$_GET['project_id'] : '';
        header('Location: user_times.php' . $pid);
        exit;
    }
    if (in_array($act, ['get_records', 'quick_expense'])) {
        header('Location: lottery_records.php');
        exit;
    }
}

// 获取仪表盘核心指标
$total_projects = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_prizes = (int)$pdo->query("SELECT COUNT(*) FROM prizes")->fetchColumn();
$record_summary = adminRecordSummary($pdo);

// 获取最新 5 条中奖待报销记录
$recent_stmt = $pdo->query("
    SELECT lr.id, lr.created_at, u.name as user_name, p.name as prize_name, pr.name as project_name
    FROM lottery_records lr
    JOIN users u ON lr.user_id = u.id
    JOIN projects pr ON lr.project_id = pr.id
    JOIN prizes p ON lr.prize_id = p.id
    WHERE NOT EXISTS(
        SELECT 1 FROM expense_records er 
        WHERE er.project_id = lr.project_id 
          AND er.user_id = lr.user_id 
          AND er.is_used = 1 
          AND er.reason REGEXP CONCAT('抽奖记录 #', lr.id, '([^0-9]|$)')
    )
    ORDER BY lr.created_at DESC 
    LIMIT 5
");
$recent_pending_records = $recent_stmt->fetchAll();

// 获取当前活动项目列表摘要
$projects_summary = $pdo->query("
    SELECT p.id, p.name, p.status, p.created_at,
           (SELECT COUNT(*) FROM prizes pr WHERE pr.project_id = p.id) AS prize_count,
           (SELECT COUNT(*) FROM user_project_times upt WHERE upt.project_id = p.id) AS user_count
    FROM projects p
    ORDER BY p.id DESC
    LIMIT 6
")->fetchAll();

$active_tab = 'dashboard';
$page_title = '概览面板';
require dirname(__DIR__) . '/views/admin/header.php';
?>

<div class="space-y-6">
    <!-- 业务快捷卡片 -->
    <div>
        <h2 class="text-xl font-bold text-gray-800 mb-1">控制台概览</h2>
        <p class="text-xs text-gray-500 mb-4">系统已完成模块化拆分，您可通过左侧导航或下方快捷入口直接进入各业务子系统</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="projects.php" class="block p-5 bg-white border border-gray-200 rounded-xl hover:border-teal-500 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">抽奖项目</span>
                    <div class="w-10 h-10 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center group-hover:bg-teal-700 group-hover:text-white transition">
                        <i class="fa fa-project-diagram"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <strong class="text-2xl font-bold text-gray-800"><?= $total_projects ?></strong>
                    <span class="text-xs text-gray-500 ml-1">个活动项目</span>
                </div>
                <div class="mt-2 text-xs text-teal-600 font-medium flex items-center">
                    <span>进入项目管理</span>
                    <i class="fa fa-arrow-right ml-1 transition group-hover:translate-x-1"></i>
                </div>
            </a>

            <a href="prizes.php" class="block p-5 bg-white border border-gray-200 rounded-xl hover:border-teal-500 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">活动奖品</span>
                    <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition">
                        <i class="fa fa-gift"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <strong class="text-2xl font-bold text-gray-800"><?= $total_prizes ?></strong>
                    <span class="text-xs text-gray-500 ml-1">项奖品配置</span>
                </div>
                <div class="mt-2 text-xs text-amber-600 font-medium flex items-center">
                    <span>进入奖品与概率</span>
                    <i class="fa fa-arrow-right ml-1 transition group-hover:translate-x-1"></i>
                </div>
            </a>

            <a href="users.php" class="block p-5 bg-white border border-gray-200 rounded-xl hover:border-teal-500 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">系统用户</span>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition">
                        <i class="fa fa-users"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <strong class="text-2xl font-bold text-gray-800"><?= $total_users ?></strong>
                    <span class="text-xs text-gray-500 ml-1">名注册员工</span>
                </div>
                <div class="mt-2 text-xs text-blue-600 font-medium flex items-center">
                    <span>进入用户管理</span>
                    <i class="fa fa-arrow-right ml-1 transition group-hover:translate-x-1"></i>
                </div>
            </a>

            <a href="lottery_records.php" class="block p-5 bg-white border border-gray-200 rounded-xl hover:border-teal-500 hover:shadow-md transition group">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">待报销</span>
                    <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center group-hover:bg-rose-600 group-hover:text-white transition">
                        <i class="fa fa-clock-o"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <strong class="text-2xl font-bold text-rose-600"><?= (int)$record_summary['pending'] ?></strong>
                    <span class="text-xs text-gray-500 ml-1">笔需尽快处理</span>
                </div>
                <div class="mt-2 text-xs text-rose-600 font-medium flex items-center">
                    <span>进入报销工作台</span>
                    <i class="fa fa-arrow-right ml-1 transition group-hover:translate-x-1"></i>
                </div>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 最新待报销中奖记录 -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-800 text-base flex items-center">
                    <i class="fa fa-bell text-rose-500 mr-2"></i>最新待处理中奖
                </h3>
                <a href="lottery_records.php" class="text-xs text-teal-700 hover:underline">查看全部记录 &rarr;</a>
            </div>

            <?php if (empty($recent_pending_records)): ?>
                <div class="text-center py-8 text-gray-400 text-xs">
                    <i class="fa fa-check-circle text-emerald-500 text-xl block mb-2"></i>
                    目前所有中奖记录均已完成报销！
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($recent_pending_records as $rec): ?>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-sm text-gray-800"><?= htmlspecialchars($rec['user_name']) ?></div>
                            <div class="text-xs text-gray-500">
                                <span class="text-teal-700 font-medium"><?= htmlspecialchars($rec['project_name']) ?></span> · 
                                奖品：<span class="text-emerald-700 font-semibold"><?= htmlspecialchars($rec['prize_name']) ?></span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-gray-400 block"><?= date('m-d H:i', strtotime($rec['created_at'])) ?></span>
                            <a href="lottery_records.php" class="text-xs text-teal-600 hover:underline font-medium">快速处理</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 活跃项目概况 -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-gray-800 text-base flex items-center">
                    <i class="fa fa-cubes text-teal-600 mr-2"></i>项目分布与配置
                </h3>
                <a href="projects.php" class="text-xs text-teal-700 hover:underline">项目管理 &rarr;</a>
            </div>

            <?php if (empty($projects_summary)): ?>
                <div class="text-center py-8 text-gray-400 text-xs">暂无活动项目</div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($projects_summary as $ps): ?>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-medium text-sm text-gray-800"><?= htmlspecialchars($ps['name']) ?></span>
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full <?= $ps['status'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                <?= $ps['status'] ? '已启用' : '已停用' ?>
                            </span>
                            <div class="text-xs text-gray-400 mt-0.5">创建于 <?= date('Y-m-d', strtotime($ps['created_at'])) ?></div>
                        </div>
                        <div class="text-right text-xs text-gray-500 space-x-2">
                            <span>奖品: <b class="text-gray-700"><?= $ps['prize_count'] ?></b></span>
                            <span>授权: <b class="text-gray-700"><?= $ps['user_count'] ?></b>人</span>
                            <a href="prizes.php?project_id=<?= $ps['id'] ?>" class="text-teal-600 hover:underline">奖品配置</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/views/admin/footer.php'; ?>
