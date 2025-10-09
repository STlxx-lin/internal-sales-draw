<?php
require_once 'config.php';

$user_ip = getUserIP();
$user = checkUser($pdo, $user_ip);

// 如果用户已存在，重定向到首页
if ($user) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $error = '请输入您的姓名';
    } elseif (strlen($name) < 2) {
        $error = '姓名至少需要2个字符';
    } else {
        try {
            // 创建用户
            $stmt = $pdo->prepare("INSERT INTO users (name, ip_address) VALUES (?, ?)");
            $stmt->execute([$name, $user_ip]);
            
            $success = '注册成功！正在跳转到抽奖页面...';
            
            // 3秒后跳转
            header('refresh:3;url=index.php');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // 重复键错误
                $error = '该IP地址已经注册过了';
            } else {
                $error = '注册失败，请重试';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户注册 - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-purple-400 via-pink-500 to-red-500 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <i class="fas fa-user-plus text-5xl text-blue-500 mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-800"><?php echo SITE_NAME; ?></h1>
            <p class="text-gray-600 mt-2">欢迎参与抽奖活动</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-2"></i>请输入您的姓名
                </label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                       placeholder="请输入您的真实姓名"
                       required
                       maxlength="100">
            </div>

            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="text-sm text-gray-600">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>注册说明：</strong>
                </div>
                <ul class="text-sm text-gray-600 mt-2 space-y-1">
                    <li>• 系统将自动绑定您的IP地址</li>
                    <li>• 每个IP地址只能注册一次</li>
                    <li>• 请使用常用姓名进行注册</li>
                    <li>• 注册后即可参与抽奖活动</li>
                </ul>
            </div>

            <div class="text-center">
                <div class="text-sm text-gray-500 mb-4">
                    当前IP地址：<?php echo htmlspecialchars($user_ip); ?>
                </div>
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-500 to-purple-600 text-white py-3 px-6 rounded-lg font-medium hover:from-blue-600 hover:to-purple-700 transition-all transform hover:scale-105 shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    立即注册
                </button>
            </div>
        </form>

        <div class="mt-8 text-center">
            <a href="admin.php" class="text-blue-500 hover:text-blue-600 text-sm">
                <i class="fas fa-cog mr-1"></i>
                管理员入口
            </a>
        </div>
    </div>

    <script>
    // 自动聚焦到姓名输入框
    document.getElementById('name').focus();
    
    // 表单验证
    document.querySelector('form').addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        if (name.length < 2) {
            e.preventDefault();
            alert('姓名至少需要2个字符');
            return false;
        }
    });
    </script>
</body>
</html>