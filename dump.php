<?php
require 'config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "--- $t ---\n";
    print_r($pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC)['Create Table']);
    echo "\n\n";
}
