<?php
// 报销管理AJAX接口
session_start();
require_once 'config.php';

// 验证登录态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$action = $_GET['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'history':
        // 获取某条报销记录的修改历史
        $expense_id = (int)($_GET['id'] ?? 0);
        if ($expense_id <= 0) {
            echo json_encode(['success' => false, 'message' => '参数错误']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM expense_edit_history WHERE expense_id = ? ORDER BY created_at DESC");
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

        $applied_date = strtotime($record['applied_at']);
        $two_days_after = strtotime('+2 days', $applied_date);
        $now = time();

        echo json_encode([
            'success' => true,
            'editable' => ($now >= $two_days_after) && in_array($record['status'], ['pending', 'rejected']),
            'editable_from' => date('Y-m-d H:i', $two_days_after),
            'status' => $record['status']
        ], JSON_UNESCAPED_UNICODE);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '未知操作']);
}
