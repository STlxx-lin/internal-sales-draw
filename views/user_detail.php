<?php
// Render the user detail response from escaped database values.
ob_start();
?>
<div class="ud-profile">
    <div class="ud-avatar"><i class="fa fa-user"></i></div>
    <div><h4><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></h4><p>用户 #<?= (int)$user['id'] ?> <span>·</span> <?= htmlspecialchars($user['ip_address'], ENT_QUOTES) ?></p></div>
    <div class="ud-joined"><small>加入时间</small><span><?= date('Y-m-d', strtotime($user['created_at'])) ?></span></div>
</div>
<div class="ud-stats">
    <div><span>剩余机会</span><strong><?= (int)array_sum(array_column($times_list, 'remaining_times')) ?></strong><small>已分配项目合计</small></div>
    <div><span>累计抽奖</span><strong><?= (int)$user_stats['total'] ?></strong><small>全部历史记录</small></div>
    <div><span>累计中奖</span><strong><?= (int)$user_stats['wins'] ?></strong><small>不含未中奖记录</small></div>
    <div class="ud-stat-accent"><span>待报销</span><strong><?= (int)$user_stats['pending'] ?></strong><small>中奖且尚未报销</small></div>
</div>
<?php $info_html = ob_get_clean(); ob_start(); ?>
<div class="ud-tabs" role="tablist" aria-label="用户详情分类">
    <button type="button" id="ud-tab-projects" role="tab" aria-selected="true" aria-controls="ud-projects" onclick="switchUserDetailTab('projects')">项目与次数 <span><?= count($times_list) ?></span></button>
    <button type="button" id="ud-tab-records" role="tab" aria-selected="false" aria-controls="ud-records" onclick="switchUserDetailTab('records')">抽奖与报销 <span><?= (int)$user_stats['total'] ?></span></button>
    <button type="button" id="ud-tab-activity" role="tab" aria-selected="false" aria-controls="ud-activity" onclick="switchUserDetailTab('activity')">分配日志</button>
</div>
<section id="ud-projects" role="tabpanel" aria-labelledby="ud-tab-projects" class="ud-section">
    <div class="ud-section-heading"><h4>参与项目</h4><p>查看各项目的机会分配与访问权限</p></div>
    <?php if (!$times_list): ?><div class="ud-empty"><i class="fa fa-folder-open-o"></i><strong>还没有分配项目</strong><p>为该用户分配抽奖次数后，项目会显示在这里。</p></div><?php endif; ?>
    <?php foreach ($times_list as $row): ?>
    <article class="ud-project">
        <div class="ud-project-top"><h5><?= htmlspecialchars($row['project_name'], ENT_QUOTES) ?></h5><span class="ud-badge <?= $row['is_visible'] ? 'ud-good' : '' ?>"><?= $row['is_visible'] ? '用户可见' : '已隐藏' ?></span></div>
        <div class="ud-project-numbers"><div><span>已分配</span><b><?= (int)$row['total_times'] ?></b> 次</div><div><span>剩余可用</span><b><?= (int)$row['remaining_times'] ?></b> 次</div></div>
    </article>
    <?php endforeach; ?>
</section>
<section id="ud-records" role="tabpanel" aria-labelledby="ud-tab-records" class="ud-section" hidden>
    <div class="ud-records-header flex justify-between items-center mb-4 flex-wrap gap-3">
        <div class="ud-section-heading mb-0">
            <h4>抽奖与报销记录</h4>
            <p id="ud-records-count-desc">最近 <?= count($record_list) ?> 条 · 累计 <?= (int)$user_stats['total'] ?> 条（中奖 <?= (int)$user_stats['wins'] ?> 次，待报销 <?= (int)$user_stats['pending'] ?> 笔）</p>
        </div>
        <!-- 筛选与搜索工具条 -->
        <div class="ud-records-filter-bar flex items-center gap-2 flex-wrap">
            <div class="ud-filter-search-box relative">
                <i class="fa fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
                <input type="text" id="ud-record-keyword" placeholder="搜索奖品/项目/编号" oninput="applyUserRecordsFilter()" class="pl-8 pr-3 py-1.5 text-xs bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 w-44">
            </div>
            <div class="ud-filter-tags flex items-center bg-gray-100 p-0.5 rounded-lg border border-gray-200">
                <button type="button" class="ud-filter-btn active" data-status="all" onclick="setUserRecordFilterStatus('all', this)">全部</button>
                <button type="button" class="ud-filter-btn" data-status="pending" onclick="setUserRecordFilterStatus('pending', this)">
                    待报销 <span class="ud-filter-count"><?= (int)$user_stats['pending'] ?></span>
                </button>
                <button type="button" class="ud-filter-btn" data-status="used" onclick="setUserRecordFilterStatus('used', this)">已报销</button>
                <button type="button" class="ud-filter-btn" data-status="miss" onclick="setUserRecordFilterStatus('miss', this)">未中奖</button>
            </div>
        </div>
    </div>
    <?php if (!$record_list): ?>
        <div class="ud-empty">
            <i class="fa fa-gift"></i>
            <strong>暂无抽奖记录</strong>
            <p>参与活动完成抽奖后，奖品与报销进度将清晰记录于此。</p>
        </div>
    <?php endif; ?>
    <div id="ud-records-empty-match" class="ud-empty" style="display: none;">
        <i class="fa fa-filter"></i>
        <strong>没有匹配的记录</strong>
        <p>可以尝试更换状态标签或清空搜索关键词。</p>
    </div>
    <div class="ud-records-list space-y-3" id="ud-records-list">
    <?php foreach ($record_list as $row): 
        $is_win = (bool)$row['prize_id'];
        $is_used = (bool)$row['is_used'];
        $status_key = !$is_win ? 'miss' : ($is_used ? 'used' : 'pending');
        $prize_name = $row['prize_name'] ?? '未中奖';
        $project_name = $row['project_name'] ?? '';
    ?>
    <article class="ud-record-card <?= $is_win ? ($is_used ? 'is-used' : 'is-pending') : 'is-empty' ?>" 
             data-status="<?= $status_key ?>" 
             data-id="<?= (int)$row['id'] ?>"
             data-prize="<?= htmlspecialchars(mb_strtolower($prize_name), ENT_QUOTES) ?>" 
             data-project="<?= htmlspecialchars(mb_strtolower($project_name), ENT_QUOTES) ?>">
        <div class="ud-record-icon <?= $is_win ? 'is-win' : 'is-miss' ?>">
            <i class="fa <?= $is_win ? 'fa-gift' : 'fa-meh-o' ?>"></i>
        </div>
        <div class="ud-record-main">
            <div class="flex items-center gap-2 flex-wrap">
                <strong class="ud-prize-title <?= $is_win ? 'text-gray-900 font-bold' : 'text-gray-400 font-medium' ?>">
                    <?= htmlspecialchars($prize_name, ENT_QUOTES) ?>
                </strong>
                <span class="ud-tag-project"><?= htmlspecialchars($project_name, ENT_QUOTES) ?></span>
            </div>
            <div class="ud-record-meta">
                <span class="font-mono">#<?= (int)$row['id'] ?></span>
                <span>·</span>
                <span><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></span>
                <?php if ($is_used && !empty($row['expense_amount'])): ?>
                    <span>·</span>
                    <span class="text-teal-700 font-medium">报销单 #<?= (int)$row['expense_id'] ?> (¥<?= number_format((float)$row['expense_amount'], 2) ?>)</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ud-record-action">
            <?php if (!$is_win): ?>
                <span class="ud-badge ud-badge-miss">未中奖</span>
            <?php elseif ($is_used): ?>
                <span class="ud-badge ud-badge-used"><i class="fa fa-check-circle mr-1"></i>已报销</span>
            <?php else: ?>
                <button type="button" class="ud-btn-expense" data-user-id="<?= (int)$user['id'] ?>" onclick="quickExpense(<?= (int)$row['id'] ?>, <?= htmlspecialchars(json_encode($user['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($row['prize_name']), ENT_QUOTES) ?>, <?= (int)$row['project_id'] ?>, this)">
                    <i class="fa fa-bolt mr-1"></i>快速报销
                </button>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
    </div>
</section>
<section id="ud-activity" role="tabpanel" aria-labelledby="ud-tab-activity" class="ud-section" hidden>
    <div class="ud-section-heading"><h4>次数分配记录</h4><p>展示该用户在数据库中保存的次数分配历史记录</p></div>
    <?php if (!$times_records): ?><div class="ud-empty"><i class="fa fa-history"></i><strong>暂无分配日志</strong><p>当前日志范围内未找到该用户的分配操作。</p></div><?php endif; ?>
    <?php foreach ($times_records as $row): ?>
    <article class="ud-activity"><div><strong><?= htmlspecialchars($row['project_name'], ENT_QUOTES) ?></strong><p><?= htmlspecialchars($row['action'], ENT_QUOTES) ?> · <?= htmlspecialchars($row['status'], ENT_QUOTES) ?></p><small><?= htmlspecialchars($row['time'] . ' · ' . $row['ip'], ENT_QUOTES) ?></small></div><span class="ud-delta"><?= $row['times'] > 0 ? '+' : '' ?><?= (int)$row['times'] ?> 次</span></article>
    <?php endforeach; ?>
</section>
<?php $detail_html = ob_get_clean(); ?>
