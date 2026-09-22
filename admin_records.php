<?php
function adminRecordSummary($pdo) {
    return $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(prize_id IS NOT NULL AND NOT is_used),0) AS pending, COALESCE(SUM(is_used),0) AS used FROM (SELECT lr.prize_id, EXISTS(SELECT 1 FROM expense_records er WHERE er.project_id=lr.project_id AND er.user_id=lr.user_id AND er.is_used=1 AND er.reason REGEXP CONCAT('抽奖记录 #',lr.id,'([^0-9]|$)')) AS is_used FROM lottery_records lr) summary")->fetch();
}

function adminRecords($pdo, $page, $keyword = '', $status = 'all') {
    $where = [];
    $params = [];
    $used = "EXISTS(SELECT 1 FROM expense_records er WHERE er.project_id=lr.project_id AND er.user_id=lr.user_id AND er.is_used=1 AND er.reason REGEXP CONCAT('抽奖记录 #',lr.id,'([^0-9]|$)'))";
    if ($keyword !== '') {
        $where[] = '(u.name LIKE ? OR pr.name LIKE ? OR p.name LIKE ? OR CAST(lr.id AS CHAR) = ?)';
        $params = ['%' . $keyword . '%', '%' . $keyword . '%', '%' . $keyword . '%', $keyword];
    }
    if ($status === 'pending') { $where[] = 'lr.prize_id IS NOT NULL AND NOT ' . $used; }
    if ($status === 'used') { $where[] = $used; }
    $from = ' FROM lottery_records lr JOIN users u ON lr.user_id=u.id JOIN projects pr ON lr.project_id=pr.id LEFT JOIN prizes p ON lr.prize_id=p.id';
    $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare('SELECT COUNT(*)' . $from . $condition);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $pages = (int)ceil($total / 20);
    $page = min(max(1, (int)$page), max(1, $pages));
    $stmt = $pdo->prepare('SELECT lr.*, u.name AS user_name, u.ip_address, p.name AS prize_name, pr.name AS project_name, ' . $used . ' AS is_used' . $from . $condition . ' ORDER BY lr.created_at DESC, lr.id DESC LIMIT 20 OFFSET ' . (($page - 1) * 20));
    $stmt->execute($params);
    return ['records' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}
