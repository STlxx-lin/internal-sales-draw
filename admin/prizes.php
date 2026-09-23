<?php
// admin/prizes.php - 奖品管理独立页面
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/admin_records.php';

$message = '';
$error = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 获取所有项目
$projects = $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
$selected_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : ($projects[0]['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'add_prize':
                $project_id = (int)($_POST['project_id'] ?? $selected_project_id);
                $name = trim($_POST['name'] ?? '');
                $is_unlimited = isset($_POST['is_unlimited']) && $_POST['is_unlimited'] == '1';
                $quantity = $is_unlimited ? 999999 : (int)($_POST['quantity'] ?? 0);
                $probability = (float)($_POST['probability'] ?? 0);
                
                if (empty($name)) {
                    throw new Exception('奖品名称不能为空');
                }
                if (!$is_unlimited && $quantity <= 0) {
                    throw new Exception('奖品数量必须大于0');
                }
                if ($probability < 0 || $probability > 100) {
                    throw new Exception('中奖概率必须在0-100之间');
                }
                
                $stmt = $pdo->prepare("INSERT INTO prizes (project_id, name, total_quantity, remaining_quantity, probability) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$project_id, $name, $quantity, $quantity, $probability]);
                logAdminAction('add_prize', '成功', ['project_id' => $project_id, 'name' => $name, 'quantity' => $quantity, 'probability' => $probability], $admin_log_file);
                header('Location: prizes.php?project_id=' . $project_id . '&msg=' . urlencode('奖品添加成功'));
                exit;

            case 'edit_prize':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $is_unlimited = isset($_POST['is_unlimited']) && $_POST['is_unlimited'] == '1';
                $quantity = $is_unlimited ? 999999 : (int)($_POST['quantity'] ?? 0);
                $probability = (float)($_POST['probability'] ?? 0);
                
                if (empty($name)) {
                    throw new Exception('奖品名称不能为空');
                }
                if (!$is_unlimited && $quantity < 0) {
                    throw new Exception('奖品数量不能小于0');
                }
                if ($probability < 0 || $probability > 100) {
                    throw new Exception('中奖概率必须在0-100之间');
                }
                $stmt = $pdo->prepare("SELECT project_id FROM prizes WHERE id = ?");
                $stmt->execute([$id]);
                $project_id = (int)($stmt->fetchColumn() ?: 0);
                if (!$project_id) {
                    throw new Exception('奖品不存在');
                }
                
                $stmt = $pdo->prepare("UPDATE prizes SET name = ?, remaining_quantity = ?, probability = ? WHERE id = ?");
                $stmt->execute([$name, $quantity, $probability, $id]);
                logAdminAction('edit_prize', '成功', ['id' => $id, 'project_id' => $project_id, 'name' => $name, 'quantity' => $quantity, 'probability' => $probability], $admin_log_file);
                header('Location: prizes.php?project_id=' . $project_id . '&msg=' . urlencode('奖品更新成功'));
                exit;

            case 'delete_prize':
                $id = (int)($_POST['id'] ?? 0);
                $project_id = (int)($_GET['project_id'] ?? $selected_project_id);
                $stmt = $pdo->prepare("DELETE FROM prizes WHERE id = ?");
                $stmt->execute([$id]);
                logAdminAction('delete_prize', '成功', ['id' => $id, 'project_id' => $project_id], $admin_log_file);
                header('Location: prizes.php?project_id=' . $project_id . '&msg=' . urlencode('奖品已删除'));
                exit;

            case 'update_probabilities':
                header('Content-Type: application/json; charset=utf-8');
                try {
                    $project_id = (int)($_POST['project_id'] ?? 0);
                    $probabilities = $_POST['probabilities'] ?? [];
                    if (!is_array($probabilities)) {
                        throw new Exception('无效的概率数据格式');
                    }
                    $total_prob = 0;
                    foreach ($probabilities as $prize_id => $prob) {
                        $prob = (float)$prob;
                        if ($prob < 0 || $prob > 100) {
                            throw new Exception('概率值必须在0-100之间');
                        }
                        $total_prob += $prob;
                    }
                    if ($total_prob > 100) {
                        throw new Exception('总概率不能超过100%，当前为: ' . number_format($total_prob, 2) . '%');
                    }
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare("UPDATE prizes SET probability = ? WHERE id = ? AND project_id = ?");
                    foreach ($probabilities as $prize_id => $prob) {
                        $stmt->execute([(float)$prob, (int)$prize_id, $project_id]);
                    }
                    $pdo->commit();
                    logAdminAction('update_probabilities', '成功', ['project_id' => $project_id, 'probabilities' => $probabilities], $admin_log_file);
                    echo json_encode(['success' => true, 'message' => '概率调整成功']);
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }

            case 'get_prizes_data':
                header('Content-Type: application/json; charset=utf-8');
                try {
                    $project_id = (int)($_POST['project_id'] ?? 0);
                    $stmt = $pdo->prepare("SELECT id, name, probability FROM prizes WHERE project_id = ? ORDER BY sort_order, id");
                    $stmt->execute([$project_id]);
                    $p_list = $stmt->fetchAll();
                    $t_prob = 0;
                    foreach ($p_list as $p) {
                        $t_prob += (float)$p['probability'];
                    }
                    echo json_encode([
                        'success' => true,
                        'prizes' => $p_list,
                        'total_probability' => number_format($t_prob, 2)
                    ]);
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logAdminAction($action, '失败', $error, $admin_log_file);
    }
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

// 获取选定项目的奖品与配置
$prizes = [];
$total_probability = 0;
$current_project_info = null;
if ($selected_project_id) {
    foreach ($projects as $pr) {
        if ($pr['id'] == $selected_project_id) {
            $current_project_info = $pr;
            break;
        }
    }
    $stmt = $pdo->prepare("SELECT * FROM prizes WHERE project_id = ? ORDER BY sort_order, id");
    $stmt->execute([$selected_project_id]);
    $prizes = $stmt->fetchAll();
    foreach ($prizes as $prize) {
        $total_probability += $prize['probability'];
    }
}

$record_summary = adminRecordSummary($pdo);
$active_tab = 'prizes';
$page_title = '奖品管理';
require dirname(__DIR__) . '/views/admin/header.php';
?>

<div class="flex justify-between items-center mb-6 flex-wrap gap-4">
    <div class="flex items-center space-x-4 flex-wrap gap-2">
        <h2 class="text-xl font-bold text-gray-800">奖品管理</h2>
        <select onchange="location.href='prizes.php?project_id='+this.value" class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
            <?php foreach ($projects as $project): ?>
                <option value="<?php echo $project['id']; ?>" <?php echo $project['id'] == $selected_project_id ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($project['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($current_project_info): ?>
        <form method="POST" action="projects.php" class="inline-flex items-center">
            <input type="hidden" name="action" value="toggle_prize_level">
            <input type="hidden" name="id" value="<?php echo $current_project_info['id']; ?>">
            <input type="hidden" name="val" value="<?php echo empty($current_project_info['show_prize_level']) ? '1' : '0'; ?>">
            <input type="hidden" name="redirect" value="prizes.php?project_id=<?php echo $current_project_info['id']; ?>">
            <?php if (!empty($current_project_info['show_prize_level'])): ?>
                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition shadow-sm" title="点击关闭前端奖品等级显示（特等奖/一等奖等标签）">
                    <i class="fa fa-toggle-on text-emerald-600 mr-1.5 text-sm"></i>前端奖品等级：已显示
                </button>
            <?php else: ?>
                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 text-gray-600 border border-gray-300 hover:bg-gray-200 transition shadow-sm" title="点击开启前端奖品等级显示">
                    <i class="fa fa-toggle-off text-gray-400 mr-1.5 text-sm"></i>前端奖品等级：已隐藏
                </button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <div class="flex space-x-3">
        <button onclick="showModal('add-prize-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition flex items-center">
            <i class="fa fa-plus mr-2"></i>添加奖品
        </button>
        <button onclick="showModal('probability-adjust-modal')" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg transition flex items-center">
            <i class="fa fa-sliders mr-2"></i>快速调整概率
        </button>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th class="px-6 py-3">ID</th>
                <th class="px-6 py-3">奖品名称</th>
                <th class="px-6 py-3">剩余数量</th>
                <th class="px-6 py-3">中奖概率</th>
                <th class="px-6 py-3">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($prizes)): ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-400">该项目下暂无奖品，请点击右上角添加奖品</td>
            </tr>
            <?php else: ?>
                <?php foreach ($prizes as $prize): ?>
                <tr class="bg-white border-b" data-prize-id="<?php echo $prize['id']; ?>">
                    <td class="px-6 py-4"><?php echo $prize['id']; ?></td>
                    <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($prize['name']); ?></td>
                    <td class="px-6 py-4"><?php echo $prize['remaining_quantity'] >= 999999 ? '无限' : $prize['remaining_quantity']; ?></td>
                    <td class="px-6 py-4 probability-cell"><?php echo $prize['probability']; ?>%</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="user-action-group">
                            <button type="button" onclick="editPrize(<?php echo $prize['id']; ?>, '<?php echo htmlspecialchars($prize['name'], ENT_QUOTES); ?>', <?php echo $prize['remaining_quantity']; ?>, <?php echo $prize['probability']; ?>)" class="btn-action-icon btn-action-edit" title="编辑奖品">
                                <i class="fa fa-pencil-square-o"></i>
                            </button>
                            <button type="button" onclick="deletePrize(<?php echo $prize['id']; ?>)" class="btn-action-icon btn-action-delete" title="删除奖品">
                                <i class="fa fa-trash-o"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- 合计概率显示行 -->
                <tr class="bg-red-50 border-b-2 border-red-200">
                    <td class="px-6 py-4 font-bold text-red-700" colspan="3">合计概率</td>
                    <td class="px-6 py-4 font-bold text-red-700 text-lg total-probability-cell"><?php echo number_format($total_probability, 2); ?>%</td>
                    <td class="px-6 py-4"></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 添加奖品模态框 -->
<div id="add-prize-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">添加奖品</h3>
        <form method="POST" action="prizes.php?project_id=<?php echo $selected_project_id; ?>">
            <input type="hidden" name="action" value="add_prize">
            <input type="hidden" name="project_id" value="<?php echo $selected_project_id; ?>">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">奖品名称</label>
                <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required placeholder="请输入奖品名称">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">奖品数量</label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="radio" name="quantity_type" value="unlimited" class="mr-2" checked onchange="toggleQuantityInput(this)">
                        <span>无限数量</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="quantity_type" value="limited" class="mr-2" onchange="toggleQuantityInput(this)">
                        <span>限定数量</span>
                    </label>
                </div>
                <input type="hidden" name="is_unlimited" value="1">
                <input type="number" name="quantity" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-2 hidden" placeholder="请输入数量">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">中奖概率(%)</label>
                <input type="number" name="probability" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2" required placeholder="0.00">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('add-prize-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    添加
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑奖品模态框 -->
<div id="edit-prize-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">编辑奖品</h3>
        <form method="POST" action="prizes.php?project_id=<?php echo $selected_project_id; ?>">
            <input type="hidden" name="action" value="edit_prize">
            <input type="hidden" name="id" id="edit-prize-id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">奖品名称</label>
                <input type="text" name="name" id="edit-prize-name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">剩余数量</label>
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="radio" name="quantity_type" value="unlimited" class="mr-2" onchange="toggleEditQuantityInput(this)">
                        <span>无限数量</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="quantity_type" value="limited" class="mr-2" onchange="toggleEditQuantityInput(this)">
                        <span>限定数量</span>
                    </label>
                </div>
                <input type="hidden" name="is_unlimited" value="0">
                <input type="number" name="quantity" id="edit-prize-quantity" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-2" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">中奖概率(%)</label>
                <input type="number" name="probability" id="edit-prize-probability" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('edit-prize-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    更新
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 概率调整模态框 -->
<div id="probability-adjust-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
        <h3 class="text-lg font-bold mb-4 text-gray-800">快速调整概率</h3>
        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-1">当前项目：<span class="font-medium text-gray-800"><?php echo htmlspecialchars($projects[array_search($selected_project_id, array_column($projects, 'id'))]['name'] ?? '未选择'); ?></span></p>
            <p class="text-sm text-gray-600 mb-4">当前总概率：<span id="current-total-probability" class="font-medium text-red-600"><?php echo number_format($total_probability, 2); ?>%</span></p>
        </div>
        
        <div class="max-h-96 overflow-y-auto mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-2 text-left">奖品名称</th>
                        <th class="px-4 py-2 text-left">当前概率</th>
                        <th class="px-4 py-2 text-left">新概率</th>
                        <th class="px-4 py-2 text-left">滑动调整</th>
                    </tr>
                </thead>
                <tbody id="probability-adjust-table">
                    <?php foreach ($prizes as $prize): ?>
                    <tr class="border-b">
                        <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($prize['name']); ?></td>
                        <td class="px-4 py-2"><?php echo $prize['probability']; ?>%</td>
                        <td class="px-4 py-2">
                            <input type="number" 
                                   class="probability-input w-20 border border-gray-300 rounded px-2 py-1 text-sm" 
                                   data-prize-id="<?php echo $prize['id']; ?>"
                                   value="<?php echo $prize['probability']; ?>" 
                                   min="0" 
                                   max="100" 
                                   step="0.01"
                                   onchange="updateProbabilitySlider(this)">
                            <span class="text-xs text-gray-500">%</span>
                        </td>
                        <td class="px-4 py-2">
                            <input type="range" 
                                   class="probability-slider w-24" 
                                   data-prize-id="<?php echo $prize['id']; ?>"
                                   value="<?php echo $prize['probability']; ?>" 
                                   min="0" 
                                   max="100" 
                                   step="0.01"
                                   oninput="updateProbabilityInput(this)">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
            <p class="text-sm text-yellow-800">
                <i class="fa fa-exclamation-triangle mr-1"></i>
                提示：调整后的总概率为 <span id="new-total-probability" class="font-bold">0.00%</span>
            </p>
        </div>
        
        <div class="flex justify-between items-center flex-wrap gap-2">
            <div class="flex space-x-2">
                <button type="button" onclick="resetProbabilities()" class="px-3 py-2 text-sm bg-gray-500 text-white rounded hover:bg-gray-600">
                    <i class="fa fa-refresh mr-1"></i>重置
                </button>
                <button type="button" onclick="averageProbabilities()" class="px-3 py-2 text-sm bg-orange-500 text-white rounded hover:bg-orange-600">
                    <i class="fa fa-balance-scale mr-1"></i>平均分配
                </button>
            </div>
            <div class="flex space-x-3">
                <button type="button" onclick="hideModal('probability-adjust-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="button" onclick="saveProbabilities()" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                    <i class="fa fa-save mr-1"></i>保存调整
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function editPrize(id, name, quantity, probability) {
    document.getElementById('edit-prize-id').value = id;
    document.getElementById('edit-prize-name').value = name;
    document.getElementById('edit-prize-probability').value = probability;
    
    const quantityInput = document.getElementById('edit-prize-quantity');
    const unlimitedRadio = document.querySelector('#edit-prize-modal input[value="unlimited"]');
    const limitedRadio = document.querySelector('#edit-prize-modal input[value="limited"]');
    const isUnlimitedInput = document.querySelector('#edit-prize-modal input[name="is_unlimited"]');
    
    if (quantity >= 999999) {
        unlimitedRadio.checked = true;
        quantityInput.classList.add('hidden');
        quantityInput.required = false;
        quantityInput.value = '';
        isUnlimitedInput.value = '1';
    } else {
        limitedRadio.checked = true;
        quantityInput.classList.remove('hidden');
        quantityInput.required = true;
        quantityInput.value = quantity;
        isUnlimitedInput.value = '0';
    }
    showModal('edit-prize-modal');
}

function deletePrize(id) {
    if (confirm('确定要删除这个奖品吗？')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'prizes.php?project_id=<?php echo $selected_project_id; ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_prize';
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

function toggleQuantityInput(radio) {
    const form = radio.closest('form');
    const quantityInput = form.querySelector('input[name="quantity"]');
    const isUnlimitedInput = form.querySelector('input[name="is_unlimited"]');
    if (radio.value === 'limited') {
        quantityInput.classList.remove('hidden');
        quantityInput.required = true;
        isUnlimitedInput.value = '0';
    } else {
        quantityInput.classList.add('hidden');
        quantityInput.required = false;
        quantityInput.value = '';
        isUnlimitedInput.value = '1';
    }
}

function toggleEditQuantityInput(radio) {
    const form = radio.closest('form');
    const quantityInput = form.querySelector('#edit-prize-quantity');
    const isUnlimitedInput = form.querySelector('input[name="is_unlimited"]');
    if (radio.value === 'limited') {
        quantityInput.classList.remove('hidden');
        quantityInput.required = true;
        isUnlimitedInput.value = '0';
    } else {
        quantityInput.classList.add('hidden');
        quantityInput.required = false;
        quantityInput.value = '';
        isUnlimitedInput.value = '1';
    }
}

function updateProbabilitySlider(input) {
    const prizeId = input.dataset.prizeId;
    const slider = document.querySelector(`.probability-slider[data-prize-id="${prizeId}"]`);
    if (slider) slider.value = input.value;
    updateTotalProbability();
}

function updateProbabilityInput(slider) {
    const prizeId = slider.dataset.prizeId;
    const input = document.querySelector(`.probability-input[data-prize-id="${prizeId}"]`);
    if (input) input.value = slider.value;
    updateTotalProbability();
}

function updateTotalProbability() {
    let total = 0;
    document.querySelectorAll('.probability-input').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    const span = document.getElementById('new-total-probability');
    if (span) {
        span.textContent = total.toFixed(2) + '%';
        span.className = total > 100 ? 'font-bold text-red-600' : 'font-bold text-green-600';
    }
}

function resetProbabilities() {
    document.querySelectorAll('.probability-input').forEach(input => {
        const row = input.closest('tr');
        const currentProb = parseFloat(row.cells[1].textContent) || 0;
        input.value = currentProb;
        updateProbabilitySlider(input);
    });
}

function averageProbabilities() {
    const inputs = document.querySelectorAll('.probability-input');
    if (inputs.length === 0) return;
    const avg = (100 / inputs.length).toFixed(2);
    inputs.forEach(input => {
        input.value = avg;
        updateProbabilitySlider(input);
    });
}

function saveProbabilities() {
    const probabilities = {};
    let total = 0;
    document.querySelectorAll('.probability-input').forEach(input => {
        const prizeId = input.dataset.prizeId;
        const prob = parseFloat(input.value) || 0;
        probabilities[prizeId] = prob;
        total += prob;
    });
    if (total > 100) {
        alert('总概率不能超过100%，当前为: ' + total.toFixed(2) + '%');
        return;
    }
    const formData = new FormData();
    formData.append('action', 'update_probabilities');
    formData.append('project_id', '<?php echo $selected_project_id; ?>');
    for (const id in probabilities) {
        formData.append('probabilities[' + id + ']', probabilities[id]);
    }
    fetch('prizes.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('概率调整成功！');
            hideModal('probability-adjust-modal');
            location.reload();
        } else {
            alert('调整失败: ' + data.message);
        }
    })
    .catch(() => alert('网络错误，保存失败'));
}

document.addEventListener('DOMContentLoaded', () => {
    updateTotalProbability();
});
</script>

<?php require dirname(__DIR__) . '/views/admin/footer.php'; ?>
