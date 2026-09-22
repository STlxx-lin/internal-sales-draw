<?php
// Shared reimbursement rules. This file does not open a database connection.
function expenseAmount($value, $allowZero = false) {
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        throw new InvalidArgumentException('报销金额格式错误');
    }
    $value = trim((string)$value);
    if (!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
        throw new InvalidArgumentException('报销金额需为最多两位小数，且不能超过99999999.99元');
    }
    $amount = $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
    if (!$allowZero && $amount === '0.00') {
        throw new InvalidArgumentException('报销金额必须大于0');
    }
    return $amount;
}

function expenseEditableFrom($record) {
    $applied = strtotime($record['applied_at'] ?? '');
    return $applied === false ? PHP_INT_MAX : strtotime('+2 days', $applied);
}

function expenseCanEdit($record, $now = null) {
    return in_array($record['status'], ['pending', 'rejected'], true)
        && empty($record['is_used']) && ($now ?? time()) >= expenseEditableFrom($record);
}

function expenseCsrfToken() {
    if (empty($_SESSION['expense_csrf'])) {
        $_SESSION['expense_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['expense_csrf'];
}

function expenseCheckCsrf($token) {
    if (!is_string($token) || empty($_SESSION['expense_csrf']) || !hash_equals($_SESSION['expense_csrf'], $token)) {
        throw new InvalidArgumentException('页面已失效，请刷新后重新提交');
    }
}

function expenseUpload($file) {
    if (!$file || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) { return null; }
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('附件上传失败，请检查文件大小后重试');
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_string($tmp) || !is_uploaded_file($tmp) || filesize($tmp) > 10 * 1024 * 1024 || filesize($tmp) === 0) {
        throw new InvalidArgumentException('附件无效或超过10MB');
    }
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('附件仅支持 JPG、PNG 或 PDF');
    }
    $directory = __DIR__ . '/uploads/expenses';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('无法创建附件目录');
    }
    $relative = 'uploads/expenses/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($tmp, __DIR__ . '/' . $relative)) {
        throw new RuntimeException('附件保存失败，请重试');
    }
    return $relative;
}

// Only remove a new upload after its database operation failed; keep audited attachments.
function expenseDiscardUpload($path) {
    if (is_string($path) && preg_match('~^uploads/expenses/[a-f0-9]{32}\.(jpg|png|pdf)$~D', $path)) {
        @unlink(__DIR__ . '/' . $path);
    }
}

function expenseLockRecord($pdo, $id) {
    $stmt = $pdo->prepare('SELECT * FROM expense_records WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) { throw new InvalidArgumentException('报销记录不存在，请刷新列表'); }
    return $record;
}

function expenseAudit($pdo, $id, $editor, $reason, $old, $new) {
    $stmt = $pdo->prepare('INSERT INTO expense_edit_history (expense_id, editor, edit_reason, old_data, new_data) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$id, $editor, $reason, json_encode($old, JSON_UNESCAPED_UNICODE), json_encode($new, JSON_UNESCAPED_UNICODE)]);
}

function expenseApprove($pdo, $id, $status, $approver) {
    if (!in_array($status, ['approved', 'rejected'], true) || $approver === '') {
        throw new InvalidArgumentException('审批状态或审批人无效');
    }
    $record = expenseLockRecord($pdo, $id);
    if ($record['status'] !== 'pending' || !empty($record['is_used'])) {
        throw new InvalidArgumentException('该记录已处理，请刷新列表，仅待审批记录可以审批');
    }
    $stmt = $pdo->prepare('UPDATE expense_records SET status = ?, approver = ?, approved_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $approver, $id]);
    expenseAudit($pdo, $id, $approver, '审批：' . ($status === 'approved' ? '通过' : '拒绝'),
        ['status' => $record['status'], 'approver' => $record['approver']],
        ['status' => $status, 'approver' => $approver]);
}

function expenseQuickCreate($pdo, $recordId, $amount, $appliedAt, $attachment) {
    // The lottery row serializes submissions across different administrator sessions.
    $stmt = $pdo->prepare('SELECT lr.*, u.name AS user_name, p.name AS prize_name FROM lottery_records lr JOIN users u ON lr.user_id = u.id LEFT JOIN prizes p ON lr.prize_id = p.id WHERE lr.id = ? AND lr.prize_id IS NOT NULL FOR UPDATE');
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();
    if (!$record) { throw new InvalidArgumentException('该记录不存在或未中奖'); }
    // A numeric boundary avoids matching #1 with #10; locking read sees the latest commit.
    $stmt = $pdo->prepare("SELECT id FROM expense_records WHERE project_id = ? AND user_id = ? AND is_used = 1 AND reason REGEXP ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$record['project_id'], $record['user_id'], '抽奖记录 #' . (int)$recordId . '([^0-9]|$)']);
    if ($stmt->fetch()) { throw new InvalidArgumentException('该中奖记录已报销，请刷新列表'); }
    $stmt = $pdo->prepare("INSERT INTO expense_records (project_id, user_id, expense_name, amount, reason, status, applicant, is_used, applied_at, attachment, approver, approved_at) VALUES (?, ?, ?, ?, ?, 'approved', ?, 1, ?, ?, '管理员', NOW())");
    $stmt->execute([$record['project_id'], $record['user_id'], $record['prize_name'] . ' - 快速报销',
        $amount, '由抽奖记录 #' . (int)$recordId . ' 快速报销生成', $record['user_name'], $appliedAt, $attachment]);
    $id = $pdo->lastInsertId();
    expenseAudit($pdo, $id, '管理员', '快速报销', [], ['status' => 'approved', 'amount' => $amount, 'lottery_record_id' => (int)$recordId]);
    return $id;
}
