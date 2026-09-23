<?php
require_once 'config.php';

$user_ip = getUserIP();
$user = checkUser($pdo, $user_ip);

// 如果用户不存在，重定向到用户注册页面
if (!$user) {
    logUserAction('index_redirect_register', '成功', '未找到用户，跳转注册页');
    header('Location: register.php');
    exit;
}

// 一次查询获取用户可见的启用项目和次数，避免项目列表逐项查询。
$stmt = $pdo->prepare("SELECT p.*, upt.remaining_times FROM projects p JOIN user_project_times upt ON upt.project_id = p.id AND upt.user_id = ? AND upt.is_visible = 1 WHERE p.status = 1 ORDER BY p.id");
$stmt->execute([$user['id']]);
$projects = $stmt->fetchAll();

// 获取当前选中的项目（默认第一个）
$current_project_id = isset($_GET['project']) ? (int)$_GET['project'] : ($projects[0]['id'] ?? 0);

// 获取当前项目信息
$current_project = null;
foreach ($projects as $project) {
    if ($project['id'] == $current_project_id) {
        $current_project = $project;
        break;
    }
}

// 当前项目来自已过滤权限的项目列表。
$user_project = $current_project;
if (!$current_project) {
    $current_project_id = 0;
}

$remaining_times = $user_project ? $user_project['remaining_times'] : 0;
// 是否在前端展示奖品等级（特等奖/一等奖等标签），默认开启
$show_prize_level = isset($current_project['show_prize_level']) ? (int)$current_project['show_prize_level'] : 1;

// 获取当前项目的奖品（过滤掉概率为0%的奖品，不在转盘和奖品池中显示）
$stmt = $pdo->prepare("SELECT * FROM prizes WHERE project_id = ? AND probability > 0 ORDER BY sort_order, id");
$stmt->execute([$current_project_id]);
$prizes = $stmt->fetchAll();

// 提前计算概率阶梯（概率越低越稀有珍贵，供转盘和奖品池共享）
$distinct_probs = array_values(array_unique(array_map(function($p) {
    return (float)($p['probability'] ?? 0);
}, $prizes)));
sort($distinct_probs);

// 获取用户的抽奖历史（最近20条）
$stmt = $pdo->prepare("
    SELECT lr.*, p.name as prize_name, pr.name as project_name,
           EXISTS(SELECT 1 FROM expense_records er WHERE er.project_id = lr.project_id AND er.user_id = lr.user_id AND er.is_used = 1 AND er.reason REGEXP CONCAT('抽奖记录 #', lr.id, '([^0-9]|$)')) as is_used
    FROM lottery_records lr 
    LEFT JOIN prizes p ON lr.prize_id = p.id 
    JOIN projects pr ON lr.project_id = pr.id
    WHERE lr.user_id = ? 
    ORDER BY lr.created_at DESC, lr.id DESC
    LIMIT 20
");
$stmt->execute([$user['id']]);
$recent_records = $stmt->fetchAll();
logUserAction('index_view', '成功', ['user_id' => $user['id'], 'project_id' => $current_project_id, 'remaining_times' => $remaining_times, 'visible' => $user_project ? 1 : 0]);
?>
<!DOCTYPE html><html class="dark" lang="zh-CN" style=""><svg class="inline-defs-container" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden"></svg><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<meta content="web_standard" name="shell-type">
<title><?php echo SITE_NAME; ?> - 年会盛典幸运抽奖</title>
<style>
    @layer base {
      html, body { margin: 0; padding: 0; }
      body { overscroll-behavior: none; }
      main > :first-child { margin-top: 0 !important; }
      main > :last-child { margin-bottom: 0 !important; }
    }
    ::-webkit-scrollbar { display: none; }
    .clip-triangle {
      clip-path: polygon(50% 100%, 0% 0%, 100% 0%);
    }
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
</head>
<body class="bg-surface font-body-md text-on-surface antialiased min-h-screen flex flex-col selection:bg-primary-container selection:text-white">
<!-- Top Navigation Bar -->
<header class="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-xl border-b border-outline-variant/30 shadow-[0_1px_16px_rgba(0,0,0,0.25)]">
<div class="h-20 max-w-7xl mx-auto px-gutter flex items-center justify-between">
<div class="flex items-center gap-space-lg">
<a class="font-headline-md text-primary tracking-tight flex items-center gap-2" data-path="首页" href="index.php">
<span class="material-symbols-outlined text-primary text-28px">auto_awesome</span>
<span class=""><?php echo SITE_NAME; ?></span>
</a>
<nav class="hidden md:flex items-center gap-space-sm p-1.5 bg-surface-container/70 backdrop-blur-md rounded-full border border-outline-variant/30" data-active-classes="bg-primary-container text-on-primary-container font-semibold rounded-full">
<a aria-current="page" class="px-space-md py-space-sm transition-all bg-primary-container text-on-primary-container font-semibold rounded-full shadow-md shadow-primary-container/25" data-path="幸运抽奖" href="index.php">幸运抽奖</a>
<a class="px-space-md py-space-sm text-body-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-bright/50 rounded-full transition-all" data-path="我的战绩" href="history.php">我的战绩</a>
<a class="px-space-md py-space-sm text-body-sm text-on-surface-variant hover:text-on-surface hover:bg-surface-bright/50 rounded-full transition-all" data-path="管理后台" href="admin.php">管理后台</a>
</nav>
</div>
<div class="flex items-center gap-space-md">
<div class="hidden sm:flex items-center gap-space-sm bg-surface-container-high/90 border border-outline-variant/40 px-4 py-2 rounded-full">
<span class="material-symbols-outlined text-primary text-[20px]">badge</span>
<span class="text-body-sm font-medium text-on-surface"><?php echo htmlspecialchars($user['name']); ?> <span class="text-on-surface-variant text-xs">(IP: <?php echo htmlspecialchars($user['ip_address']); ?>)</span></span>
</div>
<a href="history.php" title="个人抽奖记录" class="w-10 h-10 rounded-full bg-gradient-to-tr from-primary-container to-primary flex items-center justify-center cursor-pointer shadow-[0_0_15px_rgba(210,187,255,0.35)] ring-2 ring-primary/30">
<span class="material-symbols-outlined text-on-primary text-[20px]">person</span>
</a>
</div>
</div>
</header>
<!-- Main Content Canvas -->
<main class="w-full pt-24 pb-16 bg-surface flex-1">
<div class="max-w-7xl mx-auto px-gutter flex flex-col gap-8">
<!-- Section 1: Hero & Quota Status Bar -->
<section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-surface-container-low via-surface-container to-surface-container-high border border-outline-variant/40 p-6 md:p-8 shadow-xl">
<div class="absolute -right-16 -top-16 w-80 h-80 bg-primary-container/20 rounded-full blur-3xl pointer-events-none"></div>
<div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
<div class="space-y-2">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 border border-primary/25 text-primary text-label-md font-semibold">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
              内部专属 • 公平公正公开&nbsp;</div>
<h2 class="text-2xl sm:text-3xl font-headline-md font-bold text-on-surface">
  <?php echo htmlspecialchars($current_project['name'] ?? '请选择抽奖项目'); ?>
</h2>
<p class="text-xs text-on-surface-variant">参与年会抽奖，所中奖品自动记入员工档案与待报销清单</p>
</div>
<!-- Quota Card -->
<div class="flex items-center gap-4 bg-surface-container-lowest/80 border border-outline-variant/60 backdrop-blur-md px-5 py-4 rounded-xl shadow-inner">
<div class="w-12 h-12 rounded-xl bg-primary/15 border border-primary/30 flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-[28px]">confirmation_number</span>
</div>
<div class="flex flex-col">
<span class="text-xs text-on-surface-variant">当前项目抽奖配额</span>
<div class="flex items-baseline gap-1.5">
<span class="text-xs font-semibold text-on-surface">剩余</span>
<span class="text-3xl font-headline-lg font-bold text-tertiary font-mono" id="chance-count"><?php echo (int)$remaining_times; ?></span>
<span class="text-xs text-on-surface-variant">次机会</span>
</div>
</div>
<div class="h-9 w-px bg-outline-variant/40 mx-1"></div>
<div class="text-right pl-1">
<div class="text-xs text-on-surface-variant">身份认证状态</div>
<span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-400">
<span class="material-symbols-outlined text-[14px]">verified</span>已核实准入
              </span>
</div>
</div>
</div>
</section>
<!-- Section 2: Main Interactive Row (Wheel Area + Side Panels) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
<!-- Left: Wheel & Interactive Zone (7 Cols on desktop) -->
<section class="lg:col-span-7 flex flex-col items-center bg-surface-container-low/70 border border-outline-variant/40 rounded-2xl p-6 sm:p-8 backdrop-blur-sm relative overflow-hidden shadow-xl">
<!-- Background glow circle behind wheel -->
<div class="absolute w-[440px] h-[440px] rounded-full bg-primary-container/15 blur-3xl pointer-events-none top-12"></div>
<!-- Mode switcher tab (项目切换栏) -->
<div class="flex items-center flex-wrap justify-center gap-1.5 p-1.5 bg-surface-container-high/90 backdrop-blur-md rounded-2xl sm:rounded-full border border-outline-variant/50 mb-6 z-10 shadow-lg">
<?php foreach ($projects as $idx => $p): 
    $is_active = ($p['id'] == $current_project_id);
?>
<a href="?project=<?php echo $p['id']; ?>" class="px-4 py-2 rounded-xl sm:rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?php echo $is_active ? 'bg-primary-container text-on-primary-container shadow-md shadow-primary-container/25 border border-primary/30' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-bright/50'; ?>">
    <span class="material-symbols-outlined text-[18px] <?php echo $is_active ? 'text-tertiary' : ''; ?>"><?php echo $idx === 0 ? 'auto_awesome' : ($idx === 1 ? 'groups' : 'trophy'); ?></span>
    <span><?php echo htmlspecialchars($p['name']); ?></span>
    <span class="text-[10px] opacity-90 font-mono" id="project-quota-<?php echo $p['id']; ?>">(<?php echo (int)$p['remaining_times']; ?>)</span>
</a>
<?php endforeach; ?>
<?php if (empty($projects)): ?>
    <span class="px-4 py-2 text-xs text-on-surface-variant">暂无可用抽奖项目</span>
<?php endif; ?>
</div>

<!-- Wheel Display Mode -->
<div class="flex flex-col items-center w-full relative z-10" id="mode-wheel">
<!-- Modern Tech Wheel Container -->
<div class="relative w-[340px] h-[340px] sm:w-[420px] sm:h-[420px] flex items-center justify-center select-none my-2">
<!-- Outer decorative neon ring -->
<div class="absolute inset-0 rounded-full border-4 border-primary/20 shadow-[0_0_35px_rgba(124,58,237,0.25)] pointer-events-none"></div>
<div class="absolute -inset-2 rounded-full border border-outline-variant/30 pointer-events-none"></div>
<!-- Rotating SVG Wheel -->
<div class="w-full h-full rounded-full transition-transform duration-[4500ms] cubic-bezier(0.15,0.85,0.1,1) overflow-hidden shadow-2xl" id="wheel-element">
<?php
// 构建转盘扇区数据：包含当前项目的所有真实奖品，并在末尾添加一个"谢谢参与"扇区
$wheel_items = [];
foreach ($prizes as $p) {
    // 确定对应的等级
    $prob = (float)($p['probability'] ?? 0);
    $prob_rank = array_search($prob, $distinct_probs);
    if ($prob_rank === false) $prob_rank = 99;
    
    if (empty($show_prize_level)) {
        $level_name = '';
    } elseif ($prob_rank === 0) $level_name = '特等奖';
    elseif ($prob_rank === 1) $level_name = '一等奖';
    elseif ($prob_rank === 2) $level_name = '二等奖';
    elseif ($prob_rank === 3) $level_name = '三等奖';
    else $level_name = '幸运奖';

    $wheel_items[] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'level' => $level_name,
        'probability' => $prob,
        'is_win' => true
    ];
}
// 补充谢谢参与扇区
$wheel_items[] = [
    'id' => 0,
    'name' => '谢谢参与',
    'level' => '再接再厉',
    'probability' => 0,
    'is_win' => false
];

$segment_count = count($wheel_items);
$angle_per_segment = 360 / $segment_count;
?>
<svg class="w-full h-full" viewBox="0 0 400 400">
<defs>
<!-- 特等奖：至尊金橙渐变 -->
<linearGradient id="grad-tier-0" x1="0%" x2="100%" y1="0%" y2="100%">
  <stop offset="0%" stop-color="#f59e0b" stop-opacity="1"></stop>
  <stop offset="100%" stop-color="#b45309" stop-opacity="1"></stop>
</linearGradient>
<!-- 一等奖：梦幻青蓝渐变 -->
<linearGradient id="grad-tier-1" x1="0%" x2="100%" y1="0%" y2="100%">
  <stop offset="0%" stop-color="#06b6d4" stop-opacity="1"></stop>
  <stop offset="100%" stop-color="#0e7490" stop-opacity="1"></stop>
</linearGradient>
<!-- 二等奖：翡翠碧绿渐变 -->
<linearGradient id="grad-tier-2" x1="0%" x2="100%" y1="0%" y2="100%">
  <stop offset="0%" stop-color="#10b981" stop-opacity="1"></stop>
  <stop offset="100%" stop-color="#047857" stop-opacity="1"></stop>
</linearGradient>
<!-- 三等奖：浪漫粉紫渐变 -->
<linearGradient id="grad-tier-3" x1="0%" x2="100%" y1="0%" y2="100%">
  <stop offset="0%" stop-color="#ec4899" stop-opacity="1"></stop>
  <stop offset="100%" stop-color="#be185d" stop-opacity="1"></stop>
</linearGradient>
<!-- 幸运奖A：科技紫罗兰渐变 -->
<linearGradient id="grad-tier-lucky-a" x1="0%" x2="100%" y1="0%" y2="100%">
  <stop offset="0%" stop-color="#7c3aed" stop-opacity="1"></stop>
  <stop offset="100%" stop-color="#4f46e5" stop-opacity="1"></stop>
</linearGradient>
<!-- 幸运奖B：深邃靛蓝渐变 -->
<linearGradient id="grad-tier-lucky-b" x1="0%" x2="100%" y1="0%" y2="100%">
  <stop offset="0%" stop-color="#4338ca" stop-opacity="1"></stop>
  <stop offset="100%" stop-color="#1e1b4b" stop-opacity="1"></stop>
</linearGradient>
<!-- 谢谢参与：暗灰稳重渐变 -->
<linearGradient id="grad-tier-none" x1="0%" x2="100%" y1="0%" y2="100%">
  <stop offset="0%" stop-color="#334155" stop-opacity="1"></stop>
  <stop offset="100%" stop-color="#1e293b" stop-opacity="1"></stop>
</linearGradient>
</defs>

<!-- 动态生成所有扇区切片与文字 -->
<?php 
for ($i = 0; $i < $segment_count; $i++): 
    $item = $wheel_items[$i];
    $start_deg = $i * $angle_per_segment;
    $end_deg = ($i + 1) * $angle_per_segment;
    
    // 计算扇区弧度的起始和结束坐标 (圆心 200, 200，半径 200)
    $start_rad = deg2rad($start_deg);
    $end_rad = deg2rad($end_deg);
    $x1 = 200 + 200 * cos($start_rad);
    $y1 = 200 + 200 * sin($start_rad);
    $x2 = 200 + 200 * cos($end_rad);
    $y2 = 200 + 200 * sin($end_rad);
    $large_arc = ($angle_per_segment > 180) ? 1 : 0;
    
    // 根据等级（或统一交替色）分配扇区颜色与外圈标签
    if (!$item['is_win']) {
        $fill = 'url(#grad-tier-none)';
        $level_color = '#94a3b8';
    } elseif (empty($show_prize_level)) {
        // 当关闭等级显示时，采用优雅科技交替渐变色，统一呈现奖品
        $fill = ($i % 2 === 0) ? 'url(#grad-tier-lucky-a)' : 'url(#grad-tier-lucky-b)';
        $level_color = '#ffffff';
    } elseif ($item['level'] === '特等奖') {
        $fill = 'url(#grad-tier-0)';
        $level_color = '#fef08a';
    } elseif ($item['level'] === '一等奖') {
        $fill = 'url(#grad-tier-1)';
        $level_color = '#a5f3fc';
    } elseif ($item['level'] === '二等奖') {
        $fill = 'url(#grad-tier-2)';
        $level_color = '#a7f3d0';
    } elseif ($item['level'] === '三等奖') {
        $fill = 'url(#grad-tier-3)';
        $level_color = '#fbcfe8';
    } else {
        // 幸运奖交替色
        $fill = ($i % 2 === 0) ? 'url(#grad-tier-lucky-a)' : 'url(#grad-tier-lucky-b)';
        $level_color = '#ddd6fe';
    }

    $center_deg = $start_deg + ($angle_per_segment / 2);
    // 字体大小与排版位置自适应
    $font_size_lvl = $segment_count > 12 ? '9' : ($segment_count > 8 ? '10' : '11');
    $font_size_name = $segment_count > 12 ? '9.5' : ($segment_count > 8 ? '10.5' : '11.5');
    // 圆心是(200, 200)，半径是200，中心按钮半径大约50
    $r_level = 160;
    $r_name  = 115;
?>
<path d="M200 200 L<?php echo round($x1, 2); ?> <?php echo round($y1, 2); ?> A200 200 0 <?php echo $large_arc; ?> 1 <?php echo round($x2, 2); ?> <?php echo round($y2, 2); ?> Z" fill="<?php echo $fill; ?>" stroke="#1e1b4b" stroke-width="1.5"></path>

<!-- 沿扇区中轴径向均匀排布：根据配置决定是否显示等级文字 -->
<g transform="rotate(<?php echo $center_deg; ?> 200 200)" style="filter: drop-shadow(0 1px 2px rgba(0,0,0,0.9));">
  <?php if (!empty($show_prize_level)): ?>
    <!-- 等级 (外圈 r=160) -->
    <text x="<?php echo 200 + $r_level; ?>" y="200" fill="<?php echo $level_color; ?>" font-family="Rubik" font-size="<?php echo $font_size_lvl; ?>" font-weight="700" text-anchor="middle" dominant-baseline="central"><?php echo htmlspecialchars($item['level']); ?></text>
    <!-- 奖品名称 (内层 r=115) -->
    <text x="<?php echo 200 + $r_name; ?>" y="200" fill="#ffffff" font-size="<?php echo $font_size_name; ?>" font-weight="600" text-anchor="middle" dominant-baseline="central"><?php echo htmlspecialchars(mb_substr($item['name'], 0, 7)); ?></text>
  <?php else: ?>
    <!-- 不显示等级时，奖品名称在扇区居中呈现 (r=138) -->
    <text x="<?php echo 200 + 138; ?>" y="200" fill="#ffffff" font-size="<?php echo $segment_count > 12 ? '11' : ($segment_count > 8 ? '12.5' : '13.5'); ?>" font-weight="700" text-anchor="middle" dominant-baseline="central"><?php echo htmlspecialchars(mb_substr($item['name'], 0, 8)); ?></text>
  <?php endif; ?>
</g>
<?php endfor; ?>
</svg>
</div>
<!-- Top Wheel Pointer (Triangle pointer pointing down) -->
<div class="absolute -top-3 left-1/2 -translate-x-1/2 z-30 pointer-events-none drop-shadow-[0_4px_8px_rgba(0,0,0,0.6)] flex flex-col items-center">
<div class="w-7 h-9 bg-gradient-to-b from-tertiary to-tertiary-container clip-triangle"></div>
<div class="w-3 h-3 rounded-full bg-white -mt-1 shadow-md"></div>
</div>
<!-- Center Interactive Spin Button -->
<div class="absolute z-20 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
<button class="group relative w-24 h-24 sm:w-28 sm:h-28 rounded-full bg-gradient-to-br from-primary-container via-purple-600 to-indigo-800 text-white font-bold p-1 shadow-[0_0_30px_rgba(124,58,237,0.7)] hover:scale-105 active:scale-95 transition-transform duration-200 flex flex-col items-center justify-center border-4 border-surface-bright focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed" id="spin-btn" onclick="spinWheel()" <?php echo $remaining_times <= 0 ? 'disabled' : ''; ?>>
<div class="absolute inset-0 rounded-full bg-white/20 blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity"></div>
<span class="material-symbols-outlined text-[26px] text-tertiary animate-pulse mb-0.5" id="spin-icon">touch_app</span>
<span class="text-sm font-headline-sm tracking-wide" id="spin-text">立即抽奖</span>
<span class="text-[10px] text-on-primary-container opacity-90">消耗 1 次</span>
</button>
</div>
</div>
<!-- Quick Action Bar below wheel -->
<div class="mt-8 flex flex-wrap items-center justify-center gap-3 w-full max-w-md">
<button class="flex-1 min-w-[120px] py-2.5 px-4 rounded-xl bg-primary-container/80 hover:bg-primary-container text-on-primary-container font-semibold text-xs transition-all shadow-md flex items-center justify-center gap-1.5 border border-primary/30" onclick="spinWheel()">
<span class="material-symbols-outlined text-base">play_arrow</span>
                单次抽奖
              </button>
<a href="history.php" class="flex-1 min-w-[140px] py-2.5 px-4 rounded-xl bg-surface-container-high hover:bg-surface-bright text-on-surface font-semibold text-xs transition-all border border-outline-variant/60 flex items-center justify-center gap-1.5 hover:border-primary/50 text-center">
<span class="material-symbols-outlined text-base text-tertiary">history</span>
                历史与报销
              </a>
<button class="py-2.5 px-4 rounded-xl bg-surface-container hover:bg-surface-bright text-on-surface-variant hover:text-on-surface font-medium text-xs transition-all border border-outline-variant/40 flex items-center justify-center gap-1" onclick="openRulesModal()">
<span class="material-symbols-outlined text-base">info</span>
                抽奖规则说明
              </button>
</div>
</div>
</section>

<!-- Right: Auxiliary Sidebars (5 Cols on desktop) -->
<div class="lg:col-span-5 flex flex-col h-full">
<!-- My Win Record Panel (我的中奖记录) -->
<div class="bg-surface-container-low/70 border border-outline-variant/40 rounded-2xl p-6 backdrop-blur-sm shadow-xl flex flex-col justify-between h-full flex-1">
<div class="flex flex-col flex-1 min-h-0">
<div class="flex items-center justify-between pb-3.5 border-b border-outline-variant/30 mb-4 flex-shrink-0">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-[22px]">inventory_2</span>
<h3 class="font-headline-sm text-sm font-semibold text-on-surface">我的中奖记录 (用户: <?php echo htmlspecialchars($user['name']); ?>)</h3>
</div>
<span class="text-xs text-primary font-semibold bg-primary/10 border border-primary/20 px-2.5 py-0.5 rounded-full">最近 <?php echo count($recent_records); ?> 笔</span>
</div>

<div class="space-y-2.5 overflow-y-auto pr-1 flex-1 min-h-[440px] max-h-[560px]" style="scrollbar-width: thin;">
<?php 
$has_wins = false;
foreach ($recent_records as $rec): 
    if ($rec['prize_name']):
        $has_wins = true;
?>
<div class="p-3 rounded-xl bg-surface-container/80 border border-primary/20 flex items-center justify-between hover:border-primary/40 transition-colors">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-lg bg-primary/20 flex items-center justify-center text-primary flex-shrink-0">
<span class="material-symbols-outlined text-20px">card_giftcard</span>
</div>
<div>
<div class="text-xs font-semibold text-on-surface"><?php echo htmlspecialchars($rec['prize_name']); ?></div>
<div class="text-[11px] text-on-surface-variant"><?php echo htmlspecialchars($rec['project_name']); ?> · <?php echo date('m-d H:i', strtotime($rec['created_at'])); ?></div>
</div>
</div>
<?php if ($rec['is_used']): ?>
<span class="px-2 py-0.5 rounded text-[11px] bg-emerald-500/20 text-emerald-300 font-medium border border-emerald-500/30">已报销</span>
<?php else: ?>
<span class="px-2 py-0.5 rounded text-[11px] bg-amber-500/20 text-amber-300 font-medium border border-amber-500/30">待报销</span>
<?php endif; ?>
</div>
<?php endif; endforeach; ?>
<?php if (!$has_wins): ?>
<div class="text-center py-16 text-on-surface-variant text-xs flex flex-col items-center justify-center gap-2">
    <span class="material-symbols-outlined text-3xl opacity-40">sentiment_neutral</span>
    <span>暂无中奖记录，快去启动转盘试试手气吧！</span>
</div>
<?php endif; ?>
</div>

</div>
<a class="mt-5 w-full py-3 px-4 rounded-xl bg-primary/15 hover:bg-primary/25 border border-primary/30 text-primary text-xs font-semibold transition-all flex items-center justify-center gap-1.5 group flex-shrink-0" href="history.php">
<span class="">前往抽奖战绩 / 查看我的奖品</span>
<span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
</a>
</div>
</div>
</div>

<!-- Section 3: Annual Gala Prize List / Inventory Cards (项目奖品清单) -->
<section class="mt-2 bg-surface-container-low/70 border border-outline-variant/40 rounded-2xl p-6 sm:p-8 backdrop-blur-sm shadow-xl">
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-5 border-b border-outline-variant/30 mb-6"><div><h2 class="text-lg sm:text-xl font-headline-md font-semibold text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-tertiary">card_membership</span>本轮活动奖品池</h2><p class="text-xs text-on-surface-variant mt-1">当前项目【<?php echo htmlspecialchars($current_project['name'] ?? '未选择'); ?>】所包含的奖品配额及库存</p></div></div>
<!-- Tier Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
<?php 
// 根据中奖概率对奖品进行等级评估（概率越低越稀有珍贵）
// 提取所有奖品的不同概率值并升序排列以确定等级阶梯
$distinct_probs = array_values(array_unique(array_map(function($p) {
    return (float)($p['probability'] ?? 0);
}, $prizes)));
sort($distinct_probs); // 概率最低的排在最前

foreach ($prizes as $idx => $prize): 
    $is_infinite = ($prize['remaining_quantity'] >= 999999);
    $is_empty = (!$is_infinite && $prize['remaining_quantity'] <= 0);
    $prob = (float)($prize['probability'] ?? 0);
    
    // 寻找当前奖品在概率阶梯中的层级 (0为最稀有)
    $prob_rank = array_search($prob, $distinct_probs);
    if ($prob_rank === false) $prob_rank = 99;

    // 确定等级名称与主题样式（未开启等级显示时使用统一品质风格）
    if (empty($show_prize_level)) {
        $tier_name = '';
        $tier_tag_class = '';
        $tier_card_class = 'border-outline-variant/40 bg-surface-container hover:border-primary/40';
        $tier_icon_class = 'bg-primary-container/20 text-primary';
        $tier_icon = 'featured_seasonal_and_gifts';
    } elseif ($prob_rank === 0) {
        $tier_name = '特等奖';
        $tier_tag_class = 'bg-amber-500/25 text-amber-300 border border-amber-500/40 shadow-sm';
        $tier_card_class = 'border-amber-500/40 bg-gradient-to-b from-amber-500/10 to-surface-container';
        $tier_icon_class = 'bg-amber-500/20 text-amber-300';
        $tier_icon = 'trophy';
    } elseif ($prob_rank === 1) {
        $tier_name = '一等奖';
        $tier_tag_class = 'bg-purple-500/25 text-purple-300 border border-purple-500/40';
        $tier_card_class = 'border-purple-500/30 bg-gradient-to-b from-purple-500/10 to-surface-container';
        $tier_icon_class = 'bg-purple-500/20 text-purple-300';
        $tier_icon = 'workspace_premium';
    } elseif ($prob_rank === 2) {
        $tier_name = '二等奖';
        $tier_tag_class = 'bg-cyan-500/25 text-cyan-300 border border-cyan-500/40';
        $tier_card_class = 'border-cyan-500/30 bg-gradient-to-b from-cyan-500/10 to-surface-container';
        $tier_icon_class = 'bg-cyan-500/20 text-cyan-300';
        $tier_icon = 'military_tech';
    } elseif ($prob_rank === 3) {
        $tier_name = '三等奖';
        $tier_tag_class = 'bg-indigo-500/25 text-indigo-300 border border-indigo-500/40';
        $tier_card_class = 'border-outline-variant/40';
        $tier_icon_class = 'bg-indigo-500/20 text-indigo-300';
        $tier_icon = 'card_giftcard';
    } else {
        $tier_name = '幸运奖';
        $tier_tag_class = 'bg-surface-bright text-on-surface-variant border border-outline-variant/30';
        $tier_card_class = 'border-outline-variant/40';
        $tier_icon_class = 'bg-surface-bright text-primary';
        $tier_icon = 'redeem';
    }
?>
<div class="relative rounded-xl p-4 bg-surface-container border <?php echo $tier_card_class; ?> flex flex-col justify-between transition-all hover:border-primary/50 <?php echo $is_empty ? 'opacity-40' : ''; ?>">
<div class="flex items-center justify-between gap-1 mb-2">
  <?php if (!empty($show_prize_level)): ?>
    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $tier_tag_class; ?>"><?php echo $tier_name; ?></span>
  <?php endif; ?>
  <span class="text-[10px] text-on-surface-variant/80 font-mono <?php echo empty($show_prize_level) ? 'ml-auto' : ''; ?>"><?php echo number_format($prob, 1); ?>%</span>
</div>
<div>
<div class="w-12 h-12 rounded-xl <?php echo $tier_icon_class; ?> flex items-center justify-center mb-3">
<span class="material-symbols-outlined text-28px"><?php echo $tier_icon; ?></span>
</div>
<h4 class="font-headline-sm text-sm font-semibold text-on-surface truncate" title="<?php echo htmlspecialchars($prize['name']); ?>"><?php echo htmlspecialchars($prize['name']); ?></h4>
<p class="text-[11px] text-on-surface-variant mt-1">
    <?php if ($is_infinite): ?>
        库存充足
    <?php elseif ($is_empty): ?>
        已抢完
    <?php else: ?>
        剩余 <?php echo (int)$prize['remaining_quantity']; ?> 份
    <?php endif; ?>
</p>
</div>
</div>
<?php endforeach; ?>
<?php if (empty($prizes)): ?>
<div class="col-span-full text-center py-8 text-on-surface-variant text-xs">当前项目暂未配置奖品</div>
<?php endif; ?>
</div>
</section>
</div>

<!-- Win Result Modal Dialog -->
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-md hidden opacity-0 transition-opacity duration-300" id="win-modal">
<div class="bg-surface-container-high border border-outline-variant/60 p-8 rounded-2xl max-w-md w-full mx-4 flex flex-col items-center text-center transform scale-95 transition-transform duration-300 shadow-2xl relative" id="modal-content">
<div class="w-20 h-20 rounded-full bg-gradient-to-tr from-primary-container to-tertiary-container/60 flex items-center justify-center mb-4 text-tertiary shadow-[0_0_25px_rgba(249,189,34,0.35)]" id="modal-icon-wrap">
<span class="material-symbols-outlined text-[46px]" id="modal-icon">celebration</span>
</div>
<span class="text-xs uppercase tracking-wider text-primary font-bold mb-1" id="modal-sub">CONGRATULATIONS</span>
<h3 class="text-2xl font-headline-md text-on-surface font-bold mb-2" id="modal-title"><?php echo htmlspecialchars($current_project['name'] ?? '恭喜中奖！'); ?></h3>
<p class="text-body-sm text-on-surface-variant mb-6" id="win-message">
          您抽中了 <span class="text-tertiary font-bold text-base" id="win-prize-name"></span>！奖品已凭工号写入个人账户。
        </p>
<div class="flex gap-3 w-full">
<button class="flex-1 py-3 rounded-full bg-surface-container text-on-surface font-semibold text-xs hover:bg-surface-bright transition-all border border-outline-variant/40" onclick="closeModal()">
            好的，收下
          </button>
<button class="flex-1 py-3 rounded-full bg-gradient-to-r from-primary-container to-primary text-on-primary font-semibold text-xs hover:opacity-95 transition-all shadow-md" onclick="closeModal(); location.reload();">
            再抽一次
          </button>
</div>
</div>
</div>

<!-- Rules Modal Dialog -->
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-md hidden opacity-0 transition-opacity duration-300" id="rules-modal">
<div class="bg-surface-container-high border border-outline-variant/60 p-6 sm:p-8 rounded-2xl max-w-lg w-full mx-4 flex flex-col text-left transform scale-95 transition-transform duration-300 shadow-2xl">
<div class="flex items-center justify-between pb-3 border-b border-outline-variant/30 mb-4">
<h3 class="font-headline-sm text-base text-on-surface font-bold flex items-center gap-2">
<span class="material-symbols-outlined text-primary">rule</span>
            年终盛典抽奖规则说明
          </h3>
<button class="text-on-surface-variant hover:text-on-surface" onclick="closeRulesModal()">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<div class="space-y-3 text-xs text-on-surface-variant leading-relaxed">
<p class=""><strong class="text-on-surface">1. 资格与次数：</strong> 凡在册员工均享有管理员统一分配的项目专属抽奖配额，实时在线核验，扣完即止。</p>
<p class=""><strong class="text-on-surface">2. 结果公示与公证：</strong> 所有抽奖请求均由后端核心服务即时计算，现场与后台数据实时同步上链，杜绝暗箱操作。</p>
<p class=""><strong class="text-on-surface">3. 奖品领取与报销：</strong> 中奖后系统自动归档记录，员工可在“我的战绩/抽奖历史”中凭工号提交报销申请或由管理员直接审批发放。</p>
</div>
<button class="mt-6 w-full py-2.5 rounded-full bg-primary text-on-primary text-xs font-semibold hover:opacity-90 transition-all" onclick="closeRulesModal()">
          我已知晓
        </button>
</div>
</div>
</main>

<!-- Interactive JavaScript Logic -->
<script>
    let remainingChances = <?php echo (int)$remaining_times; ?>;
    let currentRotation = 0;
    let isRotating = false;
    const currentProjectId = <?php echo (int)$current_project_id; ?>;

    const segments = <?php echo json_encode($wheel_items, JSON_UNESCAPED_UNICODE); ?>;
    const anglePerSegment = 360 / segments.length;

    function updateQuotaDisplay(count) {
      // 更新大卡片中的剩余次数
      const quotaEl = document.getElementById('chance-count');
      if (quotaEl) {
        quotaEl.innerText = count;
      }
      // 同步更新顶部活动标签上的数字 (如：转盘 (8))
      const tabQuotaEl = document.getElementById(`project-quota-${currentProjectId}`);
      if (tabQuotaEl) {
        tabQuotaEl.innerText = `(${count})`;
      }
      const spinBtn = document.getElementById('spin-btn');
      if (spinBtn && count <= 0) {
        spinBtn.disabled = true;
      }
    }

    function spinWheel() {
      if (isRotating) return;
      if (remainingChances <= 0) {
        alert("您的抽奖配额已用尽，感谢参与！");
        return;
      }

      isRotating = true;
      const spinBtn = document.getElementById('spin-btn');
      const spinText = document.getElementById('spin-text');
      const spinIcon = document.getElementById('spin-icon');
      if (spinText) spinText.innerText = "开奖中...";
      if (spinIcon) spinIcon.innerText = "sync";

      // 向真实后端发起抽奖请求
      fetch('lottery.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ project_id: currentProjectId })
      })
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          alert(data.message || '抽奖失败，请重试');
          isRotating = false;
          if (spinText) spinText.innerText = "立即抽奖";
          if (spinIcon) spinIcon.innerText = "touch_app";
          return;
        }

        remainingChances = data.remaining_times;
        updateQuotaDisplay(remainingChances);

        const wheel = document.getElementById('wheel-element');
        
        // 查找实际抽中奖品对应的扇区索引（支持数字/字符串类型自适应及名称精准匹配）
        let targetIndex = -1;
        if (data.prize && data.prize.id !== undefined && data.prize.id !== null) {
          targetIndex = segments.findIndex(s => s.id == data.prize.id);
          if (targetIndex === -1 && data.prize.name) {
            targetIndex = segments.findIndex(s => s.name === data.prize.name);
          }
        }
        // 如果没找到或未中奖，定位到“谢谢参与”扇区
        if (targetIndex === -1) {
          targetIndex = segments.findIndex(s => !s.is_win || s.name === '谢谢参与');
          if (targetIndex === -1) targetIndex = segments.length - 1;
        }

        console.log('[抽奖调试] 后端开奖结果:', data.prize, '命中扇区序号:', targetIndex, '扇区对应奖品:', segments[targetIndex]);

        // 计算该扇区正中位置角度（相对于0度即3点钟方向顺时针角）
        const centerAngle = (targetIndex * anglePerSegment) + (anglePerSegment / 2);
        // 指针位于12点钟方向（即-90度或270度）。
        // 当转盘顺时针旋转 R 度后，原位于 centerAngle 的点的新位置为 (centerAngle + R) mod 360。
        // 为了让该点到达正上方 270度，必须满足: (centerAngle + R) mod 360 = 270
        // 即目标相对角度 targetMod = (270 - centerAngle + 360) mod 360
        const targetMod = ((270 - (centerAngle % 360) + 360) % 360);
        const currentMod = ((currentRotation % 360) + 360) % 360;
        let delta = targetMod - currentMod;
        if (delta <= 0) {
          delta += 360;
        }
        const extraLaps = 5 * 360; // 额外平滑转5圈
        currentRotation += extraLaps + delta;

        wheel.style.transform = `rotate(${currentRotation}deg)`;

        setTimeout(() => {
          isRotating = false;
          if (spinText) spinText.innerText = "立即抽奖";
          if (spinIcon) spinIcon.innerText = "touch_app";
          
          if (data.prize) {
            showModal(data.prize.name, true);
          } else {
            showModal('很遗憾，这次未中奖', false);
          }
        }, 4600);
      })
      .catch(err => {
        console.error(err);
        alert("网络通信异常，请重试！");
        isRotating = false;
        if (spinText) spinText.innerText = "立即抽奖";
        if (spinIcon) spinIcon.innerText = "touch_app";
      });
    }

    const currentProjectName = <?php echo json_encode($current_project['name'] ?? '幸运抽奖'); ?>;

    function showModal(prizeName, isWin) {
      const modal = document.getElementById('win-modal');
      const content = document.getElementById('modal-content');
      const msg = document.getElementById('win-message');
      const title = document.getElementById('modal-title');
      const sub = document.getElementById('modal-sub');
      const icon = document.getElementById('modal-icon');

      if (isWin) {
        sub.innerText = "CONGRATULATIONS";
        title.innerText = currentProjectName;
        icon.innerText = "celebration";
        msg.innerHTML = `恭喜您在【${currentProjectName}】中抽中 <span class="text-tertiary font-bold text-base">${prizeName}</span>！奖品已凭工号存入记录。`;
      } else {
        sub.innerText = "THANK YOU FOR PARTICIPATING";
        title.innerText = currentProjectName;
        icon.innerText = "sentiment_satisfied";
        msg.innerHTML = `<span class="text-on-surface-variant">${prizeName}，好运就在下一次！</span>`;
      }

      modal.classList.remove('hidden');
      setTimeout(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
      }, 10);
    }

    function closeModal() {
      const modal = document.getElementById('win-modal');
      const content = document.getElementById('modal-content');
      modal.classList.add('opacity-0');
      content.classList.remove('scale-100');
      content.classList.add('scale-95');
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 300);
    }

    function openRulesModal() {
      const modal = document.getElementById('rules-modal');
      const content = modal.querySelector('div');
      modal.classList.remove('hidden');
      setTimeout(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
        content.classList.add('scale-100');
      }, 10);
    }

    function closeRulesModal() {
      const modal = document.getElementById('rules-modal');
      const content = modal.querySelector('div');
      modal.classList.add('opacity-0');
      content.classList.remove('scale-100');
      content.classList.add('scale-95');
      setTimeout(() => {
        modal.classList.add('hidden');
      }, 300);
    }
</script>
<!-- Global Footer -->
<footer class="w-full bg-surface-container-lowest border-t border-outline-variant/30 py-space-xl mt-auto">
<div class="max-w-7xl mx-auto px-gutter flex flex-col sm:flex-row items-center justify-between gap-4 text-on-surface-variant text-body-sm">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-primary"></span>
<span class=""><?php echo SITE_NAME; ?> 组委会技术支持</span>
</div>
<div class="">© <?php echo date('Y'); ?> 内部系统严禁外传</div>
</div>
</footer>
</body></html>
