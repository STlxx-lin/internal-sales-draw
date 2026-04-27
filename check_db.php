<?php
// 数据库完整性检查与自动修复脚本
require_once __DIR__ . '/config.php';

// 所有必须的数据库表定义
$required_tables = [
    'departments' => [
        'desc' => '部门表',
        'sql' => "CREATE TABLE `departments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL COMMENT '部门名称',
            `description` TEXT NULL COMMENT '部门描述',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'users' => [
        'desc' => '用户表',
        'sql' => "CREATE TABLE `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL COMMENT '用户姓名',
            `ip_address` VARCHAR(45) NOT NULL COMMENT 'IP地址',
            `department_id` INT(11) NULL COMMENT '部门ID',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `ip_address` (`ip_address`),
            KEY `fk_users_department` (`department_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'projects' => [
        'desc' => '抽奖项目表',
        'sql' => "CREATE TABLE `projects` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(200) NOT NULL COMMENT '项目名称',
            `status` TINYINT(4) DEFAULT 1 COMMENT '1启用0禁用',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'prizes' => [
        'desc' => '奖品表',
        'sql' => "CREATE TABLE `prizes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `project_id` INT(11) NOT NULL COMMENT '所属项目ID',
            `name` VARCHAR(200) NOT NULL COMMENT '奖品名称',
            `image` VARCHAR(500) NULL COMMENT '奖品图片路径',
            `total_quantity` INT(11) NOT NULL DEFAULT 0 COMMENT '总数量',
            `remaining_quantity` INT(11) NOT NULL DEFAULT 0 COMMENT '剩余数量',
            `probability` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '中奖概率(%)',
            `sort_order` INT(11) DEFAULT 0 COMMENT '排序',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `project_id` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'user_project_times' => [
        'desc' => '用户项目抽奖次数表',
        'sql' => "CREATE TABLE `user_project_times` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL COMMENT '用户ID',
            `project_id` INT(11) NOT NULL COMMENT '项目ID',
            `total_times` INT(11) NOT NULL DEFAULT 0 COMMENT '总抽奖次数',
            `remaining_times` INT(11) NOT NULL DEFAULT 0 COMMENT '剩余抽奖次数',
            `is_visible` TINYINT(4) DEFAULT 1 COMMENT '是否显示',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_user_project` (`user_id`, `project_id`),
            KEY `project_id` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'lottery_records' => [
        'desc' => '抽奖记录表',
        'sql' => "CREATE TABLE `lottery_records` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL COMMENT '用户ID',
            `project_id` INT(11) NOT NULL COMMENT '项目ID',
            `prize_id` INT(11) NULL COMMENT '奖品ID(NULL=未中奖)',
            `ip_address` VARCHAR(45) NOT NULL COMMENT '抽奖IP',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `project_id` (`project_id`),
            KEY `prize_id` (`prize_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'expense_records' => [
        'desc' => '抽奖活动报销记录表',
        'sql' => "CREATE TABLE `expense_records` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `project_id` INT(11) NOT NULL COMMENT '关联抽奖项目ID',
            `expense_name` VARCHAR(200) NOT NULL COMMENT '报销活动名称',
            `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '报销金额',
            `reason` TEXT NULL COMMENT '报销事由',
            `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `applicant` VARCHAR(100) NOT NULL COMMENT '申请人',
            `approver` VARCHAR(100) NULL COMMENT '审批人',
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请日期',
            `approved_at` DATETIME NULL COMMENT '审批日期',
            `attachment` VARCHAR(500) NULL COMMENT '附件路径',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_project_id` (`project_id`),
            KEY `idx_status` (`status`),
            KEY `idx_applied_at` (`applied_at`),
            KEY `idx_amount` (`amount`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'expense_edit_history' => [
        'desc' => '报销修改历史记录表',
        'sql' => "CREATE TABLE `expense_edit_history` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `expense_id` INT(11) NOT NULL COMMENT '关联报销记录ID',
            `editor` VARCHAR(100) NOT NULL COMMENT '编辑人',
            `edit_reason` VARCHAR(500) NULL COMMENT '修改原因',
            `old_data` TEXT NULL COMMENT '修改前JSON',
            `new_data` TEXT NULL COMMENT '修改后JSON',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_expense_id` (`expense_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
];

$upload_dirs = ['uploads', 'uploads/expenses'];

// -- 工具函数 --
function dbConnect($withDB = true) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        if ($withDB) $dsn .= ";dbname=" . DB_NAME;
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        return null;
    }
}

// -- AJAX处理（浏览器异步请求） --
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $op = $_GET['ajax'];

    if ($op === 'check_connection') {
        $pdo = dbConnect(false);
        if ($pdo) {
            $stmt = $pdo->query("SELECT VERSION()");
            $ver = $stmt->fetchColumn();
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
            $db_exist = (bool)$stmt->fetchColumn();
            echo json_encode(['ok' => true, 'version' => $ver, 'db_exists' => $db_exist], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['ok' => false, 'error' => '无法连接MySQL服务器'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($op === 'check_all') {
        $steps = [];
        // 连接
        $pdo_no = dbConnect(false);
        if (!$pdo_no) {
            echo json_encode(['ok' => false, 'error' => 'MySQL连接失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $steps[] = ['type' => 'ok', 'msg' => 'MySQL服务器连接成功'];
        $steps[] = ['type' => 'info', 'msg' => 'MySQL版本: ' . $pdo_no->query("SELECT VERSION()")->fetchColumn()];

        // 数据库
        $stmt = $pdo_no->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
        if (!$stmt->fetchColumn()) {
            $pdo_no->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $steps[] = ['type' => 'ok', 'msg' => '数据库 [' . DB_NAME . '] 已创建'];
        } else {
            $steps[] = ['type' => 'ok', 'msg' => '数据库 [' . DB_NAME . '] 已存在'];
        }

        $pdo = dbConnect(true);
        if (!$pdo) {
            $steps[] = ['type' => 'fail', 'msg' => '无法连接目标数据库'];
            echo json_encode(['ok' => false, 'steps' => $steps], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $exist_count = 0;
        $create_count = 0;
        $fail_count = 0;

        foreach ($required_tables as $tname => $tdef) {
            if (in_array($tname, $existing)) {
                $steps[] = ['type' => 'ok', 'msg' => "表 [{$tname}] 已存在", 'table' => $tname, 'status' => 'exist'];
                $exist_count++;
            } else {
                try {
                    $pdo->exec($tdef['sql']);
                    $steps[] = ['type' => 'warn', 'msg' => "表 [{$tname}] 已创建", 'table' => $tname, 'status' => 'created'];
                    $create_count++;
                } catch (PDOException $e) {
                    $steps[] = ['type' => 'fail', 'msg' => "表 [{$tname}] 创建失败: {$e->getMessage()}", 'table' => $tname, 'status' => 'failed'];
                    $fail_count++;
                }
            }
        }

        // 上传目录
        foreach ($upload_dirs as $dir) {
            $fp = __DIR__ . '/' . $dir;
            if (!is_dir($fp)) {
                mkdir($fp, 0755, true);
                $steps[] = ['type' => 'warn', 'msg' => "目录 [{$dir}] 已创建"];
            } else {
                $steps[] = ['type' => 'ok', 'msg' => "目录 [{$dir}] 已存在"];
            }
        }

        echo json_encode([
            'ok' => true,
            'steps' => $steps,
            'summary' => ['existed' => $exist_count, 'created' => $create_count, 'failed' => $fail_count, 'total' => count($required_tables)],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($op === 'repair_missing') {
        $steps = [];
        $pdo = dbConnect(true);
        if (!$pdo) {
            echo json_encode(['ok' => false, 'error' => '数据库连接失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $repaired = 0;
        foreach ($required_tables as $tname => $tdef) {
            if (!in_array($tname, $existing)) {
                try {
                    $pdo->exec($tdef['sql']);
                    $steps[] = ['type' => 'ok', 'msg' => "表 [{$tname}] 修复成功", 'table' => $tname, 'status' => 'created'];
                    $repaired++;
                } catch (PDOException $e) {
                    $steps[] = ['type' => 'fail', 'msg' => "表 [{$tname}] 修复失败: {$e->getMessage()}", 'table' => $tname, 'status' => 'failed'];
                }
            }
        }
        if ($repaired === 0) {
            $steps[] = ['type' => 'info', 'msg' => '没有需要修复的表，所有表均已存在'];
        }
        echo json_encode(['ok' => true, 'steps' => $steps, 'repaired' => $repaired], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($op === 'check_dirs') {
        $steps = [];
        foreach ($upload_dirs as $dir) {
            $fp = __DIR__ . '/' . $dir;
            if (!is_dir($fp)) {
                mkdir($fp, 0755, true);
                $steps[] = ['type' => 'warn', 'msg' => "目录 [{$dir}] 已创建"];
            } else {
                $steps[] = ['type' => 'ok', 'msg' => "目录 [{$dir}] 已存在"];
            }
        }
        echo json_encode(['ok' => true, 'steps' => $steps], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($op === 'table_detail') {
        $table = $_GET['table'] ?? '';
        if (!isset($required_tables[$table])) {
            echo json_encode(['ok' => false, 'error' => '未知表'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $pdo = dbConnect(true);
        if (!$pdo) {
            echo json_encode(['ok' => false, 'error' => '数据库连接失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $cols = $pdo->query("DESCRIBE `{$table}`")->fetchAll();
            $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
            echo json_encode([
                'ok' => true,
                'table' => $table,
                'desc' => $required_tables[$table]['desc'],
                'row_count' => (int)$count,
                'columns' => $cols,
                'create_sql' => $create,
            ], JSON_UNESCAPED_UNICODE);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => '未知操作'], JSON_UNESCAPED_UNICODE);
    exit;
}

// -- CLI模式：自动执行全部检查 --
if (php_sapi_name() === 'cli') {
    function cli_out($msg, $type = 'info') {
        $p = ['ok' => '[✓] ', 'fail' => '[✗] ', 'warn' => '[!] ', 'info' => '[*] '];
        echo ($p[$type] ?? '') . $msg . "\n";
    }

    cli_out("数据库完整性检查开始...", 'info');
    cli_out("目标数据库: " . DB_NAME, 'info');

    $pdo_no = dbConnect(false);
    if (!$pdo_no) { cli_out("MySQL连接失败", 'fail'); exit(1); }
    cli_out("MySQL服务器连接成功", 'ok');

    $stmt = $pdo_no->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    if (!$stmt->fetchColumn()) {
        cli_out("数据库 [" . DB_NAME . "] 不存在，正在创建...", 'warn');
        $pdo_no->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        cli_out("数据库 [" . DB_NAME . "] 创建成功", 'ok');
    } else {
        cli_out("数据库 [" . DB_NAME . "] 已存在", 'ok');
    }

    $pdo = dbConnect(true);
    if (!$pdo) { cli_out("目标数据库连接失败", 'fail'); exit(1); }

    $existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $existed = $created = $failed = 0;
    foreach ($required_tables as $tname => $tdef) {
        if (in_array($tname, $existing)) {
            cli_out("表 [{$tname}] 已存在，跳过", 'ok');
            $existed++;
        } else {
            cli_out("表 [{$tname}] 缺失，正在创建...", 'warn');
            try {
                $pdo->exec($tdef['sql']);
                cli_out("表 [{$tname}] 创建成功", 'ok');
                $created++;
            } catch (PDOException $e) {
                cli_out("表 [{$tname}] 创建失败: " . $e->getMessage(), 'fail');
                $failed++;
            }
        }
    }

    foreach ($upload_dirs as $dir) {
        $fp = __DIR__ . '/' . $dir;
        if (!is_dir($fp)) { mkdir($fp, 0755, true); cli_out("目录 [{$dir}] 已创建", 'ok'); }
        else { cli_out("目录 [{$dir}] 已存在", 'ok'); }
    }

    cli_out("========== 检查完成 ==========", 'info');
    cli_out("已存在: {$existed} | 新创建: {$created} | 失败: {$failed} | 总计: " . count($required_tables), 'info');
    cli_out($failed === 0 ? "√ 数据库完整性检查通过" : "× 存在失败项", $failed === 0 ? 'ok' : 'fail');
    exit;
}

// -- 浏览器模式：输出交互界面 --
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据库完整性检查 - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <!-- 头部 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex flex-wrap justify-between items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fa fa-database text-blue-500 mr-2"></i>
                        数据库完整性检查
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        目标数据库：<span class="font-mono font-semibold"><?= DB_NAME ?></span>
                        &nbsp;|&nbsp; 主机：<span class="font-mono"><?= DB_HOST ?></span>
                        &nbsp;|&nbsp; 共需 <span class="font-semibold"><?= count($required_tables) ?></span> 张表
                    </p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <a href="index.php" class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        <i class="fa fa-home mr-1"></i>首页
                    </a>
                    <a href="admin.php" class="px-3 py-2 text-sm bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                        <i class="fa fa-cog mr-1"></i>管理后台
                    </a>
                </div>
            </div>
        </div>

        <!-- 操作面板 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-700 mb-4"><i class="fa fa-sliders-h text-gray-400 mr-2"></i>操作面板</h2>
            <div class="flex flex-wrap gap-3">
                <button onclick="runCheck()" id="btn-check" class="px-5 py-2.5 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition font-medium text-sm shadow-sm">
                    <i class="fa fa-search mr-2"></i>全面检查
                </button>
                <button onclick="repairMissing()" id="btn-repair" class="px-5 py-2.5 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-medium text-sm shadow-sm">
                    <i class="fa fa-wrench mr-2"></i>一键修复缺失表
                </button>
                <button onclick="checkConnection()" id="btn-conn" class="px-5 py-2.5 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition font-medium text-sm shadow-sm">
                    <i class="fa fa-plug mr-2"></i>检查数据库连接
                </button>
                <button onclick="checkDirs()" id="btn-dirs" class="px-5 py-2.5 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition font-medium text-sm shadow-sm">
                    <i class="fa fa-folder-open mr-2"></i>检查上传目录
                </button>
                <button onclick="location.reload()" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium text-sm">
                    <i class="fa fa-redo mr-2"></i>重置面板
                </button>
            </div>
            <div class="mt-3 text-xs text-gray-400">
                <i class="fa fa-info-circle mr-1"></i>点击操作按钮执行对应任务，执行日志和结果将在下方实时显示
            </div>
        </div>

        <!-- 执行日志 -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-bold text-gray-700"><i class="fa fa-terminal text-gray-400 mr-2"></i>执行日志</h2>
                <span id="log-status" class="text-xs text-gray-400">就绪</span>
            </div>
            <div id="log-panel" class="bg-gray-900 text-gray-200 rounded-lg p-4 font-mono text-xs h-64 overflow-y-auto leading-relaxed">
                <div class="text-gray-500">等待操作...</div>
            </div>
        </div>

        <!-- 汇总统计 -->
        <div id="summary-section" class="mb-6 hidden">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <div class="text-xs text-gray-400 mb-1">已存在</div>
                    <div id="sum-existed" class="text-2xl font-bold text-blue-600">-</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <div class="text-xs text-gray-400 mb-1">已修复/创建</div>
                    <div id="sum-created" class="text-2xl font-bold text-green-600">-</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <div class="text-xs text-gray-400 mb-1">失败</div>
                    <div id="sum-failed" class="text-2xl font-bold text-red-600">-</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                    <div class="text-xs text-gray-400 mb-1">总计</div>
                    <div id="sum-total" class="text-2xl font-bold text-gray-700"><?= count($required_tables) ?></div>
                </div>
            </div>
        </div>

        <!-- 表状态列表 -->
        <div id="table-section" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-700"><i class="fa fa-table text-gray-400 mr-2"></i>数据表状态</h2>
                <span id="table-count" class="text-sm text-gray-400"></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-6 py-3 text-left">表名</th>
                            <th class="px-6 py-3 text-left">说明</th>
                            <th class="px-6 py-3 text-left">状态</th>
                            <th class="px-6 py-3 text-right">操作</th>
                        </tr>
                    </thead>
                    <tbody id="table-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    const logPanel = document.getElementById('log-panel');
    const logStatus = document.getElementById('log-status');

    // 清空日志
    function clearLog() {
        logPanel.innerHTML = '';
    }

    // 写日志
    function log(msg, type) {
        const colors = {ok: '#4ade80', fail: '#f87171', warn: '#fbbf24', info: '#9ca3af'};
        const prefix = {ok: '✓', fail: '✗', warn: '!', info: '·'};
        const color = colors[type] || '#9ca3af';
        const div = document.createElement('div');
        div.innerHTML = `<span style="color:${color}">${prefix[type] || ''} ${msg}</span>`;
        logPanel.appendChild(div);
        logPanel.scrollTop = logPanel.scrollHeight;
    }

    // 设置日志状态
    function setStatus(text, spinning) {
        logStatus.innerHTML = (spinning ? '<i class="fa fa-spinner fa-spin mr-1"></i>' : '') + text;
    }

    // 按钮loading状态
    function setBtnLoading(btn, loading) {
        const el = document.getElementById(btn);
        if (loading) {
            el.disabled = true;
            el.classList.add('opacity-60', 'cursor-not-allowed');
            const icon = el.querySelector('i');
            if (icon) { icon.className = 'fa fa-spinner fa-spin mr-2'; }
        } else {
            el.disabled = false;
            el.classList.remove('opacity-60', 'cursor-not-allowed');
        }
    }

    // 刷新表状态列表
    function refreshTableList(steps) {
        const section = document.getElementById('table-section');
        const tbody = document.getElementById('table-body');
        section.classList.remove('hidden');
        tbody.innerHTML = '';

        const tableStatus = {};
        steps.forEach(s => {
            if (s.table) tableStatus[s.table] = s.status;
        });

        const tables = <?= json_encode(array_keys($required_tables)) ?>;
        const descs = <?= json_encode(array_column($required_tables, 'desc')) ?>;
        let existCount = 0;

        tables.forEach((tname, i) => {
            const st = tableStatus[tname] || 'exist';
            if (st === 'exist') existCount++;

            const badges = {
                exist: '<span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-700">已存在</span>',
                created: '<span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">已创建</span>',
                failed: '<span class="px-2 py-0.5 text-xs rounded-full bg-red-100 text-red-700">失败</span>',
            };

            tbody.innerHTML += `
                <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                    <td class="px-6 py-3 font-mono text-xs font-medium">${tname}</td>
                    <td class="px-6 py-3 text-gray-500 text-xs">${descs[i] || ''}</td>
                    <td class="px-6 py-3">${badges[st] || badges.exist}</td>
                    <td class="px-6 py-3 text-right">
                        <button onclick="showTableDetail('${tname}')" class="text-blue-500 hover:text-blue-700 text-xs">
                            <i class="fa fa-info-circle"></i> 详情
                        </button>
                    </td>
                </tr>`;
        });

        document.getElementById('table-count').textContent = `已存在 ${existCount} / ${tables.length}`;
    }

    // 更新汇总统计
    function updateSummary(summary) {
        const section = document.getElementById('summary-section');
        section.classList.remove('hidden');
        document.getElementById('sum-existed').textContent = summary.existed;
        document.getElementById('sum-created').textContent = summary.created;
        document.getElementById('sum-failed').textContent = summary.failed;
        document.getElementById('sum-total').textContent = summary.total;
    }

    // 全面检查
    function runCheck() {
        clearLog();
        setStatus('正在检查...', true);
        setBtnLoading('btn-check', true);
        log('开始全面检查...', 'info');
        log('目标数据库: <?= DB_NAME ?>', 'info');

        fetch('check_db.php?ajax=check_all')
            .then(r => r.json())
            .then(data => {
                if (!data.ok) { log(data.error || '检查失败', 'fail'); setStatus('失败'); return; }
                data.steps.forEach(s => log(s.msg, s.type));
                log('======== 检查完成 ========', 'info');
                if (data.summary) {
                    updateSummary(data.summary);
                    log(`已存在:${data.summary.existed} | 新创建:${data.summary.created} | 失败:${data.summary.failed}`, 'info');
                    log(data.summary.failed === 0 ? '√ 数据库完整性检查通过，所有表均就绪。' : '× 存在失败项', data.summary.failed === 0 ? 'ok' : 'fail');
                }
                refreshTableList(data.steps);
                setStatus(data.summary && data.summary.failed === 0 ? '检查通过' : '存在失败项');
            })
            .catch(e => { log('网络错误: ' + e.message, 'fail'); setStatus('错误'); })
            .finally(() => setBtnLoading('btn-check', false));
    }

    // 一键修复
    function repairMissing() {
        if (!confirm('将检查并创建所有缺失的数据表，确认继续？')) return;

        clearLog();
        setStatus('正在修复...', true);
        setBtnLoading('btn-repair', true);
        log('开始修复缺失表...', 'info');

        fetch('check_db.php?ajax=repair_missing')
            .then(r => r.json())
            .then(data => {
                if (!data.ok) { log(data.error || '修复失败', 'fail'); setStatus('失败'); return; }
                data.steps.forEach(s => log(s.msg, s.type));
                log(data.repaired === 0 ? '所有表均已存在，无需修复。' : `修复完成，共修复 ${data.repaired} 张表。`, data.repaired > 0 ? 'ok' : 'info');
                setStatus(`已修复 ${data.repaired} 张表`);
                // 修复后自动重新检查
                setTimeout(() => runCheck(), 500);
            })
            .catch(e => { log('网络错误: ' + e.message, 'fail'); setStatus('错误'); })
            .finally(() => setBtnLoading('btn-repair', false));
    }

    // 检查数据库连接
    function checkConnection() {
        clearLog();
        setStatus('正在检查连接...', true);
        setBtnLoading('btn-conn', true);
        log('检查数据库连接...', 'info');

        fetch('check_db.php?ajax=check_connection')
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    log('MySQL服务器连接成功', 'ok');
                    log('MySQL版本: ' + data.version, 'info');
                    log('数据库 [' + '<?= DB_NAME ?>' + ']: ' + (data.db_exists ? '已存在' : '不存在'), data.db_exists ? 'ok' : 'warn');
                } else {
                    log(data.error, 'fail');
                }
                setStatus(data.ok ? '连接成功' : '连接失败');
            })
            .catch(e => { log('网络错误: ' + e.message, 'fail'); setStatus('错误'); })
            .finally(() => setBtnLoading('btn-conn', false));
    }

    // 检查上传目录
    function checkDirs() {
        clearLog();
        setStatus('正在检查目录...', true);
        setBtnLoading('btn-dirs', true);
        log('检查上传目录...', 'info');

        fetch('check_db.php?ajax=check_dirs')
            .then(r => r.json())
            .then(data => {
                if (!data.ok) { log(data.error || '检查失败', 'fail'); setStatus('失败'); return; }
                data.steps.forEach(s => log(s.msg, s.type));
                setStatus('目录检查完成');
            })
            .catch(e => { log('网络错误: ' + e.message, 'fail'); setStatus('错误'); })
            .finally(() => setBtnLoading('btn-dirs', false));
    }

    // 查看表详情
    function showTableDetail(tname) {
        clearLog();
        setStatus('加载表详情...', true);
        log(`加载表 [${tname}] 详情...`, 'info');

        fetch(`check_db.php?ajax=table_detail&table=${encodeURIComponent(tname)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.ok) { log(data.error || '加载失败', 'fail'); setStatus('失败'); return; }
                log(`表名: ${data.table}`, 'ok');
                log(`说明: ${data.desc}`, 'info');
                log(`数据行数: ${data.row_count}`, 'info');
                log('--- 字段列表 ---', 'info');
                data.columns.forEach(c => {
                    log(`  ${c.Field.padEnd(20)} ${c.Type.padEnd(20)} ${c.Null === 'YES' ? 'NULL' : 'NOT NULL'}  ${c.Key ? '['+c.Key+']' : ''}`, 'info');
                });
                log('--- 建表语句 ---', 'info');
                log(data.create_sql, 'info');
                setStatus(`已加载表 ${tname}`);
            })
            .catch(e => { log('网络错误: ' + e.message, 'fail'); setStatus('错误'); });
    }

    // 页面加载时自动执行一次连接检查
    window.addEventListener('load', () => {
        logPanel.innerHTML = '<div class="text-gray-500">就绪 — 点击上方按钮开始操作</div>';
    });
    </script>
</body>
</html>
