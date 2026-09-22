<?php
// users.php - 用户管理独立页面
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/admin_records.php';

$message = '';
$error = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'add_user':
                $name = trim($_POST['name'] ?? '');
                $ip = trim($_POST['ip'] ?? '');
                if (empty($name)) throw new Exception('用户姓名不能为空');
                if (empty($ip)) throw new Exception('IP地址不能为空');
                
                $stmt = $pdo->prepare("INSERT INTO users (name, ip_address) VALUES (?, ?)");
                $stmt->execute([$name, $ip]);
                logAdminAction('add_user', '成功', ['name' => $name, 'ip_address' => $ip], $admin_log_file);
                header('Location: users.php?msg=' . urlencode('用户添加成功'));
                exit;

            case 'edit_user':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $ip = trim($_POST['ip'] ?? '');
                if (empty($name)) throw new Exception('用户姓名不能为空');
                if (empty($ip)) throw new Exception('IP地址不能为空');
                
                $stmt = $pdo->prepare("UPDATE users SET name = ?, ip_address = ? WHERE id = ?");
                $stmt->execute([$name, $ip, $id]);
                logAdminAction('edit_user', '成功', ['id' => $id, 'name' => $name, 'ip_address' => $ip], $admin_log_file);
                header('Location: users.php?msg=' . urlencode('用户更新成功'));
                exit;

            case 'delete_user':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                logAdminAction('delete_user', '成功', ['id' => $id], $admin_log_file);
                header('Location: users.php?msg=' . urlencode('用户已删除'));
                exit;

            case 'get_user_detail':
                header('Content-Type: application/json; charset=utf-8');
                $user_id = (int)($_POST['user_id'] ?? 0);
                if (!$user_id) {
                    throw new Exception('用户ID不能为空');
                }
                $stmt = $pdo->prepare("SELECT id, name, ip_address, created_at FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if (!$user) {
                    throw new Exception('用户不存在');
                }

                $stmt = $pdo->prepare("
                    SELECT upt.*, pr.name as project_name
                    FROM user_project_times upt
                    JOIN projects pr ON upt.project_id = pr.id
                    WHERE upt.user_id = ?
                    ORDER BY pr.name
                ");
                $stmt->execute([$user_id]);
                $times_list = $stmt->fetchAll();

                $stmt = $pdo->prepare("
                    SELECT lr.id, lr.project_id, lr.prize_id, lr.created_at, pr.name as project_name, p.name as prize_name,
                           er.id as expense_id, er.amount as expense_amount, er.applied_at as expense_applied_at,
                           (er.id IS NOT NULL) as is_used
                    FROM lottery_records lr
                    JOIN projects pr ON lr.project_id = pr.id
                    LEFT JOIN prizes p ON lr.prize_id = p.id
                    LEFT JOIN expense_records er ON er.project_id = lr.project_id 
                         AND er.user_id = lr.user_id 
                         AND er.is_used = 1 
                         AND er.reason REGEXP CONCAT('抽奖记录 #', lr.id, '([^0-9]|$)')
                    WHERE lr.user_id = ?
                    ORDER BY lr.created_at DESC, lr.id DESC
                    LIMIT 50
                ");
                $stmt->execute([$user_id]);
                $record_list = $stmt->fetchAll();

                $project_name_map = [];
                $project_rows = $pdo->query("SELECT id, name FROM projects")->fetchAll();
                foreach ($project_rows as $project_row) {
                    $project_name_map[(int)$project_row['id']] = $project_row['name'];
                }

                $times_records = [];
                // 优先从数据库 user_times_logs 表精准查询分配记录
                $log_stmt = $pdo->prepare("
                    SELECT utl.*, pr.name as project_name
                    FROM user_times_logs utl
                    LEFT JOIN projects pr ON utl.project_id = pr.id
                    WHERE utl.user_id = ?
                    ORDER BY utl.created_at DESC, utl.id DESC
                    LIMIT 50
                ");
                $log_stmt->execute([$user_id]);
                $db_logs = $log_stmt->fetchAll();

                if (!empty($db_logs)) {
                    foreach ($db_logs as $dl) {
                        $times_records[] = [
                            'time' => $dl['created_at'],
                            'ip' => $dl['ip_address'],
                            'project_name' => $dl['project_name'] ?? ('项目' . $dl['project_id']),
                            'times' => (int)$dl['times'],
                            'status' => '成功',
                            'action' => $dl['action'] ?? '单人分配'
                        ];
                    }
                } else if (is_file($admin_log_file)) {
                    // 向下兼容历史文件日志
                    $lines = file($admin_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $lines = array_slice($lines, -2000);
                    $lines = array_reverse($lines);
                    foreach ($lines as $line) {
                        $parts = explode("\t", $line);
                        if (count($parts) < 6) continue;
                        $log_time = $parts[0];
                        $log_ip = $parts[1];
                        $log_action = $parts[3];
                        $log_status = $parts[4];
                        $detail_text = $parts[5];
                        if (!in_array($log_action, ['set_user_times', 'batch_set_user_times'], true)) continue;
                        $detail = json_decode($detail_text, true);
                        if (!is_array($detail)) continue;
                        $match = false;
                        if ($log_action === 'set_user_times' && (int)($detail['user_id'] ?? 0) === $user_id) $match = true;
                        if ($log_action === 'batch_set_user_times') {
                            $detail_user_ids = $detail['user_ids'] ?? [];
                            if (is_array($detail_user_ids) && in_array($user_id, array_map('intval', $detail_user_ids), true)) $match = true;
                        }
                        if (!$match) continue;
                        $project_id = (int)($detail['project_id'] ?? 0);
                        $times_records[] = [
                            'time' => $log_time,
                            'ip' => $log_ip,
                            'project_name' => $project_name_map[$project_id] ?? ('项目' . $project_id),
                            'times' => (int)($detail['times'] ?? 0),
                            'status' => $log_status,
                            'action' => $log_action === 'set_user_times' ? '单人分配' : '批量分配'
                        ];
                        if (count($times_records) >= 50) break;
                    }
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COUNT(lr.prize_id) AS wins,
                    COALESCE(SUM(lr.prize_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM expense_records er WHERE er.project_id=lr.project_id AND er.user_id=lr.user_id AND er.is_used=1 AND er.reason REGEXP CONCAT('抽奖记录 #',lr.id,'([^0-9]|$)'))),0) AS pending
                    FROM lottery_records lr WHERE lr.user_id = ?");
                $stmt->execute([$user_id]);
                $user_stats = $stmt->fetch();
                require dirname(__DIR__) . '/views/user_detail.php';

                echo json_encode([
                    'success' => true,
                    'title' => $user['name'] . '详情',
                    'info_html' => $info_html,
                    'html' => $detail_html
                ]);
                exit;
            case 'quick_expense':
                header('Content-Type: application/json; charset=utf-8');
                $attachment = null;
                try {
                    $input = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
                        ? json_decode(file_get_contents('php://input'), true) : $_POST;
                    if (!is_array($input)) throw new InvalidArgumentException('请求参数无效');
                    expenseCheckCsrf($input['csrf_token'] ?? null);
                    $record_id = filter_var($input['record_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if (!$record_id) throw new InvalidArgumentException('记录ID无效');
                    $amount = expenseAmount($input['amount'] ?? '', true);
                    $applied_at = $input['applied_at'] ?? date('Y-m-d H:i:s');
                    if (!is_string($applied_at)) throw new InvalidArgumentException('报销日期无效');
                    $date = DateTime::createFromFormat('!Y-m-d\TH:i', $applied_at);
                    if (!$date || $date->format('Y-m-d\TH:i') !== $applied_at) {
                        $date = DateTime::createFromFormat('!Y-m-d H:i:s', $applied_at);
                        if (!$date || $date->format('Y-m-d H:i:s') !== $applied_at) throw new InvalidArgumentException('报销日期无效');
                    }
                    $pdo->beginTransaction();
                    $attachment = expenseUpload($_FILES['attachment'] ?? null);
                    $expense_id = expenseQuickCreate($pdo, $record_id, $amount, $date->format('Y-m-d H:i:s'), $attachment);
                    $pdo->commit();
                    logAdminAction('quick_expense', '成功', ['record_id' => $record_id, 'expense_id' => $expense_id, 'amount' => $amount], $admin_log_file);
                    echo json_encode(['success' => true, 'expense_id' => $expense_id]);
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($attachment) expenseUnlink($attachment);
                    logAdminAction('quick_expense', '失败', ['record_id' => $input['record_id'] ?? null, 'error' => $e->getMessage()], $admin_log_file);
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logAdminAction($action, '失败', $error, $admin_log_file);
        if ($action === 'get_user_detail' || $action === 'quick_expense') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
$record_summary = adminRecordSummary($pdo);
$active_tab = 'users';
$page_title = '用户管理';
require dirname(__DIR__) . '/views/admin/header.php';
?>

<div class="flex justify-between items-center mb-6 flex-wrap gap-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">用户管理</h2>
        <p class="text-xs text-gray-500 mt-1">管理系统注册用户，支持查看用户抽奖与中奖明细</p>
    </div>
    <div class="flex items-center space-x-3">
        <input type="text" id="users-filter" placeholder="筛选姓名" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" oninput="applyUsersFilter()">
        <button onclick="showModal('add-user-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition flex items-center">
            <i class="fa fa-plus mr-2"></i>添加用户
        </button>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th class="px-6 py-3">ID</th>
                <th class="px-6 py-3">姓名</th>
                <th class="px-6 py-3">IP地址</th>
                <th class="px-6 py-3">注册时间</th>
                <th class="px-6 py-3">操作</th>
            </tr>
        </thead>
        <tbody id="users-body">
            <?php if (empty($users)): ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-400">暂无用户数据</td>
            </tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr class="bg-white border-b" data-name="<?php echo htmlspecialchars($user['name']); ?>">
                    <td class="px-6 py-4"><?php echo $user['id']; ?></td>
                    <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($user['name']); ?></td>
                    <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($user['ip_address']); ?></td>
                    <td class="px-6 py-4 text-gray-500"><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="user-action-group">
                            <button type="button" onclick="showUserDetail(<?php echo (int)$user['id']; ?>, this)" class="btn-action-detail" aria-haspopup="dialog" aria-label="查看<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>的详情" title="查看用户详情">
                                <i class="fa fa-id-card-o" aria-hidden="true"></i>
                                <span>查看详情</span>
                                <i class="fa fa-angle-right" aria-hidden="true"></i>
                            </button>
                            <button type="button" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['ip_address'], ENT_QUOTES); ?>')" class="btn-action-icon btn-action-edit" title="编辑用户">
                                <i class="fa fa-pencil-square-o"></i>
                            </button>
                            <button type="button" onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn-action-icon btn-action-delete" title="删除用户">
                                <i class="fa fa-trash-o"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 添加用户模态框 -->
<div id="add-user-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">添加用户</h3>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="add_user">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">用户姓名</label>
                <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required placeholder="请输入用户姓名">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">IP地址</label>
                <input type="text" name="ip" class="w-full border border-gray-300 rounded-lg px-3 py-2" required placeholder="例如 127.0.0.1">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('add-user-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    添加
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑用户模态框 -->
<div id="edit-user-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">编辑用户</h3>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="id" id="edit-user-id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">用户姓名</label>
                <input type="text" name="name" id="edit-user-name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">IP地址</label>
                <input type="text" name="ip" id="edit-user-ip" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('edit-user-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    更新
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 用户详情模态框 -->
<div id="user-detail-modal" role="dialog" aria-modal="true" aria-labelledby="user-detail-title" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-4xl max-h-[85vh] flex flex-col relative overflow-hidden">
        <div class="ud-header">
            <div class="flex items-center justify-between mb-4">
                <h3 id="user-detail-title" tabindex="-1" class="text-lg font-bold text-gray-800">用户详情</h3>
                <button type="button" onclick="hideModal('user-detail-modal')" class="ud-close" aria-label="关闭用户详情">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div id="user-detail-info"></div>
        </div>
        <div id="user-detail-body" class="ud-body"></div>
        <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50 rounded-b-lg">
            <button type="button" onclick="hideModal('user-detail-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                关闭
            </button>
        </div>
    </div>
</div>

<!-- 快速报销模态框（支持在用户详情弹窗中直接报销） -->
<div id="quick-expense-modal" role="dialog" aria-modal="true" aria-labelledby="qe-title" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md pointer-events-auto">
        <h3 class="text-lg font-bold mb-4 text-gray-800" id="qe-title">快速报销</h3>
        <p class="quick-note text-xs text-gray-500 mb-3">核对中奖信息，填写实际金额。提交后将直接完成报销并保存审批记录。</p>
        <div id="qe-error" class="qe-error hidden p-3 mb-3 bg-red-50 text-red-700 border border-red-200 rounded text-sm" role="alert"></div>
        <form id="quick-expense-form" onsubmit="submitQuickExpense(event)">
            <input type="hidden" id="qe-record-id">
            <input type="hidden" id="qe-project-id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">报销用户</label>
                <input type="text" id="qe-user-name" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-gray-700" readonly>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">中奖奖品</label>
                <input type="text" id="qe-prize-name" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 text-gray-700 font-semibold text-emerald-800" readonly>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">报销时间</label>
                <input type="datetime-local" id="qe-applied-at" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">报销金额 (元)</label>
                <input type="number" id="qe-amount" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-lg font-bold text-teal-700" required placeholder="请输入报销金额">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">报销附件（JPG / PNG / PDF，最大10MB）</label>
                <input type="file" id="qe-attachment" accept=".jpg,.jpeg,.png,.pdf" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('quick-expense-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="submit" id="qe-submit-btn" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 flex items-center">
                    <i class="fa fa-check mr-1"></i>确认报销
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(id, name, ip) {
    document.getElementById('edit-user-id').value = id;
    document.getElementById('edit-user-name').value = name;
    document.getElementById('edit-user-ip').value = ip;
    showModal('edit-user-modal');
}

function deleteUser(id) {
    if (confirm('确定要删除这个用户吗？相关的抽奖次数和记录也会受到影响！')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'users.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_user';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function applyUsersFilter() {
    const input = document.getElementById('users-filter');
    const filter = (input ? input.value : '').toLowerCase();
    const rows = document.querySelectorAll('#users-body tr[data-name]');
    rows.forEach(row => {
        const name = (row.getAttribute('data-name') || '').toLowerCase();
        row.style.display = name.includes(filter) ? '' : 'none';
    });
}

let userDetailRequest = 0;
let userDetailTrigger = null;

function showUserDetail(userId, trigger, keepOpen = false) {
    userDetailTrigger = trigger || userDetailTrigger;
    const currentRequest = ++userDetailRequest;
    const info = document.getElementById('user-detail-info');
    const body = document.getElementById('user-detail-body');
    if (!keepOpen) {
        info.innerHTML = '<div class="ud-empty"><i class="fa fa-spinner fa-spin"></i><strong>正在加载资料...</strong></div>';
        body.innerHTML = '';
        showModal('user-detail-modal');
    }
    const formData = new FormData();
    formData.append('action', 'get_user_detail');
    formData.append('user_id', userId);
    fetch('users.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (currentRequest !== userDetailRequest) return;
        if (data.success) {
            info.innerHTML = data.info_html;
            body.innerHTML = data.html;
            document.getElementById('user-detail-title').textContent = data.title;
            userRecordFilterState = { status: 'all', keyword: '' };
        } else {
            body.innerHTML = '<div class="ud-empty"><i class="fa fa-exclamation-circle text-red-500"></i><strong>加载失败</strong><p>' + (data.message || '未知错误') + '</p></div>';
        }
    })
    .catch(() => {
        body.innerHTML = '<div class="ud-empty"><i class="fa fa-exclamation-circle text-red-500"></i><strong>加载详情失败，请检查网络后重试</strong></div>';
    });
}

function switchUserDetailTab(tabName) {
    const tabs = ['projects', 'records', 'activity'];
    tabs.forEach(t => {
        const btn = document.getElementById('ud-tab-' + t);
        const sec = document.getElementById('ud-' + t);
        if (btn && sec) {
            if (t === tabName) {
                btn.setAttribute('aria-selected', 'true');
                sec.hidden = false;
            } else {
                btn.setAttribute('aria-selected', 'false');
                sec.hidden = true;
            }
        }
    });
}
// 抽奖与报销前端即时筛选过滤
let userRecordFilterState = {
    status: 'all',
    keyword: ''
};

function setUserRecordFilterStatus(status, btn) {
    userRecordFilterState.status = status;
    const bar = btn.closest('.ud-filter-tags');
    if (bar) {
        bar.querySelectorAll('.ud-filter-btn').forEach(b => b.classList.remove('active'));
    }
    btn.classList.add('active');
    applyUserRecordsFilter();
}

function applyUserRecordsFilter() {
    const input = document.getElementById('ud-record-keyword');
    const kw = input ? input.value.trim().toLowerCase() : '';
    userRecordFilterState.keyword = kw;
    const targetStatus = userRecordFilterState.status;

    const cards = document.querySelectorAll('#ud-records-list .ud-record-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const cStatus = card.getAttribute('data-status') || '';
        const cId = String(card.getAttribute('data-id') || '');
        const cPrize = (card.getAttribute('data-prize') || '').toLowerCase();
        const cProject = (card.getAttribute('data-project') || '').toLowerCase();

        // 状态匹配
        const matchStatus = (targetStatus === 'all') || (cStatus === targetStatus);

        // 关键词匹配 (搜索ID、奖品名、所属项目)
        let matchKw = true;
        if (kw) {
            matchKw = cPrize.includes(kw) || cProject.includes(kw) || cId.includes(kw);
        }

        if (matchStatus && matchKw) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // 空匹配提示
    const emptyMatch = document.getElementById('ud-records-empty-match');
    if (emptyMatch) {
        emptyMatch.style.display = (visibleCount === 0 && cards.length > 0) ? 'flex' : 'none';
    }
}
let currentExpenseBtn = null;
let currentExpenseUserId = null;

function quickExpense(recordId, userName, prizeName, projectId, btn) {
    currentExpenseBtn = btn;
    currentExpenseUserId = btn.getAttribute('data-user-id') || null;
    document.getElementById('qe-record-id').value = recordId;
    document.getElementById('qe-project-id').value = projectId;
    document.getElementById('qe-user-name').value = userName;
    document.getElementById('qe-prize-name').value = prizeName;
    document.getElementById('qe-amount').value = '';
    document.getElementById('qe-attachment').value = '';
    
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const localIso = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    document.getElementById('qe-applied-at').value = localIso;
    
    const errEl = document.getElementById('qe-error');
    errEl.textContent = '';
    errEl.classList.add('hidden');
    
    showModal('quick-expense-modal');
    setTimeout(() => document.getElementById('qe-amount').focus(), 100);
}

function submitQuickExpense(e) {
    e.preventDefault();
    const btn = document.getElementById('qe-submit-btn');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>处理中...';
    
    const recordId = document.getElementById('qe-record-id').value;
    const formData = new FormData();
    formData.append('action', 'quick_expense');
    formData.append('csrf_token', '<?php echo htmlspecialchars($expense_csrf_token); ?>');
    formData.append('record_id', recordId);
    formData.append('project_id', document.getElementById('qe-project-id').value);
    formData.append('applied_at', document.getElementById('qe-applied-at').value);
    formData.append('amount', document.getElementById('qe-amount').value);
    
    const fileInput = document.getElementById('qe-attachment');
    if (fileInput.files.length > 0) {
        formData.append('attachment', fileInput.files[0]);
    }

    fetch('users.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        if (data.success) {
            hideModal('quick-expense-modal');
            showAdminToast('报销成功 · 已生成报销单 #' + data.expense_id);
            if (currentExpenseUserId) {
                // 就地静默刷新用户详情，保持抽奖与报销Tab打开
                showUserDetail(currentExpenseUserId, null, true);
                setTimeout(() => switchUserDetailTab('records'), 50);
            } else if (currentExpenseBtn) {
                currentExpenseBtn.outerHTML = '<span class="ud-badge ud-badge-used"><i class="fa fa-check mr-1"></i>已报销</span>';
            }
        } else {
            const errEl = document.getElementById('qe-error');
            errEl.textContent = data.message || '报销处理失败';
            errEl.classList.remove('hidden');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        const errEl = document.getElementById('qe-error');
        errEl.textContent = '网络中断，请稍后重试';
        errEl.classList.remove('hidden');
    });
}
</script>

<?php require dirname(__DIR__) . '/views/admin/footer.php'; ?>
