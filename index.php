<?php
require_once 'config.php';

$user_ip = getUserIP();
$user = checkUser($pdo, $user_ip);

// 如果用户不存在，重定向到用户注册页面
if (!$user) {
    header('Location: register.php');
    exit;
}

// 获取所有启用的项目
$stmt = $pdo->prepare("SELECT p.* FROM projects p WHERE p.status = 1 ORDER BY p.id");
$stmt->execute();
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

// 检查用户是否在当前项目中可见
$stmt = $pdo->prepare("SELECT * FROM user_project_times WHERE user_id = ? AND project_id = ? AND is_visible = 1");
$stmt->execute([$user['id'], $current_project_id]);
$user_project = $stmt->fetch();

$remaining_times = $user_project ? $user_project['remaining_times'] : 0;

// 获取当前项目的奖品
$stmt = $pdo->prepare("SELECT * FROM prizes WHERE project_id = ? ORDER BY sort_order, id");
$stmt->execute([$current_project_id]);
$prizes = $stmt->fetchAll();

// 获取用户的抽奖历史（最近10条）
$stmt = $pdo->prepare("
    SELECT lr.*, p.name as prize_name, pr.name as project_name 
    FROM lottery_records lr 
    LEFT JOIN prizes p ON lr.prize_id = p.id 
    JOIN projects pr ON lr.project_id = pr.id
    WHERE lr.user_id = ? 
    ORDER BY lr.created_at DESC 
    LIMIT 10
");
$stmt->execute([$user['id']]);
$recent_records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
    <script src="/assets/css/browser@4.js" ></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script> -->
    <script src="/assets/js/jquery-3.7.1.min.js" ></script>
    <link rel="stylesheet" href="/font-awesome-4.7.0/css/font-awesome.min.css">
    
    <link rel="stylesheet" href="/assets/css/tailwind.min.css">
    <!-- Font Awesome 4.7.0 configuration -->
    <style>
        /* Font Awesome 4.7.0 图标样式配置 */
        .fa {
            display: inline-block;
            font: normal normal normal 14px/1 FontAwesome;
            font-size: inherit;
            text-rendering: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* 确保图标字体正确加载 */
        @font-face {
            font-family: 'FontAwesome';
            src: url('/font-awesome-4.7.0/fonts/fontawesome-webfont.woff2') format('woff2'),
                 url('/font-awesome-4.7.0/fonts/fontawesome-webfont.woff') format('woff'),
                 url('/font-awesome-4.7.0/fonts/fontawesome-webfont.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        
        /* 常用图标大小 */
        .fa-lg { font-size: 1.33333em; line-height: 0.75em; vertical-align: -15%; }
        .fa-2x { font-size: 2em; }
        .fa-3x { font-size: 3em; }
        .fa-4x { font-size: 4em; }
        .fa-5x { font-size: 5em; }
        
        /* 图标旋转动画 */
        .fa-spin {
            -webkit-animation: fa-spin 2s infinite linear;
            animation: fa-spin 2s infinite linear;
        }
        
        @-webkit-keyframes fa-spin {
            0% { -webkit-transform: rotate(0deg); transform: rotate(0deg); }
            100% { -webkit-transform: rotate(359deg); transform: rotate(359deg); }
        }
        
        @keyframes fa-spin {
            0% { -webkit-transform: rotate(0deg); transform: rotate(0deg); }
            100% { -webkit-transform: rotate(359deg); transform: rotate(359deg); }
        }
    </style>
    <style>
        .lottery-wheel {
            animation: spin 3s ease-in-out;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(1440deg); }
        }
        .prize-item {
            transition: all 0.3s ease;
        }
        .prize-item:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-400 via-pink-500 to-red-500 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- 头部导航 -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800">
                    <i class="fas fa-star text-pink-500 mr-2"></i>
                    <?php echo SITE_NAME; ?>
                </h1>
                <div class="flex space-x-4">
                    <a href="admin.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-cog mr-2"></i>管理后台
                    </a>
                    <a href="history.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-history mr-2"></i>抽奖历史
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- 用户信息卡片 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-user text-blue-500 mr-2"></i>个人信息
                </h2>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <span class="text-gray-600 w-20">姓名：</span>
                        <span class="font-semibold"><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-gray-600 w-20">IP：</span>
                        <span class="text-sm text-gray-500"><?php echo htmlspecialchars($user['ip_address']); ?></span>
                    </div>
                </div>

                <!-- 项目选择 -->
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">选择抽奖项目</h3>
                    <div class="space-y-2">
                        <?php foreach ($projects as $project): ?>
                            <?php
                            // 检查用户在此项目中的可见性
                            $stmt = $pdo->prepare("SELECT remaining_times FROM user_project_times WHERE user_id = ? AND project_id = ? AND is_visible = 1");
                            $stmt->execute([$user['id'], $project['id']]);
                            $project_times = $stmt->fetch();
                            if (!$project_times) continue;
                            ?>
                            <a href="?project=<?php echo $project['id']; ?>" 
                               class="block p-3 rounded-lg border-2 transition <?php echo $project['id'] == $current_project_id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300'; ?>">
                                <div class="flex justify-between items-center">
                                    <span class="font-medium"><?php echo htmlspecialchars($project['name']); ?></span>
                                    <span class="text-sm text-gray-500">剩余 <?php echo $project_times['remaining_times']; ?> 次</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 抽奖区域 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-dice text-green-500 mr-2"></i>
                    <?php echo htmlspecialchars($current_project['name'] ?? '请选择项目'); ?>
                </h2>
                
                <?php if ($current_project && $user_project): ?>
                    <div class="text-center">
                        <div class="mb-6">
                            <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo $remaining_times; ?></div>
                            <div class="text-gray-600">剩余抽奖次数</div>
                        </div>

                        <div id="lottery-area" class="mb-6">
                            <button id="lottery-btn" 
                                    class="w-32 h-32 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full text-white text-xl font-bold shadow-lg hover:shadow-xl transition-all transform hover:scale-105 <?php echo $remaining_times <= 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo $remaining_times <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-gift text-3xl mb-2"></i><br>
                                开始抽奖
                            </button>
                        </div>

                        <div id="result-area" class="hidden">
                            <div id="result-content" class="p-6 bg-gradient-to-r from-green-400 to-blue-500 rounded-lg text-white text-center">
                                <!-- 抽奖结果将在这里显示 -->
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-exclamation-circle text-4xl mb-4"></i>
                        <p>您暂时无法参与此项目的抽奖</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 奖品展示 -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>奖品列表
                </h2>
                <div class="space-y-3">
                    <?php foreach ($prizes as $prize): ?>
                        <div class="prize-item p-3 border rounded-lg hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium"><?php echo htmlspecialchars($prize['name']); ?></div>
                                    <div class="text-sm text-gray-500">剩余：<?php echo $prize['remaining_quantity'] >= 999999 ? '无限' : $prize['remaining_quantity']; ?> 个</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm text-blue-600"><?php echo $prize['probability']; ?>%</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 最近抽奖记录 -->
        <?php if (!empty($recent_records)): ?>
        <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-list text-purple-500 mr-2"></i>最近抽奖记录
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2">时间</th>
                            <th class="text-left py-2">项目</th>
                            <th class="text-left py-2">结果</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_records as $record): ?>
                        <tr class="border-b">
                            <td class="py-2"><?php echo date('m-d H:i', strtotime($record['created_at'])); ?></td>
                            <td class="py-2"><?php echo htmlspecialchars($record['project_name']); ?></td>
                            <td class="py-2">
                                <?php if ($record['prize_name']): ?>
                                    <span class="text-green-600 font-medium"><?php echo htmlspecialchars($record['prize_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-gray-500">未中奖</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    document.getElementById('lottery-btn')?.addEventListener('click', function() {
        if (this.disabled) return;
        
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin text-3xl mb-2"></i><br>抽奖中...';
        this.classList.add('lottery-wheel');
        
        // 发送抽奖请求
        fetch('lottery.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                project_id: <?php echo $current_project_id; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            setTimeout(() => {
                this.classList.remove('lottery-wheel');
                
                if (data.success) {
                    // 显示抽奖结果
                    const resultArea = document.getElementById('result-area');
                    const resultContent = document.getElementById('result-content');
                    
                    if (data.prize) {
                        resultContent.innerHTML = `
                            <i class="fas fa-trophy text-4xl mb-4"></i>
                            <h3 class="text-2xl font-bold mb-2">恭喜中奖！</h3>
                            <p class="text-lg">${data.prize.name}</p>
                            ${data.remaining_times > 0 ? 
                                '<button onclick="location.reload()" class="mt-4 bg-white text-blue-600 px-6 py-2 rounded-lg font-medium hover:bg-gray-100 transition">继续抽奖</button>' : 
                                '<p class="mt-4 text-sm opacity-75">抽奖次数已用完</p>'}
                        `;
                        resultContent.className = 'p-6 bg-gradient-to-r from-green-400 to-blue-500 rounded-lg text-white text-center';
                    } else {
                        resultContent.innerHTML = `
                            <i class="fas fa-heart text-4xl mb-4"></i>
                            <h3 class="text-2xl font-bold mb-2">谢谢参与！</h3>
                            <p class="text-lg">很遗憾，这次没有中奖</p>
                            ${data.remaining_times > 0 ? 
                                '<button onclick="location.reload()" class="mt-4 bg-white text-gray-600 px-6 py-2 rounded-lg font-medium hover:bg-gray-100 transition">继续抽奖</button>' : 
                                '<p class="mt-4 text-sm opacity-75">抽奖次数已用完</p>'}
                        `;
                        resultContent.className = 'p-6 bg-gradient-to-r from-gray-400 to-gray-600 rounded-lg text-white text-center';
                    }
                    
                    document.getElementById('lottery-area').classList.add('hidden');
                    resultArea.classList.remove('hidden');
                } else {
                    alert(data.message || '抽奖失败，请重试');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-gift text-3xl mb-2"></i><br>开始抽奖';
                }
            }, 3000);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('网络错误，请重试');
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-gift text-3xl mb-2"></i><br>开始抽奖';
            this.classList.remove('lottery-wheel');
        });
    });
    </script>
</body>
</html>