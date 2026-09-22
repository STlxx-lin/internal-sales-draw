<?php
// Isolated transaction regression tests; never connects to the application database.
function getUserIP() { return '127.0.0.1'; }
function checkUser($pdo, $ip) { return ['id' => 1]; }
function logUserAction($action, $status, $detail) {}

class LotteryTestDatabase
{
    public $times = 1;
    public $stock = 1;
    public $records = 0;
    public $active = false;
    public $snapshot;
    public $failure = '';
    public $name = '测试奖品';
    public $probability = 100;
    public $queries = [];
    public function beginTransaction() {
        if ($this->failure === 'begin') { throw new PDOException('private database details'); }
        $this->snapshot = [$this->times, $this->stock, $this->records];
        $this->active = true;
    }
    public function inTransaction() { return $this->active; }
    public function commit() { $this->active = false; }
    public function rollBack() {
        if (!$this->active) { throw new Exception('Rollback without transaction'); }
        list($this->times, $this->stock, $this->records) = $this->snapshot;
        $this->active = false;
    }
    public function prepare($sql) {
        $this->queries[] = $sql;
        return new LotteryTestStatement($this, $sql);
    }
}

class LotteryTestStatement
{
    private $db;
    private $sql;
    private $affected = 0;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
    public function execute($params) {
        if (strpos($this->sql, 'UPDATE user_project_times') === 0) {
            if ($this->db->times > 0) { $this->db->times--; $this->affected = 1; }
        } elseif (strpos($this->sql, 'UPDATE prizes') === 0) {
            if ($this->db->failure !== 'stock' && $this->db->stock > 0) {
                $this->db->stock--; $this->affected = 1;
            }
        } elseif (strpos($this->sql, 'INSERT INTO lottery_records') === 0) {
            if ($this->db->failure === 'insert') { throw new PDOException('private database details'); }
            $this->db->records++;
        }
    }
    public function fetch() {
        if (strpos($this->sql, 'FROM projects') !== false) { return ['id' => 1]; }
        return ['remaining_times' => $this->db->times];
    }
    public function fetchAll() {
        return $this->db->stock > 0 ? [[
            'id' => 1, 'name' => $this->db->name, 'image' => null,
            'remaining_quantity' => $this->db->stock, 'probability' => $this->db->probability
        ]] : [];
    }
    public function rowCount() { return $this->affected; }
}

function draw($pdo) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $source = file_get_contents(__DIR__ . '/../lottery.php');
    $source = str_replace("require_once __DIR__ . '/config.php';", '', $source);
    $source = str_replace("file_get_contents('php://input')", "'{\"project_id\":1}'", $source);
    ob_start();
    eval('?>' . $source);
    return json_decode(ob_get_clean(), true);
}
function verify($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}

$db = new LotteryTestDatabase();
$result = draw($db);
verify($result['success'] && $result['remaining_times'] === 0, 'Last chance should succeed');
verify($db->stock === 0 && $db->records === 1 && !$db->active, 'Draw should commit once');
verify(count(array_filter($db->queries, function ($sql) { return strpos($sql, 'FOR UPDATE') !== false; })) === 2, 'Chance and inventory reads must lock');
verify(!draw($db)['success'] && $db->records === 1, 'Exhausted chance should reject a second draw');

foreach (['begin', 'insert', 'stock'] as $failure) {
    $db = new LotteryTestDatabase();
    $db->failure = $failure;
    $result = draw($db);
    verify(!$result['success'], 'Failure should reject: ' . $failure);
    verify($db->times === 1 && $db->stock === 1 && $db->records === 0 && !$db->active, 'Failure should leave no partial changes');
    verify(strpos($result['message'], 'private') === false, 'Database details should stay private');
}
foreach (['empty', 'no-win', 'unlimited', 'zero-probability'] as $case) {
    $db = new LotteryTestDatabase();
    if ($case === 'empty') { $db->stock = 0; }
    if ($case === 'no-win') { $db->name = '没有中奖'; }
    if ($case === 'unlimited') { $db->stock = 999999; }
    if ($case === 'zero-probability') { $db->probability = 0; }
    $stock = $db->stock;
    $result = draw($db);
    verify($result['success'] && $db->times === 0 && $db->records === 1, 'Draw should be recorded: ' . $case);
    verify($db->stock === $stock, 'Stock should remain unchanged: ' . $case);
    verify(($result['prize'] !== null) === ($case === 'unlimited'), 'Prize result mismatch: ' . $case);
}
echo "PASS: transaction, rollback, stock, exhausted chances and result regressions\n";
