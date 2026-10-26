<?php
// Một số bản cài PHP CLI không có php-mbstring: định nghĩa bản thay thế tối thiểu cho UTF-8.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int
    {
        return preg_match_all('/./us', $s);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s, int $start, ?int $len = null): string
    {
        preg_match_all('/./us', $s, $m);
        return implode('', array_slice($m[0], $start, $len));
    }
}
/**
 * Kết nối CSDL và các hàm truy vấn dùng chung (mysqli + prepared statement).
 * Thông số kết nối đọc từ config/config.local.php (không đưa lên Git)
 * hoặc biến môi trường FFM_DB_HOST/USER/PASS/NAME/PORT (dùng cho CI/kiểm thử).
 */

function ffmConfig(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $file = __DIR__ . '/../config/config.local.php';
    $cfg = is_file($file) ? require $file : [
        'db' => ['host' => '127.0.0.1', 'user' => 'ffm', 'pass' => '', 'name' => 'ffm', 'port' => 3306],
        'log_decisions' => true,
    ];
    foreach (['host', 'user', 'pass', 'name', 'port'] as $k) {
        $env = getenv('FFM_DB_' . strtoupper($k));
        if ($env !== false && $env !== '') {
            $cfg['db'][$k] = $k === 'port' ? (int)$env : $env;
        }
    }
    return $cfg;
}

function getDbConnection(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }
    $c = ffmConfig()['db'];
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($c['host'], $c['user'], $c['pass'], $c['name'], (int)$c['port']);
    $conn->set_charset('utf8mb4');
    return $conn;
}

/** Chạy SELECT, trả về mảng các dòng. $types theo mysqli: i, s, d. */
function dbAll(string $sql, string $types = '', array $params = []): array
{
    $stmt = getDbConnection()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function dbOne(string $sql, string $types = '', array $params = []): ?array
{
    $rows = dbAll($sql, $types, $params);
    return $rows[0] ?? null;
}

/** Chạy INSERT/UPDATE/DELETE, trả về số dòng bị ảnh hưởng. */
function dbExec(string $sql, string $types = '', array $params = []): int
{
    $stmt = getDbConnection()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function dbInsertId(): int
{
    return (int)getDbConnection()->insert_id;
}

/**
 * Chạy nhiều lệnh trong một giao dịch; lỗi thì rollback và ném lại ngoại lệ.
 * Cho phép gọi lồng nhau: chỉ giao dịch ngoài cùng mới BEGIN/COMMIT.
 */
function dbTransaction(callable $fn)
{
    static $depth = 0;
    $conn = getDbConnection();
    if ($depth > 0) {
        return $fn();
    }
    $conn->begin_transaction();
    $depth++;
    try {
        $result = $fn();
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    } finally {
        $depth--;
    }
}
