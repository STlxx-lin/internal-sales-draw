<?php
// 报销管理AJAX接口
session_start();
require_once 'config.php';
require_once __DIR__ . '/expense_service.php';

// 验证登录态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}
session_write_close();

$action = $_GET['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

try {
switch ($action) {
    case 'history':
        // 获取某条报销记录的修改历史
        $expense_id = (int)($_GET['id'] ?? 0);
        if ($expense_id <= 0) {
            echo json_encode(['success' => false, 'message' => '参数错误']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM expense_edit_history WHERE expense_id = ? ORDER BY created_at DESC, id DESC");
        $stmt->execute([$expense_id]);
        $history = $stmt->fetchAll();

        echo json_encode(['success' => true, 'history' => $history], JSON_UNESCAPED_UNICODE);
        break;

    case 'check_editable':
        // 检查某条记录是否可编辑
        $expense_id = (int)($_GET['id'] ?? 0);
        if ($expense_id <= 0) {
            echo json_encode(['success' => false, 'message' => '参数错误']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM expense_records WHERE id = ?");
        $stmt->execute([$expense_id]);
        $record = $stmt->fetch();

        if (!$record) {
            echo json_encode(['success' => false, 'message' => '记录不存在']);
            break;
        }

        $two_days_after = expenseEditableFrom($record);

        echo json_encode([
            'success' => true,
            'editable' => expenseCanEdit($record),
            'editable_from' => date('Y-m-d H:i', $two_days_after),
            'status' => $record['status']
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '未知操作']);
}
} catch (Throwable $e) {
    error_log('Expense query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '查询失败，请稍后重试']);
}
