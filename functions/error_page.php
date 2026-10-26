<?php
/**
 * Trang lỗi dùng chung (400, 401, 403, 404, 405): cùng khung, phông và màu với phần còn lại của hệ thống,
 * có một h1 nói rõ chuyện gì xảy ra và một lối đi tiếp. Thay cho exit('404: ...') trần.
 */

/** Tiêu đề ngắn cho từng mã lỗi. */
function errorTitle(int $code): string
{
    return [
        400 => 'Yêu cầu không hợp lệ',
        401 => 'Bạn chưa đăng nhập',
        403 => 'Bạn không có quyền truy cập',
        404 => 'Không tìm thấy',
        405 => 'Phương thức không được hỗ trợ',
    ][$code] ?? 'Có lỗi xảy ra';
}

/** Thư mục gốc của ứng dụng tính từ tệp đang chạy (views/, handle/ hoặc gốc), không có dấu / cuối. */
function appBasePath(?string $scriptName = null): string
{
    $script = $scriptName ?? ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = dirname($script);
    if (preg_match('~/(views|handle|api)$~', $dir)) {
        $dir = dirname($dir);
    }
    return rtrim($dir === '\\' ? '/' : $dir, '/');
}

/** Dựng HTML trang lỗi (thuần, kiểm thử được). $message là câu giải thích, đã là văn bản thường. */
function errorPageHtml(int $code, string $message, string $base = ''): string
{
    $esc = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $title = errorTitle($code);
    $next = $code === 401
        ? ['Đăng nhập', $base . '/index.php']
        : ['Về danh sách dự án', $base . '/views/projects.php'];
    return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $esc($title) . ' · Feature Flag Manager</title>'
        . '<link rel="stylesheet" href="' . $esc($base) . '/css/style.css"></head><body>'
        . '<main class="errpage"><div class="errpage-box">'
        . '<p class="errpage-code num">Lỗi ' . $code . '</p>'
        . '<h1>' . $esc($title) . '</h1>'
        . '<p class="sub">' . $esc($message) . '</p>'
        . '<p><a class="btn btn-primary" href="' . $esc($next[1]) . '">' . $esc($next[0]) . '</a></p>'
        . '</div></main></body></html>';
}

/** Trả mã lỗi kèm trang lỗi có giao diện rồi dừng. */
function abortWith(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo errorPageHtml($code, $message, appBasePath());
    exit();
}
