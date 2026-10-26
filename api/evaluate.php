<?php
/**
 * API trả kết quả bật/tắt tính năng.
 *   GET /api/evaluate.php?key=<API_KEY>&user=<MÃ_NGƯỜI_DÙNG>&feature=<MÃ_TÍNH_NĂNG>
 * Bỏ "feature" để lấy kết quả của mọi tính năng. API key cũng có thể gửi qua header X-Api-Key.
 */
require_once __DIR__ . '/../functions/decision_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(405, ['error' => 'method_not_allowed', 'message' => 'Chỉ hỗ trợ GET.']);
}

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
$user = trim($_GET['user'] ?? '');
$feature = isset($_GET['feature']) && $_GET['feature'] !== '' ? $_GET['feature'] : null;

try {
    $env = getEnvironmentByApiKey((string)$apiKey);
    if (!$env) {
        respond(401, ['error' => 'invalid_api_key', 'message' => 'API key không hợp lệ.']);
    }
    if ($user === '' || strlen($user) > 60) {
        respond(400, ['error' => 'invalid_user', 'message' => 'Thiếu hoặc sai tham số user.']);
    }
    $r = evaluateForEnvironment($env, $user, $feature);
    if ($feature !== null) {
        respond(200, ['feature' => $feature, 'enabled' => $r['results'][$feature]['enabled'],
            'reason' => $r['results'][$feature]['reason'], 'version' => $r['version'], 'environment' => $env['env_key']]);
    }
    respond(200, ['version' => $r['version'], 'environment' => $env['env_key'], 'flags' => $r['results']]);
} catch (OutOfBoundsException $e) {
    respond(404, ['error' => 'unknown_feature', 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    respond(503, ['error' => 'no_published_version', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[ffm-api] ' . $e->getMessage());
    respond(500, ['error' => 'server_error', 'message' => 'Lỗi máy chủ.']);
}
