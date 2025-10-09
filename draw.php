<?php
require_once 'config.php';

// 获取用户IP
function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        // 处理可能包含多个IP地址的情况，取第一个
        $ipaddress = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    
    $ipaddress = trim($ipaddress);
    // 处理IPv6本地回环地址转换为IPv4格式
    if ($ipaddress === '::1') {
        $ipaddress = '127.0.0.1';
    }
    return $ipaddress;
}

$user_ip = get_client_ip();
$user = null;
$message = '';
$can_draw = false;

// 获取所有项目
$projects = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM projects");
    $projects = $stmt->fetchAll();
    if (empty($projects)) {
        $message = '<div class="bg-yellow-100 text-yellow-700 p-3 rounded">没有可用的抽奖活动，请先创建抽奖活动</div>';
        $can_draw = false;
    }
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 text-red-700 p-3 rounded">获取抽奖活动失败: ' . $e->getMessage() . '</div>';
    $can_draw = false;
}

// 检查是否可以抽奖
    try {
        $project_id = $_GET['project_id'] ?? null;
        $query = "SELECT SUM(remaining) as total_remaining FROM prizes";
        $params = [];
        
        if ($project_id) {
            $query .= " WHERE project_id = ?";
            $params = [$project_id];
        }
        
        $stmt = $project_id ? $pdo->prepare($query) : $pdo->query($query);
        if ($project_id) {
            $stmt->execute($params);
        }
        
        $result = $stmt->fetch();
        $total_remaining = $result['total_remaining'] ?? 0;

        if ($total_remaining > 0) {
            $can_draw = true;
        } else {
            if ($project_id) {
                $message = '<div class="bg-yellow-100 text-yellow-700 p-3 rounded">当前抽奖活动没有可用奖品，请选择其他活动或等待管理员添加奖品</div>';
            } else {
                $message = '<div class="bg-yellow-100 text-yellow-700 p-3 rounded">请先选择抽奖活动</div>';
            }
        }
    } catch (PDOException $e) {
        $message = '<div class="bg-red-100 text-red-700 p-3 rounded">获取奖品信息失败: ' . $e->getMessage() . '</div>';
    }

// 处理抽奖请求
$lottery_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'draw' && $user && $can_draw) {
    try {
        // 开启事务
        $pdo->beginTransaction();

        // 获取所选项目的所有有剩余的奖品
        $project_id = $_GET['project_id'] ?? null;
        if (!$project_id) {
            throw new Exception("请先选择抽奖活动");
        }
        $stmt = $pdo->prepare("SELECT * FROM prizes WHERE remaining > 0 AND project_id = ?");
        $stmt->execute([$project_id]);
        $available_prizes = $stmt->fetchAll();

        if (empty($available_prizes)) {
            throw new Exception("没有可用奖品");
        }

        // 简单随机抽取一个奖品
        $random_index = array_rand($available_prizes);
        $selected_prize = $available_prizes[$random_index];

        // 更新奖品剩余数量
        $stmt = $pdo->prepare("UPDATE prizes SET remaining = remaining - 1 WHERE id = ? AND remaining > 0");
        $stmt->execute([$selected_prize['id']]);

        // 检查是否更新成功
        if ($stmt->rowCount() === 0) {
            throw new Exception("奖品已被抽完，请重试");
        }

        // 记录抽奖结果
        $stmt = $pdo->prepare("INSERT INTO lottery_records (ip_address, prize_id, is_winner, project_id) VALUES (?, ?, 1, ?)");
        $stmt->execute([$user_ip, $selected_prize['id'], $project_id]);

        // 提交事务
        $pdo->commit();

        // 准备抽奖结果
        $lottery_result = [
            'prize_name' => $selected_prize['name'],
            'image_url' => $selected_prize['image_url']
        ];
        // 重定向到结果页面
        header("Location: result.php?success=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = '<div class="bg-red-100 text-red-700 p-3 rounded">抽奖失败: ' . $e->getMessage() . '</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = '<div class="bg-red-100 text-red-700 p-3 rounded">' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>开始抽奖 - 销售部门内部抽奖系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <style>
        .lottery-wheel {
            position: relative;
            width: 300px;
            height: 300px;
            margin: 0 auto;
            border-radius: 50%;
            overflow: hidden;
            border: 8px solid #3b82f6;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
            transition: transform 8s cubic-bezier(0.1, 0.8, 0.2, 1);
        }
        .lottery-part {
            position: absolute;
            width: 50%;
            height: 50%;
            transform-origin: bottom right;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .lottery-part span {
            position: absolute;
            width: 141.42%;
            text-align: center;
            transform-origin: bottom left;
            font-weight: bold;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);

        }
        .lottery-pointer {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 30px;
            height: 30px;
            background-color: #ff6b6b;
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            z-index: 10;
        }
        .btn-draw:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        @keyframes pulse1 {
            0% { transform: scale(0.95); opacity: 0.7; }
            50% { transform: scale(1); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.7; }
        }
        .animate-pulse-slow {
            animation: pulse1 2s infinite;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <!-- 导航栏 -->
    <nav class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <a href="index.php" class="text-2xl font-bold text-blue-600 flex items-center">
                    <i class="fa fa-gift mr-2"></i>抽奖系统
                </a>
                <div class="hidden md:flex space-x-6">
                    <a href="index.php" class="font-medium text-gray-700 hover:text-blue-600">首页</a>
                    <a href="admin/prizes.php" class="font-medium text-gray-700 hover:text-blue-600">设置奖品</a>
                    <a href="draw.php" class="font-medium text-blue-600">开始抽奖</a>
                    <a href="result.php" class="font-medium text-gray-700 hover:text-blue-600">抽奖结果</a>
                    <a href="history.php" class="font-medium text-gray-700 hover:text-blue-600">抽奖历史</a>
                </div>
                <button class="md:hidden text-gray-700 focus:outline-none" id="menuBtn">
                    <i class="fa fa-bars text-xl"></i>
                </button>
            </div>
        </div>
        <!-- 移动端菜单 -->
        <div class="md:hidden hidden bg-white shadow-lg absolute w-full" id="mobileMenu">
            <div class="container mx-auto px-4 py-2 flex flex-col space-y-3">
                <a href="index.php" class="py-2 font-medium text-gray-700 hover:text-blue-600">首页</a>
                <a href="admin/prizes.php" class="py-2 font-medium text-gray-700 hover:text-blue-600">设置奖品</a>
                <a href="draw.php" class="py-2 font-medium text-blue-600">开始抽奖</a>
                <a href="result.php" class="py-2 font-medium text-gray-700 hover:text-blue-600">抽奖结果</a>
                <a href="history.php" class="py-2 font-medium text-gray-700 hover:text-blue-600">抽奖历史</a>
            </div>
        </div>
    </nav>

    <!-- 主要内容区 -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">
                <i class="fa fa-random text-blue-600 mr-3"></i>开始抽奖
            </h1>

            <?php echo $message; ?>

            <?php if (count($projects) > 0): ?>
                <form method="get" id="projectForm" class="mb-8">
                    <div class="mb-4">
                        <label for="project_id" class="block text-gray-700 font-medium mb-2">选择抽奖活动</label>
                        <select id="project_id" name="project_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                            <option value="">-- 请选择抽奖活动 --</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo isset($_GET['project_id']) && $_GET['project_id'] == $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            <?php endif; ?>

            <!-- 抽奖区域 -->
            <div class="bg-white rounded-xl shadow-md p-6 relative overflow-hidden">
                <?php if ($can_draw): ?>
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">幸运大转盘</h2>
                        <p class="text-gray-600">点击下方按钮开始抽奖，祝您好运！</p>
                    </div>

                    <div class="relative max-w-md mx-auto">
                        <div id="lotteryWheel" class="lottery-wheel">
                            <?php
                            // 获取奖品并生成转盘扇区
                            
                            $prizes = [];
                            $prize_count = 0;
                            $angle = 0;
                            if (isset($_GET['project_id'])) {
                                $stmt = $pdo->prepare("SELECT * FROM prizes WHERE remaining > 0 AND project_id = ?");
                                $stmt->execute([$_GET['project_id']]);
                                $prizes = $stmt->fetchAll();
                                $prize_count = count($prizes);
                                if ($prize_count > 0) {
                                    $angle = 360 / $prize_count;
                                }
                            }
                            ?>
                            <?php if ($prize_count > 0): ?>
                            <?php else: ?>
                                <div class="text-center py-12">
                                    <i class="fa fa-gift text-5xl text-gray-300 mb-4"></i>
                                    <h3 class="text-xl font-bold text-gray-700 mb-2">当前抽奖活动暂无奖品</h3>
                                    <p class="text-gray-500">请选择其他抽奖活动或联系管理员添加奖品</p>
                                </div>
                            <?php endif; ?>
                            <?php if ($prize_count > 0): ?>
                                <?php
                                $colors = ['#4F46E5', '#EC4899', '#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6', '#14B8A6'];

                                for ($i = 0; $i < $prize_count; $i++) {
                                $prize = $prizes[$i];
                                $color_index = $i % count($colors);
                                $start_angle = $i * $angle;
                                $text_angle = $start_angle + $angle / 2;
                                $rotation = $text_angle + 90;
                            ?>
                                <div class="lottery-part" style="transform: rotate(<?php echo $start_angle; ?>deg)">
                                    <div style="background-color: <?php echo $colors[$color_index]; ?>; width: 100%; height: 100%; transform: skewY(<?php echo 90 - $angle; ?>deg);"></div>
                                    <span style="transform: rotate(<?php echo $rotation; ?>deg); top: <?php echo $angle > 30 ? '60%' : '75%'; ?>; left: 0;"><?php echo htmlspecialchars($prize['name']); ?></span>
                                </div>
                            <?php }
                            ?>
                        </div>
                        <div class="lottery-pointer"></div>
                    </div>
                      <?php endif; ?></div>
                      <?php endif; ?></div>
                      <?php endif; ?>

                      <div class="text-center mt-10">
                        <form method="post" id="lotteryForm">
                            <input type="hidden" name="action" value="draw">
                            <?php if (isset($_GET['project_id'])): ?>
                                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($_GET['project_id']); ?>">
                            <?php endif; ?>
                            <button type="submit" id="drawBtn" class="btn-draw bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-10 rounded-full transition duration-300 transform hover:scale-105 animate-pulse-slow text-lg">
                                <i class="fa fa-play-circle mr-2"></i>开始抽奖
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fa fa-gift text-5xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-700 mb-2">暂时无法抽奖</h3>
                        <p class="text-gray-500">请联系管理员添加奖品后再试</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- 页脚 -->
    <footer class="bg-gray-800 text-white py-8 mt-auto">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p class="text-gray-400">&copy; <?php echo date('Y'); ?> 公司销售部门内部抽奖系统 - 仅供内部使用</p>
                </div>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                        <i class="fa fa-question-circle"></i> 帮助中心
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white transition duration-300">
                        <i class="fa fa-shield"></i> 隐私政策
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- 修改用户信息模态框 -->
    <div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">修改用户信息</h3>
                <button id="closeModalBtn" class="text-gray-500 hover:text-gray-700">
                    <i class="fa fa-times text-xl"></i>
                </button>
            </div>
            <form method="post" id="updateUserForm">
                <input type="hidden" name="action" value="bind_user">
                <div class="mb-4">
                    <label for="updateUsername" class="block text-gray-700 font-medium mb-2">姓名</label>
                    <input type="text" id="updateUsername" name="username" value="<?php echo $user ? htmlspecialchars($user['username']) : ''; ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">IP地址</label>
                    <p class="text-gray-600 bg-gray-100 px-4 py-2 rounded-lg"><?php echo htmlspecialchars($user_ip); ?></p>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="cancelBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-lg transition duration-300 mr-3">取消</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-300">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 移动端菜单切换
        document.getElementById('menuBtn').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            mobileMenu.classList.toggle('hidden');
        });

        // 用户信息模态框
        document.getElementById('editUserBtn')?.addEventListener('click', function() {
            document.getElementById('userModal').classList.remove('hidden');
        });

        document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
        document.getElementById('cancelBtn')?.addEventListener('click', closeModal);

        function closeModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        // 点击模态框外部关闭
        document.getElementById('userModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // 抽奖动画
        document.getElementById('lotteryForm')?.addEventListener('submit', function(e) {
            const drawBtn = document.getElementById('drawBtn');
            const lotteryWheel = document.getElementById('lotteryWheel');

            // 防止重复提交
            if (drawBtn.disabled) return;
            drawBtn.disabled = true;
            drawBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>抽奖中...';

            // 添加旋转动画
            const rotateDeg = 3600 + Math.floor(Math.random() * 360);
            lotteryWheel.style.transform = `rotate(${rotateDeg}deg)`;

            // 阻止表单立即提交
            e.preventDefault();

            // 动画结束后提交表单
            setTimeout(() => {
                this.submit();
            }, 8000); // 8秒后提交表单
        });
    </script>
</body>
</html>