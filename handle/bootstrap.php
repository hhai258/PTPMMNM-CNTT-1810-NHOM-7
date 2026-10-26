<?php
/** Dùng chung cho các tệp xử lý trong thư mục handle/. */
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/project_functions.php';
require_once __DIR__ . '/../functions/version_functions.php';
require_once __DIR__ . '/../functions/decision_functions.php';

startSessionOnce();

function redirectTo(string $url): void
{
    header('Location: ' . $url);
    exit();
}

function flash(string $type, string $message): void
{
    $_SESSION[$type] = $message;
}

/** Chỉ nhận POST, bắt buộc đăng nhập và đúng token CSRF. */
function guardPost(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        abortWith(405, 'Chỉ nhận POST.');
    }
    if (!isLoggedIn()) {
        abortWith(401, 'Chưa đăng nhập.');
    }
    requireCsrf();
    return getCurrentUser();
}

/** Số nguyên dương từ POST, hoặc 0 nếu thiếu/sai. */
function postInt(string $key): int
{
    $v = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    return $v !== false && $v > 0 ? $v : 0;
}

/** Chạy $fn; lỗi nghiệp vụ được báo lại cho người dùng và quay về $back. $fn trả về chuỗi thì chuỗi đó thay cho $okMessage. */
function runAction(callable $fn, string $back, string $okMessage): void
{
    try {
        $r = $fn();
        flash('success', is_string($r) && $r !== '' ? $r : $okMessage);
    } catch (InvalidArgumentException | RuntimeException $e) {
        flash('error', $e->getMessage());
    } catch (Throwable $e) {
        error_log('[ffm] ' . $e->getMessage());
        flash('error', 'Có lỗi hệ thống, vui lòng thử lại.');
    }
    redirectTo($back);
}
