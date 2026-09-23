<?php
require_once 'config.php';

$user_ip = getUserIP();
$user = checkUser($pdo, $user_ip);

// 如果用户不存在，重定向到用户注册页面
if (!$user) {
    logUserAction('history_redirect_register', '成功', '未找到用户，跳转注册页');
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
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

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

if ($start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $where_conditions[] = 'lr.created_at >= ?';
    $params[] = $start_date . ' 00:00:00';
}

if ($end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $where_conditions[] = 'lr.created_at <= ?';
    $params[] = $end_date . ' 23:59:59';
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
    SELECT lr.*, p.name as prize_name, pr.name as project_name,
           EXISTS(SELECT 1 FROM expense_records er WHERE er.project_id = lr.project_id AND er.user_id = lr.user_id AND er.is_used = 1 AND er.reason REGEXP CONCAT('抽奖记录 #', lr.id, '([^0-9]|$)')) as is_used
    FROM lottery_records lr 
    LEFT JOIN prizes p ON lr.prize_id = p.id 
    JOIN projects pr ON lr.project_id = pr.id
    WHERE {$where_clause}
    ORDER BY lr.created_at DESC, lr.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
logUserAction('history_view', '成功', ['user_id' => $user['id'], 'page' => $page, 'project' => $project_filter, 'result' => $result_filter]);

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

// 待核销/报销总数计算
$pending_sql = "
    SELECT COUNT(*) as total_pending
    FROM lottery_records lr
    WHERE lr.user_id = ? AND lr.prize_id IS NOT NULL
      AND NOT EXISTS (
          SELECT 1 FROM expense_records er 
          WHERE er.project_id = lr.project_id 
            AND er.user_id = lr.user_id 
            AND er.is_used = 1 
            AND er.reason REGEXP CONCAT('抽奖记录 #', lr.id, '([^0-9]|$)')
      )
";
$stmt = $pdo->prepare($pending_sql);
$stmt->execute([$user['id']]);
$total_pending = (int)($stmt->fetch()['total_pending'] ?? 0);
$total_completed = max(0, (int)$stats['total_wins'] - $total_pending);

// 用户可用剩余抽奖总资格
$stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_times), 0) as total_remaining FROM user_project_times WHERE user_id = ? AND is_visible = 1");
$stmt->execute([$user['id']]);
$total_remaining_times = (int)($stmt->fetch()['total_remaining'] ?? 0);

$win_rate = $stats['total_draws'] > 0 ? round(($stats['total_wins'] / $stats['total_draws']) * 100, 2) : 0;
?>
<!DOCTYPE html><html class="dark" lang="zh-CN" style=""><head>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"></head><body class="bg-surface font-body-md text-on-surface antialiased min-h-screen flex flex-col selection:bg-primary-container selection:text-white"><svg aria-hidden="true" class="inline-defs-container" style="position:absolute;width:0;height:0;overflow:hidden"></svg>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<meta content="web_standard" name="shell-type">
<title>我的抽奖战绩 - <?php echo SITE_NAME; ?></title>
<style>
    @layer base {
      html, body { margin: 0; padding: 0; }
      body { overscroll-behavior: none; }
      main > :first-child { margin-top: 0 !important; }
      main > :last-child { margin-bottom: 0 !important; }
    }
    ::-webkit-scrollbar { display: none; }
  </style>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          "colors": {
            "on-surface-variant": "#ccc3d8",
            "surface-bright": "#31394d",
            "secondary-fixed-dim": "#ffb2b7",
            "background": "#0b1326",
            "error": "#ffb4ab",
            "on-secondary": "#67001b",
            "surface-container-high": "#222a3d",
            "surface-container-highest": "#2d3449",
            "surface-container-lowest": "#060e20",
            "on-tertiary-fixed-variant": "#5c4300",
            "primary": "#d2bbff",
            "secondary-fixed": "#ffdadb",
            "on-secondary-fixed-variant": "#92002a",
            "on-error-container": "#ffdad6",
            "surface-tint": "#d2bbff",
            "tertiary-fixed-dim": "#f9bd22",
            "inverse-primary": "#732ee4",
            "on-background": "#dae2fd",
            "surface-container-low": "#131b2e",
            "surface-container": "#171f33",
            "surface-variant": "#2d3449",
            "on-primary-fixed": "#25005a",
            "surface-dim": "#0b1326",
            "secondary-container": "#b50036",
            "on-primary-fixed-variant": "#5a00c6",
            "tertiary-fixed": "#ffdf9f",
            "primary-fixed-dim": "#d2bbff",
            "on-tertiary-fixed": "#261a00",
            "on-primary-container": "#ede0ff",
            "inverse-surface": "#dae2fd",
            "surface": "#0b1326",
            "on-tertiary-container": "#ffe2ab",
            "on-tertiary": "#402d00",
            "secondary": "#ffb2b7",
            "on-secondary-container": "#ffc2c4",
            "on-primary": "#3f008e",
            "inverse-on-surface": "#283044",
            "on-secondary-fixed": "#40000d",
            "on-surface": "#dae2fd",
            "tertiary-container": "#836100",
            "primary-container": "#7c3aed",
            "on-error": "#690005",
            "primary-fixed": "#eaddff",
            "error-container": "#93000a",
            "tertiary": "#f9bd22",
            "outline": "#958da1",
            "outline-variant": "#4a4455"
          },
          "borderRadius": {
            "DEFAULT": "1rem",
            "lg": "2rem",
            "xl": "3rem",
            "full": "9999px"
          },
          "spacing": {
            "margin": "1.5rem",
            "space-md": "1rem",
            "space-xs": "0.25rem",
            "space-xl": "2.5rem",
            "gutter": "1.5rem",
            "space-lg": "1.5rem",
            "space-sm": "0.5rem"
          },
          "fontFamily": {
            "body-sm": ["Plus Jakarta Sans"],
            "label-md": ["Plus Jakarta Sans"],
            "headline-xl": ["Rubik"],
            "body-lg": ["Plus Jakarta Sans"],
            "label-lg": ["Plus Jakarta Sans"],
            "headline-md": ["Rubik"],
            "headline-lg": ["Rubik"],
            "body-md": ["Plus Jakarta Sans"],
            "headline-sm": ["Rubik"]
          }
        },
      },
    }
  </script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&amp;family=Rubik:wght@500;600;700&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<!-- Top Navigation Bar -->
<header class="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-xl border-b border-outline-variant/30 shadow-[0_1px_16px_rgba(0,0,0,0.25)]">
<div class="h-20 max-w-7xl mx-auto px-gutter flex items-center justify-between">
<div class="flex items-center gap-space-lg">
<a class="font-headline-md text-primary tracking-tight flex items-center gap-2" data-path="首页" href="index.php">
<span class="material-symbols-outlined text-primary text-28px">auto_awesome</span>
<span class=""><?php echo SITE_NAME; ?></span>
</a>
<nav class="hidden md:flex items-center gap-space-sm p-1.5 bg-surface-container/70 backdrop-blur-md rounded-full border border-outline-variant/30" data-active-classes="bg-primary-container text-on-primary-container font-semibold rounded-full">
<a class="px-space-md py-space-sm text-body-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-bright/50 rounded-full transition-all" data-path="幸运抽奖" href="index.php">幸运抽奖</a>
<a class="px-space-md py-space-sm transition-all bg-primary-container text-on-primary-container font-semibold rounded-full shadow-md shadow-primary-container/25" data-path="我的战绩" href="history.php">我的战绩</a>
<a class="px-space-md py-space-sm text-body-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-bright/50 rounded-full transition-all" data-path="管理后台" href="admin.php">管理后台</a>
</nav>
</div>
<div class="flex items-center gap-space-md">
<div class="hidden sm:flex items-center gap-space-sm bg-surface-container-high/90 border border-outline-variant/40 px-4 py-2 rounded-full">
<span class="material-symbols-outlined text-primary text-[20px]">badge</span>
<span class="text-body-sm font-medium text-on-surface"><?php echo htmlspecialchars($user['name']); ?> <span class="text-on-surface-variant text-xs">(IP: <?php echo htmlspecialchars($user['ip_address']); ?>)</span></span>
</div>
<div class="w-10 h-10 rounded-full bg-gradient-to-tr from-primary-container to-primary flex items-center justify-center cursor-pointer shadow-[0_0_15px_rgba(210,187,255,0.35)] ring-2 ring-primary/30">
<span class="material-symbols-outlined text-on-primary text-[20px]">person</span>
</div>
</div>
</div>
</header>
<!-- Main Content Canvas -->
<main class="w-full pt-24 pb-16 bg-surface flex-1"><div class="flex flex-col w-full">
<div class="max-w-7xl mx-auto w-full px-gutter">
<!-- Breadcrumb & Page Identifier -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-space-lg">
<div class="flex items-center gap-space-sm">
<span class="material-symbols-outlined text-primary text-[20px]">military_tech</span>
<span class="font-headline-sm text-headline-sm text-on-surface">我的抽奖战绩</span>
<span class="inline-flex items-center px-3 py-1 rounded-full bg-surface-container-high text-on-surface-variant font-label-md text-label-md tracking-wider">
  用户：<?php echo htmlspecialchars($user['name']); ?> · IP：<?php echo htmlspecialchars($user['ip_address']); ?>
</span>
</div>
<div class="flex items-center gap-2">
<a class="px-4 py-1.5 rounded-full bg-surface-container-high hover:bg-surface-bright text-on-surface text-body-sm font-label-lg transition-colors flex items-center gap-1.5" href="index.php">
<span class="material-symbols-outlined text-tertiary text-[18px]">casino</span>
  返回抽奖大厅
</a>
</div>
</div>
<!-- Top Bento Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-space-md mb-space-xl">
<div class="bg-surface-container-low rounded-DEFAULT p-space-lg flex items-center justify-between shadow-sm border border-outline-variant/30">
  <div>
    <span class="text-on-surface-variant font-label-md text-label-md block mb-1">累计获得奖品</span>
    <div class="flex items-baseline gap-2">
      <span class="font-headline-xl text-headline-xl text-primary leading-none"><?php echo (int)$stats['total_wins']; ?></span>
      <span class="text-body-sm text-on-surface-variant">件 (共抽 <?php echo (int)$stats['total_draws']; ?> 次)</span>
    </div>
  </div>
  <div class="w-12 h-12 rounded-full bg-primary-container/20 flex items-center justify-center text-primary">
    <span class="material-symbols-outlined text-[24px]">emoji_events</span>
  </div>
</div>
<div class="bg-surface-container-low rounded-DEFAULT p-space-lg flex items-center justify-between shadow-sm border border-outline-variant/30">
  <div>
    <span class="text-on-surface-variant font-label-md text-label-md block mb-1">待报销奖品</span>
    <div class="flex items-baseline gap-2">
      <span class="font-headline-xl text-headline-xl text-secondary leading-none"><?php echo (int)$total_pending; ?></span>
      <span class="text-body-sm text-on-surface-variant">件待审批或报销</span>
    </div>
  </div>
  <div class="w-12 h-12 rounded-full bg-secondary-container/20 flex items-center justify-center text-secondary">
    <span class="material-symbols-outlined text-[24px]">pending_actions</span>
  </div>
</div>
<div class="bg-surface-container-low rounded-DEFAULT p-space-lg flex items-center justify-between shadow-sm border border-outline-variant/30">
  <div>
    <span class="text-on-surface-variant font-label-md text-label-md block mb-1">剩余抽奖配额</span>
    <div class="flex items-baseline gap-2">
      <span class="font-headline-xl text-headline-xl text-tertiary leading-none"><?php echo (int)$total_remaining_times; ?></span>
      <span class="text-body-sm text-on-surface-variant">次当前可用</span>
    </div>
  </div>
  <div class="w-12 h-12 rounded-full bg-tertiary-container/30 flex items-center justify-center text-tertiary">
    <span class="material-symbols-outlined text-[24px]">toll</span>
  </div>
</div>
</div>

<!-- Filter Tabs & Batch Controls -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-space-lg">
<div class="inline-flex p-1 rounded-full bg-surface-container-low border border-outline-variant/30 flex-wrap">
  <button class="px-5 py-2 rounded-full font-label-md text-label-md transition-all bg-primary-container text-on-primary-container shadow-sm" id="tab-btn-all" onclick="filterTab('all')">全部 (<?php echo count($records); ?>)</button>
  <button class="px-5 py-2 rounded-full font-label-md text-label-md transition-all text-on-surface-variant hover:text-on-surface" id="tab-btn-pending" onclick="filterTab('pending')">待报销</button>
  <button class="px-5 py-2 rounded-full font-label-md text-label-md transition-all text-on-surface-variant hover:text-on-surface" id="tab-btn-completed" onclick="filterTab('completed')">已发放/已报销</button>
  <button class="px-5 py-2 rounded-full font-label-md text-label-md transition-all text-on-surface-variant hover:text-on-surface" id="tab-btn-history" onclick="filterTab('history')">全部抽奖足迹</button>
</div>

<!-- 筛选工具集：时间范围筛选 + 项目活动下拉 -->
<form method="GET" id="filter-form" class="flex flex-wrap items-center gap-2.5">
  <!-- 时间范围筛选 -->
  <div class="flex items-center gap-1.5 bg-surface-container-high border border-outline-variant/40 rounded-full px-3 py-1.5 shadow-sm">
    <span class="material-symbols-outlined text-[16px] text-primary">calendar_today</span>
    <span class="text-[11px] text-on-surface-variant">时间:</span>
    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="bg-transparent text-xs text-on-surface focus:outline-none cursor-pointer w-28" title="开始日期" onchange="this.form.submit()">
    <span class="text-xs text-outline">至</span>
    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="bg-transparent text-xs text-on-surface focus:outline-none cursor-pointer w-28" title="结束日期" onchange="this.form.submit()">
    <?php if ($start_date || $end_date): ?>
      <a href="?project=<?php echo urlencode($project_filter); ?>&result=<?php echo urlencode($result_filter); ?>" class="text-[11px] text-primary hover:text-white ml-1 px-1.5 py-0.5 rounded bg-primary/20 hover:bg-primary/40 transition-colors" title="清除时间筛选">重置</a>
    <?php endif; ?>
  </div>

  <!-- 项目筛选下拉 -->
  <div class="flex items-center">
    <select name="project" onchange="this.form.submit()" class="bg-surface-container-high text-xs text-on-surface border border-outline-variant/40 rounded-full px-3.5 py-2 focus:outline-none focus:ring-1 focus:ring-primary shadow-sm cursor-pointer">
      <option value="">所有抽奖活动</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $project_filter ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($p['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($result_filter): ?>
    <input type="hidden" name="result" value="<?php echo htmlspecialchars($result_filter); ?>">
  <?php endif; ?>

  <div class="text-xs text-outline hidden xl:flex items-center gap-1.5 ml-1">
    <span class="material-symbols-outlined text-[16px] text-primary">verified</span>
    <span>数据实时同步后台</span>
  </div>
</form>
</div>

<!-- Reward Cards Container -->
<div class="space-y-space-md mb-space-xl" id="rewards-container">
<?php if (empty($records)): ?>
  <div class="bg-surface-container-low rounded-DEFAULT p-12 text-center border border-outline-variant/30">
    <span class="material-symbols-outlined text-outline text-5xl mb-3">inbox</span>
    <h3 class="font-headline-sm text-base text-on-surface font-semibold mb-1">暂无抽奖记录</h3>
    <p class="text-xs text-on-surface-variant mb-4">参与抽奖后，所有的获奖成果都将归档于此</p>
    <a href="index.php" class="px-5 py-2 rounded-full bg-primary-container text-on-primary-container text-xs font-semibold shadow-sm inline-flex items-center gap-1.5">
      <span class="material-symbols-outlined text-sm">casino</span> 前往抽奖
    </a>
  </div>
<?php else: ?>
  <?php foreach ($records as $row): 
    $is_win = !empty($row['prize_id']);
    $is_used = !empty($row['is_used']);
    $item_class = !$is_win ? 'reward-history' : ($is_used ? 'reward-completed' : 'reward-pending');
  ?>
  <div class="reward-item <?php echo $item_class; ?> bg-surface-container-low rounded-DEFAULT p-5 transition-all hover:bg-surface-container flex flex-col sm:flex-row sm:items-center justify-between gap-4 border border-outline-variant/30 shadow-sm <?php echo !$is_win ? 'opacity-75' : ''; ?>">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 rounded-DEFAULT <?php echo !$is_win ? 'bg-surface-container-high text-outline' : ($is_used ? 'bg-primary-container/20 text-primary' : 'bg-secondary-container/20 text-secondary'); ?> flex-shrink-0 flex items-center justify-center">
        <span class="material-symbols-outlined text-[26px]">
          <?php echo !$is_win ? 'sentiment_dissatisfied' : ($is_used ? 'verified' : 'card_giftcard'); ?>
        </span>
      </div>
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="font-headline-sm text-body-lg font-semibold text-on-surface">
            <?php echo htmlspecialchars($row['prize_name'] ?: '未中奖 (谢谢参与)'); ?>
          </h3>
          <?php if (!$is_win): ?>
            <span class="px-2 py-0.5 rounded-full bg-surface-container-high text-outline text-xs font-label-md">足迹</span>
          <?php elseif ($is_used): ?>
            <span class="px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant text-xs font-label-md">已发放 / 已报销</span>
          <?php else: ?>
            <span class="px-2 py-0.5 rounded-full bg-secondary-container/30 text-secondary text-xs font-label-md">待报销</span>
          <?php endif; ?>
        </div>
        <div class="text-xs text-on-surface-variant mt-1">
          <?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?> · <?php echo htmlspecialchars($row['project_name']); ?>
          <span class="font-mono text-outline ml-2">#<?php echo (int)$row['id']; ?></span>
        </div>
      </div>
    </div>
    
    <div class="flex items-center gap-2 sm:self-center">
      <?php if ($is_win && !$is_used): ?>
        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-secondary-container/20 text-secondary text-xs font-semibold border border-secondary-container/30">
          <span class="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
          待报销
        </span>
      <?php elseif ($is_win && $is_used): ?>
        <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-semibold border border-emerald-500/30">
          <span class="material-symbols-outlined text-[16px]">check_circle</span>
          已报销
        </span>
      <?php else: ?>
        <span class="text-xs text-outline px-3 py-1">未中奖</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- 分页栏 -->
<?php if ($total_pages > 1): ?>
<div class="flex items-center justify-between pb-6 text-xs text-on-surface-variant">
  <div>共 <?php echo $total_records; ?> 条记录 · 当前第 <?php echo $page; ?> / <?php echo $total_pages; ?> 页</div>
  <div class="flex items-center gap-2">
    <?php 
      $page_query = '&project=' . urlencode($project_filter) . '&result=' . urlencode($result_filter) . '&start_date=' . urlencode($start_date) . '&end_date=' . urlencode($end_date);
    ?>
    <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page - 1 . $page_query; ?>" class="px-3 py-1.5 rounded-full bg-surface-container-high hover:bg-surface-bright text-on-surface transition">上一页</a>
    <?php endif; ?>
    <?php if ($page < $total_pages): ?>
      <a href="?page=<?php echo $page + 1 . $page_query; ?>" class="px-3 py-1.5 rounded-full bg-surface-container-high hover:bg-surface-bright text-on-surface transition">下一页</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Instructions -->
<div class="p-4 rounded-DEFAULT bg-surface-container-low border border-outline-variant/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-on-surface-variant mb-space-lg">
  <div class="flex items-center gap-2">
    <span class="material-symbols-outlined text-tertiary text-[18px]">receipt_long</span>
    <span>中奖记录均已实时归档，待报销项目可联系管理员或在管理后台录入审批。</span>
  </div>
  <div class="text-outline">系统数据实时同步存证</div>
</div>
</div>

<script>
    function filterTab(category) {
      const allBtn = document.getElementById('tab-btn-all');
      const pendingBtn = document.getElementById('tab-btn-pending');
      const compBtn = document.getElementById('tab-btn-completed');
      const histBtn = document.getElementById('tab-btn-history');
      
      const buttons = [allBtn, pendingBtn, compBtn, histBtn];
      buttons.forEach(btn => {
        btn.className = "px-5 py-2 rounded-full font-label-md text-label-md transition-all text-on-surface-variant hover:text-on-surface";
      });

      const items = document.querySelectorAll('.reward-item');

      if (category === 'all') {
        allBtn.className = "px-5 py-2 rounded-full font-label-md text-label-md transition-all bg-primary-container text-on-primary-container shadow-sm";
        items.forEach(el => {
          if (el.classList.contains('reward-history')) {
            el.classList.add('hidden');
          } else {
            el.classList.remove('hidden');
          }
        });
      } else if (category === 'pending') {
        pendingBtn.className = "px-5 py-2 rounded-full font-label-md text-label-md transition-all bg-primary-container text-on-primary-container shadow-sm";
        items.forEach(el => {
          if (el.classList.contains('reward-pending')) {
            el.classList.remove('hidden');
          } else {
            el.classList.add('hidden');
          }
        });
      } else if (category === 'completed') {
        compBtn.className = "px-5 py-2 rounded-full font-label-md text-label-md transition-all bg-primary-container text-on-primary-container shadow-sm";
        items.forEach(el => {
          if (el.classList.contains('reward-completed')) {
            el.classList.remove('hidden');
          } else {
            el.classList.add('hidden');
          }
        });
      } else if (category === 'history') {
        histBtn.className = "px-5 py-2 rounded-full font-label-md text-label-md transition-all bg-primary-container text-on-primary-container shadow-sm";
        items.forEach(el => el.classList.remove('hidden'));
      }
    }
  </script>
</div></main>

<!-- Global Footer -->
<footer class="w-full bg-surface-container-lowest border-t border-outline-variant/30 py-space-xl mt-auto">
<div class="max-w-7xl mx-auto px-gutter flex flex-col sm:flex-row items-center justify-between gap-4 text-on-surface-variant text-body-sm">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-primary"></span>
<span><?php echo SITE_NAME; ?> 组委会技术支持</span>
</div>
<div>© <?php echo date('Y'); ?> 内部抽奖系统 · 严禁外传</div>
</div>
</footer>
</body></html>
