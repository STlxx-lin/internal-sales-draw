<?php
// views/admin/header.php - 后台统一公共头部与侧边栏
/**
 * 接收参数:
 * $active_tab: 'dashboard' | 'projects' | 'prizes' | 'users' | 'user-times' | 'records'
 * $page_title: 页面标题文本（可选）
 * $message: 成功提示信息（可选）
 * $error: 错误提示信息（可选）
 * $record_summary: 报销概览数据（可选）
 */
$active_tab = $active_tab ?? 'dashboard';
$page_title = $page_title ?? '管理后台';
if (!isset($record_summary) && isset($pdo)) {
    if (function_exists('adminRecordSummary')) {
        $record_summary = adminRecordSummary($pdo);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : '抽奖系统' ?></title>
    <script src="../assets/css/browser@4.js"></script>
    <link rel="stylesheet" href="../font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <header class="admin-header">
            <div>
                <div class="eyebrow">SALES OPERATIONS / 销售运营</div>
                <h1>抽奖与报销工作台</h1>
                <p>集中管理活动与中奖记录，让每一笔报销清晰有序。</p>
            </div>
            <a href="../index.php" target="_blank"><i class="fa fa-external-link mr-2"></i>打开抽奖首页</a>
        </header>

        <?php if (defined('PREVIEW_MODE') && PREVIEW_MODE): ?>
            <div class="admin-preview">测试环境 · 使用独立演示数据，可以放心体验快速报销。</div>
        <?php endif; ?>

        <?php if (isset($record_summary) && is_array($record_summary)): ?>
        <section class="admin-stats" aria-label="报销概览">
            <div class="admin-stat featured">
                <small>待报销中奖记录</small>
                <strong id="summary-pending"><?= (int)($record_summary['pending'] ?? 0) ?></strong>
                <span>选择中奖记录，即可快速处理</span>
            </div>
            <div class="admin-stat">
                <small>已完成报销</small>
                <strong id="summary-used"><?= (int)($record_summary['used'] ?? 0) ?></strong>
                <span>报销凭证与操作历史已保存</span>
            </div>
            <div class="admin-stat">
                <small>累计抽奖记录</small>
                <strong id="summary-total"><?= (int)($record_summary['total'] ?? 0) ?></strong>
                <span>覆盖全部活动与参与员工</span>
            </div>
        </section>
        <?php endif; ?>

        <!-- 全局消息提示 -->
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fa fa-check-circle mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fa fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- 主面板容器 -->
        <div class="admin-panel">
            <aside class="admin-sidebar">
                <div class="admin-brand">
                    SALES<span style="color:#75c1ad">.</span> 后台
                    <small>REWARDS & EXPENSES</small>
                </div>
                <p>工作空间</p>
                <nav class="-mb-px flex space-x-8 px-6">
                    <a href="index.php" id="tab-dashboard" class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab === 'dashboard' ? 'border-blue-500 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        <i class="fa fa-dashboard mr-2"></i>概览面板
                    </a>
                    <a href="projects.php" id="tab-projects" class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab === 'projects' ? 'border-blue-500 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        <i class="fa fa-project-diagram mr-2"></i>项目管理
                    </a>
                    <a href="prizes.php" id="tab-prizes" class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab === 'prizes' ? 'border-blue-500 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        <i class="fa fa-gift mr-2"></i>奖品管理
                    </a>
                    <a href="users.php" id="tab-users" class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab === 'users' ? 'border-blue-500 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        <i class="fa fa-users mr-2"></i>用户管理
                    </a>
                    <a href="user_times.php" id="tab-user-times" class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab === 'user-times' ? 'border-blue-500 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        <i class="fa fa-clock mr-2"></i>次数分配
                    </a>
                    <a href="lottery_records.php" id="tab-records" class="tab-button py-4 px-1 border-b-2 font-medium text-sm <?= $active_tab === 'records' ? 'border-blue-500 text-blue-600 font-bold' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                        <i class="fa fa-history mr-2"></i>抽奖记录
                    </a>
                    <a href="../expense.php" id="tab-expense" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700">
                        <i class="fa fa-money-bill-wave mr-2"></i>报销管理
                    </a>
                    <a href="index.php?action=logout" onclick="return confirm('确定要退出登录吗？')" id="tab-logout" class="tab-button py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700">
                        <i class="fa fa-sign-out-alt mr-2"></i>退出登录
                    </a>
                </nav>
                <div class="sidebar-foot">● 管理员工作空间</div>
            </aside>

            <!-- 子页面内容开始 -->
            <div class="tab-content p-6 flex-1 min-w-0">
