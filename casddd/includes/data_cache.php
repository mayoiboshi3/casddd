<?php
/**
 * Short-lived query-result cache. No rendered pages, passwords or login checks are cached.
 * APP_CACHE_ENABLED=0 disables caching; APP_CACHE_TTL defaults to 120 seconds.
 */
function data_cache_dir(): string {
    $dir = sys_get_temp_dir() . '/casddd-cache-' . substr(hash('sha256', __DIR__), 0, 16);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir;
}
function data_cache_invalidate(): void {
    @file_put_contents(data_cache_dir() . '/generation', bin2hex(random_bytes(16)), LOCK_EX);
}
function data_cache_eligible(string $sql): bool {
    return getenv('APP_CACHE_ENABLED') !== '0' && is_writable(data_cache_dir())
        && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && !in_array(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), ['index.php', 'login.php', 'reset_password.php', 'check_push_notifications.php', 'register_push_device.php', 'get_user.php'], true)
        && preg_match('/^\s*SELECT\b/i', $sql)
        && preg_match('/\bFROM\b/i', $sql)
        && !preg_match('/\b(password|password_resets|push_devices|push_sessions|GET_LOCK|RELEASE_LOCK|FOR\s+UPDATE|LOCK\s+IN\s+SHARE|RAND|UUID|SLEEP|FOUND_ROWS|SQL_CALC_FOUND_ROWS|LAST_INSERT_ID)\b|@|\bINTO\b/i', $sql);
}
function data_cache_key(mysqli $conn, string $sql, array $params = []): string {

    static $scopes;
    $scopes ??= new WeakMap();
    if (!isset($scopes[$conn])) {
        $scopes[$conn] = [$conn->host_info, $conn->character_set_name(),
            $conn->query('SELECT DATABASE(), CURRENT_USER(), @@session.time_zone, @@session.sql_mode')->fetch_row()];
    }
    return hash('sha256', serialize(['mysqli-v2', $scopes[$conn], session_id(), $_SESSION['user_id'] ?? null,
        $_SESSION['role'] ?? null, $sql, $params, @file_get_contents(data_cache_dir() . '/generation')]));

}
function data_cache_ttl(): int {
    return max(1, (int)(getenv('APP_CACHE_TTL') ?: 120));
}
function data_cache_read(string $key): ?array {
    $file = data_cache_dir() . '/' . $key . '.cache';
    $mtime = @filemtime($file);
    if (!$mtime || time() - $mtime >= data_cache_ttl()) {
        if ($mtime) @unlink($file);
        return null;
    }
    $rows = @unserialize((string)@file_get_contents($file), ['allowed_classes' => false]);
    return is_array($rows) ? $rows : null;
}
function data_cache_write(string $key, array $rows): void {
    $bytes = serialize($rows);
    if (strlen($bytes) > 2097152) return;
    $tmp = @tempnam(data_cache_dir(), 'write-');
    if ($tmp === false) return;
    @chmod($tmp, 0600);
    if (@file_put_contents($tmp, $bytes, LOCK_EX) !== false)
        @rename($tmp, data_cache_dir() . '/' . $key . '.cache');
    if (is_file($tmp)) @unlink($tmp);
    if (mt_rand(1, 100) === 1) {
        foreach (glob(data_cache_dir() . '/*.cache') ?: [] as $file)
            if (@filemtime($file) < time() - max(300, (int)getenv('APP_CACHE_TTL'))) @unlink($file);
    }
}
final class DataCacheResult {
    private array $rows;
    private int $position = 0;
    public int $num_rows;
    public function __construct(array $rows) { $this->rows = $rows; $this->num_rows = count($rows); }
    public function fetch_array(int $mode = MYSQLI_BOTH): ?array {
        $row = $this->rows[$this->position++] ?? null;
        if ($row === null) return null;
        if ($mode === MYSQLI_BOTH) return $row;
        return array_filter($row, $mode === MYSQLI_ASSOC ? 'is_string' : 'is_int', ARRAY_FILTER_USE_KEY);
    }
    public function fetch_assoc(): ?array { return $this->fetch_array(MYSQLI_ASSOC); }
    public function fetch_row(): ?array { return $this->fetch_array(MYSQLI_NUM); }
    public function fetch_all(int $mode = MYSQLI_NUM): array {
        $rows = []; while (($row = $this->fetch_array($mode)) !== null) $rows[] = $row; return $rows;
    }
    public function data_seek(int $offset): bool {
        if ($offset < 0 || $offset >= $this->num_rows) return false;
        $this->position = $offset; return true;
    }
    public function free(): void { $this->rows = []; $this->num_rows = 0; }
}
function data_cache_capture(mysqli_result $result): array {
    $rows = $result->fetch_all(MYSQLI_BOTH);
    if ($result->num_rows > 0) $result->data_seek(0);
    return $rows;
}
function cached_query(mysqli $conn, string $sql, int $mode = MYSQLI_STORE_RESULT) {
    $read = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|SET)\b/i', $sql);
    $key = data_cache_eligible($sql) && $mode === MYSQLI_STORE_RESULT ? data_cache_key($conn, $sql) : null;
    if ($key !== null && ($rows = data_cache_read($key)) !== null) return new DataCacheResult($rows);
    $result = $conn->query($sql, $mode);
    // No-op housekeeping UPDATEs run on page loads; keep warm entries when nothing changed.
    if (!$read && $result !== false && ($conn->affected_rows > 0 || preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i', $sql))) {
        data_cache_invalidate();
    }
    if ($key !== null && $result instanceof mysqli_result) data_cache_write($key, data_cache_capture($result));
    return $result;
}
function cached_statements(): WeakMap { static $map; return $map ??= new WeakMap(); }
function cached_prepare(mysqli $conn, string $sql) {
    $stmt = $conn->prepare($sql);
    if ($stmt) cached_statements()[$stmt] = ['sql' => $sql, 'conn' => $conn, 'params' => []];
    return $stmt;
}
function cached_bind_param(mysqli_stmt $stmt, string $types, &...$params): bool {
    $map = cached_statements();
    if (isset($map[$stmt])) {
        $state = $map[$stmt]; $state['params'] = &$params; $map[$stmt] = $state;
    }
    return $stmt->bind_param($types, ...$params);
}
function cached_execute(mysqli_stmt $stmt, ?array $params = null): bool {
    $map = cached_statements(); $state = $map[$stmt] ?? null;
    if (!$state) return $params === null ? $stmt->execute() : $stmt->execute($params);
    $sql = $state['sql'];
    $read = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|SET)\b/i', $sql);
    $state['key'] = data_cache_eligible($sql) ? data_cache_key($state['conn'], $sql, $params ?? $state['params']) : null;
    $state['rows'] = $state['key'] !== null ? data_cache_read($state['key']) : null;
    $map[$stmt] = $state;
    if ($state['rows'] !== null) return true;
    $ok = $params === null ? $stmt->execute() : $stmt->execute($params);
    if (!$read && $ok && $stmt->affected_rows > 0) data_cache_invalidate();
    return $ok;
}
function cached_get_result(mysqli_stmt $stmt) {
    $state = cached_statements()[$stmt] ?? [];
    if (isset($state['rows'])) return new DataCacheResult($state['rows']);
    $result = $stmt->get_result();
    if ($result && isset($state['key'])) data_cache_write($state['key'], data_cache_capture($result));
    return $result;
}
function cached_fetch_assoc($result) { return $result->fetch_assoc(); }
function cached_fetch_row($result) { return $result->fetch_row(); }
function cached_fetch_array($result, int $mode = MYSQLI_BOTH) { return $result->fetch_array($mode); }
function cached_num_rows($result): int { return $result->num_rows; }
function cached_data_seek($result, int $offset): bool { return $result->data_seek($offset); }
function cached_free_result($result): void { $result->free(); }

/** Cache PDO reads without replacing PDOStatement or changing its fetch behavior. */
function cached_pdo_rows(PDO $pdo, string $sql, array $params = []): array {
    static $scopes;
    $scopes ??= new WeakMap();
    if (!isset($scopes[$pdo])) {
        $scopes[$pdo] = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) . ':' .
            serialize($pdo->query('SELECT DATABASE(), CURRENT_USER(), @@session.time_zone, @@session.sql_mode')->fetch(PDO::FETCH_NUM));
    }
    $key = data_cache_eligible($sql) && !$pdo->inTransaction()
        ? hash('sha256', serialize(['pdo-v2', $scopes[$pdo], session_id(), $_SESSION['user_id'] ?? null,
            $_SESSION['role'] ?? null, $sql, $params, @file_get_contents(data_cache_dir() . '/generation')]))
        : null;
    if ($key !== null && ($rows = data_cache_read($key)) !== null) return $rows;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($key !== null) data_cache_write($key, $rows);
    return $rows;
}
// Also covers native mysqli/PDO writes in existing handlers, including handlers that exit.
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    data_cache_invalidate();
    register_shutdown_function('data_cache_invalidate');
}
