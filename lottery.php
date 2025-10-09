<?php
require_once 'config.php';

header('Content-Type: application/json');

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '请求方法不允许']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['project_id'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$project_id = (int)$input['project_id'];
$user_ip = getUserIP();
$user = checkUser($pdo, $user_ip);

// 检查用户是否存在
if (!$user) {
    echo json_encode(['success' => false, 'message' => '用户不存在，请先注册']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 检查项目是否存在且启用
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND status = 1");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        throw new Exception('项目不存在或已禁用');
    }
    
    // 检查用户是否有抽奖次数
    $stmt = $pdo->prepare("SELECT * FROM user_project_times WHERE user_id = ? AND project_id = ? AND is_visible = 1");
    $stmt->execute([$user['id'], $project_id]);
    $user_project = $stmt->fetch();
    
    if (!$user_project || $user_project['remaining_times'] <= 0) {
        throw new Exception('您没有剩余的抽奖次数');
    }
    
    // 获取所有可用奖品
    $stmt = $pdo->prepare("SELECT * FROM prizes WHERE project_id = ? AND remaining_quantity > 0 ORDER BY sort_order, id");
    $stmt->execute([$project_id]);
    $available_prizes = $stmt->fetchAll();
    
    // 执行抽奖逻辑
    $won_prize = null;
    $total_probability = 0;
    
    // 计算总概率
    foreach ($available_prizes as $prize) {
        $total_probability += $prize['probability'];
    }
    
    // 生成随机数进行抽奖
    if ($total_probability > 0) {
        $random = mt_rand(1, 10000) / 100; // 生成0.01-100.00的随机数
        $current_probability = 0; // 初始化当前概率
        
        foreach ($available_prizes as $prize) {
            $current_probability += $prize['probability']; // 计算当前概率
            if ($random <= $current_probability) {
                $won_prize = $prize;  // 中奖奖品
                break;
            }
        }
    }
    
    // 更新用户剩余次数
    $stmt = $pdo->prepare("UPDATE user_project_times SET remaining_times = remaining_times - 1 WHERE user_id = ? AND project_id = ?");
    $stmt->execute([$user['id'], $project_id]);
    
    // 如果中奖且不是"没有中奖"奖品，更新奖品剩余数量（无限数量的奖品不扣减）
    $is_no_prize = $won_prize && $won_prize['name'] === '没有中奖';
    if ($won_prize && !$is_no_prize && $won_prize['remaining_quantity'] < 999999) {
        $stmt = $pdo->prepare("UPDATE prizes SET remaining_quantity = remaining_quantity - 1 WHERE id = ?");
        $stmt->execute([$won_prize['id']]);
    }
    
    // 记录抽奖历史（如果是"没有中奖"奖品，记录为NULL）
    $prize_id_to_record = ($won_prize && !$is_no_prize) ? $won_prize['id'] : null;
    $stmt = $pdo->prepare("INSERT INTO lottery_records (user_id, project_id, prize_id, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['id'], $project_id, $prize_id_to_record, $user_ip]);
    
    $pdo->commit();
    
    // 返回结果（如果是"没有中奖"奖品，返回null）
    $response = [
        'success' => true,
        'prize' => ($won_prize && !$is_no_prize) ? [
            'id' => $won_prize['id'],
            'name' => $won_prize['name'],
            'image' => $won_prize['image']
        ] : null,
        'remaining_times' => $user_project['remaining_times'] - 1
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => '数据库错误，请重试']);
}
?>