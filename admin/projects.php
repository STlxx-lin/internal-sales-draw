<?php
// admin/projects.php - 项目管理独立页面
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/admin_records.php';

$message = '';
$error = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'add_project':
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    throw new Exception('项目名称不能为空');
                }
                $stmt = $pdo->prepare("INSERT INTO projects (name) VALUES (?)");
                $stmt->execute([$name]);
                logAdminAction('add_project', '成功', ['name' => $name], $admin_log_file);
                header('Location: projects.php?msg=' . urlencode('项目添加成功'));
                exit;

            case 'edit_project':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    throw new Exception('项目名称不能为空');
                }
                $stmt = $pdo->prepare("UPDATE projects SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                logAdminAction('edit_project', '成功', ['id' => $id, 'name' => $name], $admin_log_file);
                header('Location: projects.php?msg=' . urlencode('项目更新成功'));
                exit;

            case 'delete_project':
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->execute([$id]);
                logAdminAction('delete_project', '成功', ['id' => $id], $admin_log_file);
                header('Location: projects.php?msg=' . urlencode('项目已删除'));
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logAdminAction($action, '失败', $error, $admin_log_file);
    }
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
}

// 获取项目数据
$projects = $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
$record_summary = adminRecordSummary($pdo);

$active_tab = 'projects';
$page_title = '项目管理';
require dirname(__DIR__) . '/views/admin/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">项目管理</h2>
        <p class="text-xs text-gray-500 mt-1">创建和维护不同的抽奖活动与业务板块</p>
    </div>
    <button onclick="showModal('add-project-modal')" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition flex items-center">
        <i class="fa fa-plus mr-2"></i>添加项目
    </button>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm text-left">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th class="px-6 py-3">ID</th>
                <th class="px-6 py-3">项目名称</th>
                <th class="px-6 py-3">状态</th>
                <th class="px-6 py-3">创建时间</th>
                <th class="px-6 py-3">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($projects)): ?>
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-400">暂无项目，点击右上角添加项目</td>
            </tr>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                <tr class="bg-white border-b">
                    <td class="px-6 py-4"><?php echo $project['id']; ?></td>
                    <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($project['name']); ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $project['status'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $project['status'] ? '启用' : '禁用'; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4"><?php echo date('Y-m-d H:i', strtotime($project['created_at'])); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="user-action-group">
                            <button type="button" onclick="editProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>')" class="btn-action-icon btn-action-edit" title="编辑项目">
                                <i class="fa fa-pencil-square-o"></i>
                            </button>
                            <button type="button" onclick="deleteProject(<?php echo $project['id']; ?>)" class="btn-action-icon btn-action-delete" title="删除项目">
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

<!-- 添加项目模态框 -->
<div id="add-project-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">添加项目</h3>
        <form method="POST" action="projects.php">
            <input type="hidden" name="action" value="add_project">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">项目名称</label>
                <input type="text" name="name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required placeholder="请输入项目名称">
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('add-project-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    添加
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑项目模态框 -->
<div id="edit-project-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4 text-gray-800">编辑项目</h3>
        <form method="POST" action="projects.php">
            <input type="hidden" name="action" value="edit_project">
            <input type="hidden" name="id" id="edit-project-id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">项目名称</label>
                <input type="text" name="name" id="edit-project-name" class="w-full border border-gray-300 rounded-lg px-3 py-2" required>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideModal('edit-project-modal')" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
                    取消
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    更新
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editProject(id, name) {
    document.getElementById('edit-project-id').value = id;
    document.getElementById('edit-project-name').value = name;
    showModal('edit-project-modal');
}

function deleteProject(id) {
    if (confirm('确定要删除这个项目吗？相关的奖品和抽奖记录也会受到影响！')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'projects.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_project';
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
</script>

<?php require dirname(__DIR__) . '/views/admin/footer.php'; ?>
