<?php
// Run: C:\xampp\php\php.exe casddd/test-support/data_cache_test.php
// Uses only connection-local temporary tables; never changes application tables.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === '--post-bootstrap') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    require_once __DIR__ . '/../includes/data_cache.php';
    echo (string)file_get_contents(data_cache_dir() . '/generation');
    exit;
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
require_once __DIR__ . '/../includes/data_cache.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
function check_cache(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$conn = new mysqli(getenv('MYSQLHOST') ?: '127.0.0.1', getenv('MYSQLUSER') ?: 'root',
    getenv('MYSQLPASSWORD') ?: '', getenv('MYSQLDATABASE') ?: 'db2', (int)(getenv('MYSQLPORT') ?: 3306));
$table = 'cache_test_' . bin2hex(random_bytes(6));
$conn->query("CREATE TEMPORARY TABLE $table (id INT PRIMARY KEY, label VARCHAR(40))");
$conn->query("INSERT INTO $table VALUES (1, 'one'), (2, 'two')");
$sql = "SELECT id, label FROM $table ORDER BY id";
data_cache_invalidate();
$first = cached_query($conn, $sql);
$expected = $first->fetch_all(MYSQLI_BOTH);
$second = cached_query($conn, $sql);
check_cache($second instanceof DataCacheResult, 'direct SELECT hits cache');
check_cache($second->fetch_all(MYSQLI_BOTH) === $expected, 'cached values and PHP types match native result');
check_cache($second->data_seek(0) && $second->fetch_assoc() === ['id' => '1', 'label' => 'one'], 'seek and associative fetch work');
check_cache($second->data_seek(1) && $second->fetch_row() === ['2', 'two'], 'numeric fetch works');
$emptySql = "SELECT id FROM $table WHERE id = 999";
cached_query($conn, $emptySql);
$empty = cached_query($conn, $emptySql);
check_cache($empty instanceof DataCacheResult && $empty->num_rows === 0 && $empty->fetch_assoc() === null, 'empty results are cached');
$stmt = cached_prepare($conn, "SELECT id, label FROM $table WHERE id = ?");
$id = 1; cached_bind_param($stmt, 'i', $id);
cached_execute($stmt); $row = cached_get_result($stmt)->fetch_assoc();
cached_execute($stmt); $hit = cached_get_result($stmt);
check_cache($hit instanceof DataCacheResult && $hit->fetch_assoc() === $row, 'prepared SELECT hits cache');
$id = 2; cached_execute($stmt); $row2 = cached_get_result($stmt)->fetch_assoc();
check_cache((int)$row2['id'] === 2, 'rebound parameters use separate cache keys');
$gen = @file_get_contents(data_cache_dir() . '/generation');
cached_query($conn, "UPDATE $table SET label = label WHERE id = 1");
check_cache(@file_get_contents(data_cache_dir() . '/generation') === $gen, 'no-op update preserves warm entries');
cached_query($conn, "UPDATE $table SET label = 'changed' WHERE id = 1");
$fresh = cached_query($conn, $sql);
check_cache($fresh instanceof mysqli_result && $fresh->fetch_assoc()['label'] === 'changed', 'writes invalidate cached reads');
$_SERVER['REQUEST_METHOD'] = 'POST';
check_cache(cached_query($conn, $sql) instanceof mysqli_result, 'mutating requests bypass cache');
$_SERVER['REQUEST_METHOD'] = 'GET';
putenv('APP_CACHE_ENABLED=0');
check_cache(cached_query($conn, $sql) instanceof mysqli_result, 'environment switch disables caching');
putenv('APP_CACHE_ENABLED');
check_cache(!data_cache_eligible("SELECT password FROM users"), 'password reads bypass cache');
check_cache(!data_cache_eligible("SELECT id FROM $table FOR UPDATE"), 'locking reads bypass cache');
$key = data_cache_key($conn, $sql);
data_cache_write($key, $expected);
touch(data_cache_dir() . '/' . $key . '.cache', time() - data_cache_ttl() - 1);
clearstatcache();
check_cache(data_cache_read($key) === null, 'expired entries are ignored');
$_SESSION['user_id'] = 101;
$key1 = data_cache_key($conn, $sql);
$_SESSION['user_id'] = 102;
check_cache($key1 !== data_cache_key($conn, $sql), 'users have separate cache keys');
unset($_SESSION['user_id']);
$pdo = new PDO('mysql:host=' . (getenv('MYSQLHOST') ?: '127.0.0.1') . ';dbname=' . (getenv('MYSQLDATABASE') ?: 'db2') . ';port=' . (getenv('MYSQLPORT') ?: 3306),
    getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TEMPORARY TABLE $table (id INT, label VARCHAR(40))");
$pdo->exec("INSERT INTO $table VALUES (1, 'pdo-one'), (2, 'pdo-two')");
$pdoSql = "SELECT id, label FROM $table WHERE id = :id";
$rows = cached_pdo_rows($pdo, $pdoSql, [':id' => 1]);
$pdo->exec("UPDATE $table SET label = 'uncached-change' WHERE id = 1");
check_cache(cached_pdo_rows($pdo, $pdoSql, [':id' => 1]) === $rows, 'PDO SELECT reuses cached rows');
check_cache(cached_pdo_rows($pdo, $pdoSql, [':id' => 2])[0]['label'] === 'pdo-two', 'PDO parameters use separate entries');
data_cache_invalidate();
check_cache(cached_pdo_rows($pdo, $pdoSql, [':id' => 1])[0]['label'] === 'uncached-change', 'shared invalidation refreshes PDO reads');
$pdo->beginTransaction();
check_cache(cached_pdo_rows($pdo, $pdoSql, [':id' => 1])[0]['label'] === 'uncached-change', 'PDO transaction reads bypass cache');
$pdo->rollBack();
data_cache_invalidate();

$before = file_get_contents(data_cache_dir() . '/generation');
$pipes = [];
$child = proc_open([PHP_BINARY, __FILE__, '--post-bootstrap'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($child)) throw new RuntimeException('Cannot start request-boundary check');
$during = stream_get_contents($pipes[1]); fclose($pipes[1]);
$errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
$status = proc_close($child);
$after = file_get_contents(data_cache_dir() . '/generation');
check_cache($status === 0 && $errors === '' && $before !== $during && $during !== $after,
    'POST bootstrap and shutdown both invalidate native-write caches');
check_cache(!data_cache_eligible('SELECT NOW() AS t'), 'database clock queries stay fresh');
$_SERVER['SCRIPT_FILENAME'] = 'chat_feed.php';
check_cache(data_cache_ttl() === max(1, (int)(getenv('APP_CACHE_TTL') ?: 120)), 'live feeds use the configured two-minute default');
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
echo "All cache integration checks passed.\n";
