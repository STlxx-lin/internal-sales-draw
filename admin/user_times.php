<?php
// user_times.php - 用户抽奖次数分配独立页面
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/admin_records.php';

$message = '';
$error = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$projects = $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
$selected_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : ($projects[0]['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'set_user_times':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $project_id = (int)($_POST['project_id'] ?? 0);
                $times = (int)($_POST['times'] ?? 0);
                $is_visible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 1;
                
                if (!$user_id || !$project_id) {
                    throw new Exception('用户和项目不能为空');
                }
                if ($times < 0) {
                    throw new Exception('抽奖次数不能为负数');
                }
                
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT * FROM user_project_times WHERE user_id = ? AND project_id = ?");
                $stmt->execute([$user_id, $project_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE user_project_times SET total_times = ?, remaining_times = ?, is_visible = ? WHERE user_id = ? AND project_id = ?");
                    $stmt->execute([$times, $times, $is_visible, $user_id, $project_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO user_project_times (user_id, project_id, total_times, remaining_times, is_visible) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $project_id, $times, $times, $is_visible]);
                }

                // 写入数据库分配记录表
                $log_stmt = $pdo->prepare("INSERT INTO user_times_logs (user_id, project_id, times, action, operator, ip_address) VALUES (?, ?, ?, '单人分配', ?, ?)");
                $operator = $_SESSION['admin_username'] ?? 'admin';
                $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $log_stmt->execute([$user_id, $project_id, $times, $operator, $client_ip]);
                $pdo->commit();

                logAdminAction('set_user_times', '成功', ['user_id' => $user_id, 'project_id' => $project_id, 'times' => $times, 'is_visible' => $is_visible], $admin_log_file);
                header('Location: user_times.php?project_id=' . $project_id . '&msg=' . urlencode('次数分配成功'));
                exit;

            case 'batch_set_user_times':
                $project_id = (int)($_POST['project_id'] ?? 0);
                $times = (int)($_POST['times'] ?? 0);
                $user_ids = $_POST['user_ids'] ?? [];
                $is_visible = isset($_POST['is_visible']) ? (int)$_POST['is_visible'] : 1;
                
                if (!$project_id) throw new Exception('项目不能为空');
                if ($times < 0) throw new Exception('抽奖次数不能为负数');
                if (empty($user_ids)) throw new Exception('请至少选择一个用户');
                
                $pdo->beginTransaction();
                $log_stmt = $pdo->prepare("INSERT INTO user_times_logs (user_id, project_id, times, action, operator, ip_address) VALUES (?, ?, ?, '批量分配', ?, ?)");
                $operator = $_SESSION['admin_username'] ?? 'admin';
                $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

                foreach ($user_ids as $uid) {
                    $uid = (int)$uid;
                    $stmt = $pdo->prepare("SELECT * FROM user_project_times WHERE user_id = ? AND project_id = ?");
                    $stmt->execute([$uid, $project_id]);
                    $existing = $stmt->fetch();
                    if ($existing) {
                        $stmt = $pdo->prepare("UPDATE user_project_times SET total_times = ?, remaining_times = ?, is_visible = ? WHERE user_id = ? AND project_id = ?");
                        $stmt->execute([$times, $times, $is_visible, $uid, $project_id]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO user_project_times (user_id, project_id, total_times, remaining_times, is_visible) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$uid, $project_id, $times, $times, $is_visible]);
                    }

                    // 写入数据库分配记录表
                    $log_stmt->execute([$uid, $project_id, $times, $operator, $client_ip]);
                }
                $pdo->commit();
                logAdminAction('batch_set_user_times', '成功', ['project_id' => $project_id, 'times' => $times, 'user_ids' => $user_ids, 'is_visible' => $is_visible], $admin_log_file);
                header('Location: user_times.php?project_id=' . $project_id . '&msg=' . urlencode('批量分配成功'));
                exit;

            case 'get_user_times':
                header('Content-Type: application/json; charset=utf-8');
                $pid = (int)($_POST['project_id'] ?? $selected_project_id);
                $stmt = $pdo->prepare("
                    SELECT upt.*, u.name as user_name, u.ip_address 
                    FROM user_project_times upt 
                    JOIN users u ON upt.user_id = u.id 
                    WHERE upt.project_id = ? 
                    ORDER BY u.name
                ");
                $stmt->execute([$pid]);
                $upt_list = $stmt->fetchAll();
                
                ob_start();
                if (empty($upt_list)): ?>
                    <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400">该项目暂未分配用户抽奖次数</td></tr>
                <?php else:
                    foreach ($upt_list as $upt): ?>
                    <tr class="bg-white border-b" data-name="<?php echo htmlspecialchars($upt['user_name']); ?>" data-total="<?php echo $upt['total_times']; ?>" data-remaining="<?php echo $upt['remaining_times']; ?>">
                        <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($upt['user_name']); ?></td>
                        <td class="px-6 py-4"><?php echo htmlspecialchars($upt['ip_address']); ?></td>
                        <td class="px-6 py-4"><?php echo $upt['total_times']; ?></td>
                        <td class="px-6 py-4"><?php echo $upt['remaining_times']; ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full <?php echo $upt['is_visible'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $upt['is_visible'] ? '显示' : '隐藏'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="user-action-group">
                                <button type="button" onclick="editUserTimes(<?php echo $upt['user_id']; ?>, <?php echo $pid; ?>, <?php echo $upt['remaining_times']; ?>, <?php echo $upt['is_visible']; ?>)" class="btn-action-icon btn-action-edit" title="编辑次数">
                                    <i class="fa fa-pencil-square-o"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach;
                endif;
                $rows_html = ob_get_clean();
                echo json_encode(['success' => true, 'rows_html' => $rows_html]);
                exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
        logAdminAction($action, '失败', $error, $admin_log_file);
        if ($action === 'get_user_times') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

$user_project_times = [];
if ($selected_project_id) {
    $stmt = $pdo->prepare("
        SELECT upt.*, u.name as user_name, u.ip_address 
        FROM user_project_times upt 
        JOIN users u ON upt.user_id = u.id 
        WHERE upt.project_id = ? 
        ORDER BY u.name
    ");
    $stmt->execute([$selected_project_id]);
    $user_project_times = $stmt->fetchAll();
}

$all_users = $pdo->query("SELECT * FROM users ORDER BY name")->fetchAll();
$record_summary = adminRecordSummary($pdo);

$active_tab = 'user-times';
$page_title = '用户抽奖次数分配';
require dirname(__DIR__) . '/views/admin/header.php';
?>

<div class="flex justify-between items-center mb-6 flex-wrap gap-4">
    <div class="flex items-center space-x-4">
        <h2 class="text-xl font-bold text-gray-800">用户抽奖次数分配</h2>
        <select onchange="location.href='user_times.php?project_id='+this.value" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
            <?php foreach ($projects as $project): ?>
                <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $selected_project_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($project['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-center space-x-3 flex-wrap gap-2">
        <input type="text" id="user-times-filter" placeholder="筛选姓名" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" oninput="applyUserTimesFilter()">
        <button type="button" onclick="refreshUserTimes()" class="px-3 py-2 text-sm border rounded-lg transition" title="局部刷新列表">
            <i class="fa fa-refresh mr-1"></i>刷新
        </button>
        <button onclick="showModal('batch-set-times-modal')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition flex items-center">
            <i class="fa fa-users mr-2"></i>批量分配
        </button>
        <button onclick="showModal('set-times-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition flex items-center">
            <i class="fa fa-plus mr-2"></i>分配次数
        </button>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th class="px-6 py-3">
                    <button type="button" class="flex items-center space-x-2 font-bold" onclick="sortUserTimesTable('name')">
                        <span>用户姓名</span>
                        <i id="sort-icon-name" class="fa fa-sort text-xs"></i>
                    </button>
                </th>
                <th class="px-6 py-3">IP地址</th>
                <th class="px-6 py-3">
                    <button type="button" class="flex items-center space-x-2 font-bold" onclick="sortUserTimesTable('total')">
                        <span>总次数</span>
                        <i id="sort-icon-total" class="fa fa-sort text-xs"></i>
                    </button>
                </th>
                <th class="px-6 py-3">
                    <button type="button" class="flex items-center space-x-2 font-bold" onclick="sortUserTimesTable('remaining')">
                        <span>剩余次数</span>
                        <i id="sort-icon-remaining" class="fa fa-sort text-xs"></i>
                    </button>
                </th>
                <th class="px-6 py-3">是否显示</th>
                <th class="px-6 py-3">操作</th>
            </tr>
        </thead>
        <tbody id="user-times-body">
            <?php if (empty($user_project_times)): ?>
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-400">该项目暂未分配用户抽奖次数</td>
            </tr>
            <?php else: ?>
                <?php foreach ($user_project_times as $upt): ?>
                <tr class="bg-white border-b" data-name="<?php echo htmlspecialchars($upt['user_name']); ?>" data-total="<?php echo $upt['total_times']; ?>" data-remaining="<?php echo $upt['remaining_times']; ?>">
                    <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($upt['user_name']); ?></td>
                    <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($upt['ip_address']); ?></td>
                    <td class="px-6 py-4 font-semibold text-gray-700"><?php echo $upt['total_times']; ?></td>
                    <td class="px-6 py-4 font-semibold text-teal-800"><?php echo $upt['remaining_times']; ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $upt['is_visible'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $upt['is_visible'] ? '显示' : '隐藏'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="user-action-group">
                            <button type="button" onclick="editUserTimes(<?php echo $upt['user_id']; ?>, <?php echo $selected_project_id; ?>, <?php echo $upt['remaining_times']; ?>, <?php echo $upt['is_visible']; ?>)" class="btn-action-icon btn-action-edit" title="编辑次数">
                                <i class="fa fa-pencil-square-o"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 分配次数模态框 -->
<div id="set-times-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">分配抽奖次数</h3>
        <form method="POST" action="user_times.php?project_id=<?php echo $selected_project_id; ?>">
            <input type="hidden" name="action" value="set_user_times">
            <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">选择用户</label>
                <select name="user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
                    <option value="">请选择用户</option>
                    <?php foreach ($all_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] . ' (' . $u['ip_address'] . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">抽奖次数</label>
                <div class="flex items-center space-x-2">
                    <button type="button" class="px-3 py-2 border rounded" onclick="incrementTimes('set-times-input', -1)">-</button>
                    <input type="number" id="set-times-input" name="times" min="0" value="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-center" required>
                    <button type="button" class="px-3 py-2 border rounded" onclick="incrementTimes('set-times-input', 1)">+</button>
                </div>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_visible" value="1" class="mr-2" checked>
                    <span class="text-sm text-gray-700">在抽奖页面显示该项目</span>
                </label>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('set-times-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">取消</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑次数模态框 -->
<div id="edit-times-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">编辑抽奖次数</h3>
        <form method="POST" action="user_times.php?project_id=<?php echo $selected_project_id; ?>">
            <input type="hidden" name="action" value="set_user_times">
            <input type="hidden" name="user_id" id="edit-times-user-id">
            <input type="hidden" name="project_id" id="edit-times-project-id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">抽奖次数</label>
                <div class="flex items-center space-x-2">
                    <button type="button" class="px-3 py-2 border rounded" onclick="incrementTimes('edit-times-input', -1)">-</button>
                    <input type="number" id="edit-times-input" name="times" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-center" required>
                    <button type="button" class="px-3 py-2 border rounded" onclick="incrementTimes('edit-times-input', 1)">+</button>
                </div>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_visible" id="edit-times-visible" value="1" class="mr-2">
                    <span class="text-sm text-gray-700">在抽奖页面显示该项目</span>
                </label>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('edit-times-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">取消</button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">更新</button>
            </div>
        </form>
    </div>
</div>

<!-- 批量分配模态框 -->
<div id="batch-set-times-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-lg">
        <h3 class="text-lg font-bold mb-4 text-gray-800">批量分配抽奖次数</h3>
        <form method="POST" action="user_times.php?project_id=<?php echo $selected_project_id; ?>">
            <input type="hidden" name="action" value="batch_set_user_times">
            <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
            <div class="mb-4">
                <div class="flex justify-between items-center mb-2">
                    <label class="block text-sm font-medium text-gray-700">选择用户</label>
                    <button type="button" onclick="toggleSelectAllUsers(this)" class="text-xs text-blue-600 hover:underline">全选</button>
                </div>
                <div class="max-h-48 overflow-y-auto border border-gray-300 rounded-lg p-2 space-y-1">
                    <?php foreach ($all_users as $u): ?>
                    <label class="flex items-center px-2 py-1 hover:bg-gray-50 rounded">
                        <input type="checkbox" name="user_ids[]" value="<?php echo $u['id']; ?>" class="batch-user-checkbox mr-2">
                        <span class="text-sm text-gray-800"><?php echo htmlspecialchars($u['name']); ?></span>
                        <span class="text-xs text-gray-400 ml-2">(<?php echo htmlspecialchars($u['ip_address']); ?>)</span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">为选中用户统一设置次数</label>
                <div class="flex items-center space-x-2">
                    <button type="button" class="px-3 py-2 border rounded" onclick="incrementTimes('batch-times-input', -1)">-</button>
                    <input type="number" id="batch-times-input" name="times" min="0" value="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-center" required>
                    <button type="button" class="px-3 py-2 border rounded" onclick="incrementTimes('batch-times-input', 1)">+</button>
                </div>
            </div>
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_visible" value="1" class="mr-2" checked>
                    <span class="text-sm text-gray-700">在抽奖页面显示该项目</span>
                </label>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('batch-set-times-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">取消</button>
                <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">批量设置</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUserTimes(userId, projectId, remainingTimes, isVisible) {
    document.getElementById('edit-times-user-id').value = userId;
    document.getElementById('edit-times-project-id').value = projectId;
    document.getElementById('edit-times-input').value = remainingTimes;
    document.getElementById('edit-times-visible').checked = (isVisible == 1);
    showModal('edit-times-modal');
}

function incrementTimes(inputId, delta) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const current = parseInt(input.value || '0', 10);
    input.value = Math.max(0, (Number.isNaN(current) ? 0 : current) + delta);
    input.focus();
}

function toggleSelectAllUsers(btn) {
    const boxes = document.querySelectorAll('.batch-user-checkbox');
    const anyUnchecked = Array.from(boxes).some(b => !b.checked);
    boxes.forEach(b => b.checked = anyUnchecked);
    btn.textContent = anyUnchecked ? '全不选' : '全选';
}

function applyUserTimesFilter() {
    const filter = (document.getElementById('user-times-filter')?.value || '').toLowerCase();
    const rows = document.querySelectorAll('#user-times-body tr[data-name]');
    rows.forEach(r => {
        const name = (r.getAttribute('data-name') || '').toLowerCase();
        r.style.display = name.includes(filter) ? '' : 'none';
    });
}

function refreshUserTimes() {
    const formData = new FormData();
    formData.append('action', 'get_user_times');
    formData.append('project_id', '<?php echo (int)$selected_project_id; ?>');
    fetch('user_times.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const tbody = document.getElementById('user-times-body');
            if (tbody) tbody.innerHTML = data.rows_html;
            applyUserTimesFilter();
            showAdminToast('用户抽奖次数已刷新');
        } else {
            alert('刷新失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(() => alert('网络异常，刷新失败'));
}

const userTimesSortState = { column: '', order: 'asc' };
function sortUserTimesTable(column) {
    const tbody = document.getElementById('user-times-body');
    const rows = Array.from(tbody.querySelectorAll('tr[data-name]'));
    if (rows.length === 0) return;
    const order = (userTimesSortState.column === column && userTimesSortState.order === 'asc') ? 'desc' : 'asc';
    userTimesSortState.column = column;
    userTimesSortState.order = order;
    rows.sort((a, b) => {
        let valA, valB;
        if (column === 'name') {
            valA = a.getAttribute('data-name') || '';
            valB = b.getAttribute('data-name') || '';
            return order === 'asc' ? valA.localeCompare(valB, 'zh-CN') : valB.localeCompare(valA, 'zh-CN');
        } else {
            valA = parseFloat(a.getAttribute('data-' + column)) || 0;
            valB = parseFloat(b.getAttribute('data-' + column)) || 0;
            return order === 'asc' ? valA - valB : valB - valA;
        }
    });
    rows.forEach(r => tbody.appendChild(r));
    ['name', 'total', 'remaining'].forEach(c => {
        const icon = document.getElementById('sort-icon-' + c);
        if (icon) {
            icon.className = c === column ? (order === 'asc' ? 'fa fa-sort-asc text-xs text-blue-600' : 'fa fa-sort-desc text-xs text-blue-600') : 'fa fa-sort text-xs';
        }
    });
}
</script>

<?php require dirname(__DIR__) . '/views/admin/footer.php'; ?>
