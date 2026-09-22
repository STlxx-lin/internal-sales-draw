<?php
// admin.php - 兼容旧入口，302 重定向至 admin/ 子目录
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$target = 'admin/index.php' . ($queryString !== '' ? '?' . $queryString : '');
header('Location: ' . $target, true, 302);
exit;
