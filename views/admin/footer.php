            </div>
            <!-- 子页面内容结束 -->
        </div>
    </div>

    <!-- 全局提示 Toast 容器 -->
    <div id="admin-toast" class="admin-toast" role="status" hidden></div>

    <script>
    // 通用 Toast 提示函数
    function showAdminToast(message) {
        let toast = document.getElementById('admin-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'admin-toast';
            toast.className = 'admin-toast';
            toast.setAttribute('role', 'status');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.hidden = false;
        clearTimeout(window.adminToastTimer);
        window.adminToastTimer = setTimeout(() => {
            toast.hidden = true;
        }, 5000);
    }

    // 通用模态框显示与隐藏控制
    function showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            const dialog = modal.querySelector('.bg-white');
            if (dialog && typeof dialog.resetPosition === 'function') {
                dialog.resetPosition();
            }
        }
    }

    // 模态框拖拽支持
    function makeModalDraggable(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        const dialog = modal.querySelector('.bg-white');
        if (!dialog) return;

        let header = dialog.querySelector('.modal-drag-handle');
        if (!header) {
            header = dialog.children[0];
            if (header) {
                header.classList.add('modal-drag-handle');
                header.style.cursor = 'move';
            }
        }
        if (!header) return;

        let isDragging = false;
        let startX, startY;
        let currentX = 0, currentY = 0;

        header.addEventListener('mousedown', function(e) {
            if (e.button !== 0 || e.target.closest('button') || e.target.closest('input') || e.target.closest('select')) return;
            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            e.preventDefault();
        });

        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            e.preventDefault();
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            startX = e.clientX;
            startY = e.clientY;
            currentX += dx;
            currentY += dy;
            dialog.style.transform = `translate(${currentX}px, ${currentY}px)`;
        });

        document.addEventListener('mouseup', function() {
            isDragging = false;
        });

        dialog.resetPosition = function() {
            currentX = 0;
            currentY = 0;
            dialog.style.transform = `translate(0px, 0px)`;
        };
    }
    </script>
</body>
</html>
