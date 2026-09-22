<?php
// lottery_records.php - 抽奖记录与报销工作台独立页面
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/admin_records.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 处理 AJAX 交互请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'get_records':
            header('Content-Type: application/json; charset=utf-8');
            try {
                $page = max(1, (int)($_POST['page'] ?? 1));
                $keyword = trim($_POST['keyword'] ?? '');
                $status = $_POST['record_status'] ?? 'all';
                $result = adminRecords($pdo, $page, $keyword, $status);
                $lottery_records = $result['records'];
                $total_records = $result['total'];
                $total_pages = $result['pages'];
                $page = $result['page'];

                ob_start();
                if (empty($lottery_records)): ?>
                    <tr><td colspan="8" class="px-6 py-8 text-center text-gray-400">暂无符合条件的记录</td></tr>
                <?php else:
                    foreach ($lottery_records as $record):
                        $is_used = (bool)$record['is_used'];
                    ?>
                    <tr class="bg-white border-b" data-record-id="<?php echo (int)$record['id']; ?>">
                        <td class="px-6 py-4 font-mono text-xs text-gray-500">#<?php echo $record['id']; ?></td>
                        <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($record['user_name']); ?></td>
                        <td class="px-6 py-4 text-gray-500 text-xs"><?php echo htmlspecialchars($record['ip_address']); ?></td>
                        <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($record['project_name']); ?></td>
                        <td class="px-6 py-4">
                            <?php if ($record['prize_id']): ?>
                                <span class="font-medium text-emerald-700"><?php echo htmlspecialchars($record['prize_name']); ?></span>
                            <?php else: ?>
                                <span class="text-gray-400">未中奖</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($is_used): ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-200 text-gray-600">
                                    <i class="fa fa-check mr-1"></i>已报销
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700">
                                    <i class="fa fa-clock-o mr-1"></i><?php echo $record['prize_id'] ? '待报销' : '无需报销'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if (!$is_used && $record['prize_id']): ?>
                                <button onclick="quickExpense(<?php echo (int)$record['id']; ?>, <?php echo htmlspecialchars(json_encode($record['user_name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($record['prize_name']), ENT_QUOTES); ?>, <?php echo (int)$record['project_id']; ?>, this)" class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded transition">
                                    <i class="fa fa-money mr-1"></i>快速报销
                                </button>
                            <?php elseif ($is_used): ?>
                                <span class="text-xs text-gray-400">—</span>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">未中奖</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs"><?php echo date('Y-m-d H:i:s', strtotime($record['created_at'])); ?></td>
                    </tr>
                    <?php endforeach;
                endif;
                $rows_html = ob_get_clean();

                ob_start();
                if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <nav class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <button type="button" onclick="refreshLotteryRecords(<?php echo $page - 1; ?>)" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <i class="fa fa-chevron-left"></i>
                                </button>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <button type="button" onclick="refreshLotteryRecords(<?php echo $i; ?>)" class="px-3 py-2 text-sm <?php echo $i == $page ? 'bg-blue-500 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <button type="button" onclick="refreshLotteryRecords(<?php echo $page + 1; ?>)" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    <i class="fa fa-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif;
                $pagination_html = ob_get_clean();

                echo json_encode([
                    'success' => true,
                    'total_records' => (int)$total_records,
                    'page' => $page,
                    'summary' => adminRecordSummary($pdo),
                    'rows_html' => $rows_html,
                    'pagination_html' => $pagination_html
                ]);
                logAdminAction('get_records', '成功', ['page' => $page, 'keyword' => $keyword], $admin_log_file);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }

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
}

// 页面加载默认首屏数据
$records_per_page = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$result = adminRecords($pdo, $page);
$lottery_records = $result['records'];
$total_records = $result['total'];
$total_pages = $result['pages'];
$page = $result['page'];
$record_summary = adminRecordSummary($pdo);

$active_tab = 'records';
$page_title = '中奖记录与快速报销';
require dirname(__DIR__) . '/views/admin/header.php';
?>

<div class="record-toolbar">
    <div>
        <h2 class="text-xl font-bold text-gray-800">中奖记录 · 快速报销</h2>
        <p class="text-xs text-gray-500 mt-1">找到中奖记录，填写报销金额，一键完成快速报销。</p>
    </div>
    <div class="record-controls">
        <input type="search" id="lottery-records-filter" aria-label="搜索中奖记录" placeholder="搜索姓名、项目、奖品或编号" oninput="scheduleLotteryRecordsSearch()">
        <select id="record-status" aria-label="报销状态" onchange="refreshLotteryRecords(1)">
            <option value="all">全部记录</option>
            <option value="pending">待报销</option>
            <option value="used">已报销</option>
        </select>
        <button type="button" onclick="refreshLotteryRecords()" class="px-3 py-2 text-sm border rounded-lg transition" aria-label="刷新记录" title="刷新记录列表">
            <i class="fa fa-refresh"></i>
        </button>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th class="px-6 py-3">记录ID</th>
                <th class="px-6 py-3">用户姓名</th>
                <th class="px-6 py-3">IP地址</th>
                <th class="px-6 py-3">抽奖项目</th>
                <th class="px-6 py-3">中奖结果</th>
                <th class="px-6 py-3">报销状态</th>
                <th class="px-6 py-3">操作</th>
                <th class="px-6 py-3">抽奖时间</th>
            </tr>
        </thead>
        <tbody id="lottery-records-body">
            <?php if (empty($lottery_records)): ?>
            <tr>
                <td colspan="8" class="px-6 py-8 text-center text-gray-400">暂无抽奖记录</td>
            </tr>
            <?php else: ?>
                <?php foreach ($lottery_records as $record):
                    $is_used = (bool)$record['is_used'];
                ?>
                <tr class="bg-white border-b" data-record-id="<?php echo (int)$record['id']; ?>">
                    <td class="px-6 py-4 font-mono text-xs text-gray-500">#<?php echo $record['id']; ?></td>
                    <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($record['user_name']); ?></td>
                    <td class="px-6 py-4 text-gray-500 text-xs"><?php echo htmlspecialchars($record['ip_address']); ?></td>
                    <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($record['project_name']); ?></td>
                    <td class="px-6 py-4">
                        <?php if ($record['prize_id']): ?>
                            <span class="font-medium text-emerald-700"><?php echo htmlspecialchars($record['prize_name']); ?></span>
                        <?php else: ?>
                            <span class="text-gray-400">未中奖</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($is_used): ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-200 text-gray-600">
                                <i class="fa fa-check mr-1"></i>已报销
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700">
                                <i class="fa fa-clock-o mr-1"></i><?php echo $record['prize_id'] ? '待报销' : '无需报销'; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if (!$is_used && $record['prize_id']): ?>
                            <button onclick="quickExpense(<?php echo (int)$record['id']; ?>, <?php echo htmlspecialchars(json_encode($record['user_name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($record['prize_name']), ENT_QUOTES); ?>, <?php echo (int)$record['project_id']; ?>, this)" class="text-xs bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded transition">
                                <i class="fa fa-money mr-1"></i>快速报销
                            </button>
                        <?php elseif ($is_used): ?>
                            <span class="text-xs text-gray-400">—</span>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">未中奖</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-gray-500 text-xs"><?php echo date('Y-m-d H:i:s', strtotime($record['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 分页 -->
<div id="lottery-records-pagination">
    <?php if ($total_pages > 1): ?>
    <div class="flex justify-center mt-6">
        <nav class="flex space-x-2">
            <?php if ($page > 1): ?>
                <button type="button" onclick="refreshLotteryRecords(<?php echo $page - 1; ?>)" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fa fa-chevron-left"></i>
                </button>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <button type="button" onclick="refreshLotteryRecords(<?php echo $i; ?>)" class="px-3 py-2 text-sm <?php echo $i == $page ? 'bg-blue-500 text-white font-bold' : 'bg-white text-gray-700 hover:bg-gray-50'; ?> border border-gray-300 rounded-lg">
                    <?php echo $i; ?>
                </button>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <button type="button" onclick="refreshLotteryRecords(<?php echo $page + 1; ?>)" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fa fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- 快速报销模态框 -->
<div id="quick-expense-modal" role="dialog" aria-modal="true" aria-labelledby="qe-title" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800" id="qe-title">快速报销</h3>
        <p class="quick-note text-xs text-gray-500 mb-3">核对中奖信息，填写实际金额。提交后将直接完成报销并保存审批记录。</p>
        <div id="qe-error" class="qe-error hidden p-3 mb-3 bg-red-50 text-red-700 border border-red-200 rounded text-sm" role="alert"></div>
        <form id="quick-expense-form" onsubmit="submitQuickExpense(event)">
            <input type="hidden" id="qe-record-id">
            <input type="hidden" id="qe-project-id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">报销用户</label>
                <input type="text" id="qe-user-name" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50" readonly>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">中奖奖品</label>
                <input type="text" id="qe-prize-name" class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-50" readonly>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">报销时间</label>
                <input type="datetime-local" id="qe-applied-at" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">报销金额</label>
                <input type="number" id="qe-amount" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2" required placeholder="请输入报销金额">
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
                    <i class="fa fa-check mr-2"></i>确认报销
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let lotteryRecordsSearchTimer = null;
let recordsRequest = 0;
let currentRecordsPage = <?php echo (int)$page; ?>;

function scheduleLotteryRecordsSearch() {
    if (lotteryRecordsSearchTimer) clearTimeout(lotteryRecordsSearchTimer);
    lotteryRecordsSearchTimer = setTimeout(() => {
        refreshLotteryRecords(1);
    }, 300);
}

function refreshLotteryRecords(page) {
    const requestId = ++recordsRequest;
    const formData = new FormData();
    formData.append('action', 'get_records');
    if (typeof page === 'number' && !Number.isNaN(page)) {
        currentRecordsPage = Math.max(1, page);
    }
    formData.append('page', String(currentRecordsPage));
    const keyword = document.getElementById('lottery-records-filter')?.value.trim() || '';
    formData.append('keyword', keyword);
    formData.append('record_status', document.getElementById('record-status').value);

    fetch('lottery_records.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (requestId !== recordsRequest) return;
        currentRecordsPage = data.page || currentRecordsPage;
        if (data.summary) {
            ['pending', 'used', 'total'].forEach(k => {
                const el = document.getElementById('summary-' + k);
                if (el && data.summary[k] !== undefined) el.textContent = data.summary[k];
            });
        }
        if (!data.success) {
            alert('刷新失败: ' + (data.message || '未知错误'));
            return;
        }
        const tbody = document.getElementById('lottery-records-body');
        const pagination = document.getElementById('lottery-records-pagination');
        if (tbody) tbody.innerHTML = data.rows_html || '';
        if (pagination) pagination.innerHTML = data.pagination_html || '';
    })
    .catch(() => {
        alert('网络异常，刷新记录失败');
    });
}

let currentExpenseBtn = null;

function quickExpense(recordId, userName, prizeName, projectId, btn) {
    currentExpenseBtn = btn;
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

    fetch('lottery_records.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
        if (data.success) {
            hideModal('quick-expense-modal');
            refreshLotteryRecords();
            showAdminToast('报销成功 · 已生成报销单 #' + data.expense_id);
            if (currentExpenseBtn) {
                currentExpenseBtn.outerHTML = '<span class="text-xs text-gray-400">—</span>';
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

document.addEventListener('DOMContentLoaded', () => {
    makeModalDraggable('quick-expense-modal');
});
</script>

<?php require dirname(__DIR__) . '/views/admin/footer.php'; ?>
