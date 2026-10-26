<?php
/**
 * Thư viện gọi API bật/tắt tính năng. Khi không lấy được cấu hình (API tắt, quá hạn,
 * sai key, JSON hỏng...) trả về giá trị dự phòng, ghi lỗi và để ứng dụng chạy tiếp.
 */

/** Giá trị dự phòng đã quy định: tính năng mới TẮT, tính năng ổn định BẬT. */
const FFM_FALLBACK = [
    'new_checkout' => false, 'dark_mode' => false, 'product_reviews' => true, 'fast_search' => true,
    'wishlist' => true, 'live_chat' => false, 'promo_banner' => false, 'one_click_buy' => false,
    'recommendations' => false, 'beta_dashboard' => false,
];

/** Đọc cấu hình: tệp config.local.php, các biến môi trường FFM_API_BASE / FFM_API_KEY (nếu có) được ưu tiên hơn. */
function ffmDemoConfig(): array
{
    $file = __DIR__ . '/config.local.php';
    $cfg = (is_file($file) ? require $file : []) + [
        'api_base' => 'http://127.0.0.1:8080/api/evaluate.php',
        'api_key' => '',
        'timeout' => 2,
    ];
    foreach (['api_base' => 'FFM_API_BASE', 'api_key' => 'FFM_API_KEY'] as $k => $env) {
        $v = getenv($env);
        if ($v !== false && $v !== '') {
            $cfg[$k] = $v;
        }
    }
    return $cfg;
}

function ffmLogError(string $msg): void
{
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/error.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

/**
 * @return array ['source' => 'api'|'fallback', 'version' => ?int, 'flags' => [key => bool], 'error' => ?string]
 */
function ffmFetchFlags(string $endUserId): array
{
    $cfg = ffmDemoConfig();
    $url = $cfg['api_base'] . '?' . http_build_query(['user' => $endUserId, 'key' => $cfg['api_key']]);
    $ctx = stream_context_create(['http' => ['timeout' => (float)$cfg['timeout'], 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    $data = $body === false ? null : json_decode($body, true);
    if ($body === false || $status !== 200 || !is_array($data) || !isset($data['flags']) || !is_array($data['flags'])) {
        $why = $body === false ? 'không kết nối được API' : 'API trả mã ' . $status . ' hoặc dữ liệu sai';
        ffmLogError("Dùng giá trị dự phòng cho user=$endUserId: $why");
        return ['source' => 'fallback', 'version' => null, 'flags' => FFM_FALLBACK, 'error' => $why];
    }
    $flags = FFM_FALLBACK;                     // tính năng API không nhắc tới vẫn có giá trị dự phòng
    foreach ($data['flags'] as $k => $r) {
        $flags[$k] = (bool)($r['enabled'] ?? false);
    }
    return ['source' => 'api', 'version' => $data['version'] ?? null, 'flags' => $flags, 'error' => null];
}
