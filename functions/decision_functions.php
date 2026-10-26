<?php
/** Trả kết quả bật/tắt cho API và mô phỏng trên dữ liệu giả. */
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/evaluator.php';
require_once __DIR__ . '/version_functions.php';
require_once __DIR__ . '/project_functions.php';

function getEnvironmentByApiKey(string $apiKey): ?array
{
    if ($apiKey === '') {
        return null;
    }
    return dbOne('SELECT * FROM environments WHERE api_key = ?', 's', [$apiKey]);
}

/**
 * Đánh giá một hoặc mọi tính năng của môi trường cho một người dùng cuối.
 * @return array ['version'=>int, 'results'=>[flag_key => ['enabled'=>bool,'reason'=>string]]]
 * @throws RuntimeException khi môi trường chưa có bản phát hành, OutOfBoundsException khi tính năng không tồn tại
 */
function evaluateForEnvironment(array $env, string $endUserId, ?string $flagKey = null): array
{
    if (empty($env['active_version_id'])) {
        throw new RuntimeException('Môi trường chưa có bản cấu hình được phát hành.');
    }
    $version = getVersion((int)$env['active_version_id']);
    $cfg = loadVersionConfig((int)$env['active_version_id']);
    $groups = getUserGroupKeys((int)$env['project_id'], $endUserId);

    $keys = $flagKey === null ? array_keys($cfg['flags']) : [$flagKey];
    $results = [];
    foreach ($keys as $k) {
        if (!isset($cfg['flags'][$k])) {
            throw new OutOfBoundsException('Không có tính năng "' . $k . '" trong bản đang phát hành.');
        }
        $results[$k] = ffmEvaluate($cfg['flags'][$k], $k, $env['env_key'], $endUserId, $groups);
        if (ffmConfig()['log_decisions'] ?? false) {
            dbExec('INSERT INTO decision_logs (environment_id, version_id, flag_key, end_user_id, enabled, reason) VALUES (?, ?, ?, ?, ?, ?)',
                'iissis', [$env['id'], $env['active_version_id'], $k, $endUserId, (int)$results[$k]['enabled'], $results[$k]['reason']]);
        }
    }
    return ['version' => (int)$version['version_no'], 'results' => $results];
}

/** Mô phỏng một version trên toàn bộ người dùng giả của dự án: số người được bật cho từng tính năng. */
function simulateVersion(int $versionId): array
{
    $v = getVersion($versionId);
    $cfg = loadVersionConfig($versionId);
    $users = array_column(dbAll('SELECT end_user_id FROM end_users WHERE project_id = ?', 'i', [$v['project_id']]), 'end_user_id');
    $groupMap = [];
    foreach (dbAll('SELECT g.group_key, gm.end_user_id FROM group_members gm JOIN user_groups g ON g.id = gm.group_id
                    WHERE g.project_id = ?', 'i', [$v['project_id']]) as $r) {
        $groupMap[$r['end_user_id']][] = $r['group_key'];
    }
    $out = [];
    foreach ($cfg['flags'] as $key => $flag) {
        $on = 0;
        foreach ($users as $u) {
            if (ffmEvaluate($flag, $key, $v['env_key'], $u, $groupMap[$u] ?? [])['enabled']) {
                $on++;
            }
        }
        $out[$key] = ['on' => $on, 'total' => count($users)];
    }
    return $out;
}

function getRecentDecisions(int $envId, int $limit = 20): array
{
    return dbAll('SELECT d.*, v.version_no FROM decision_logs d JOIN versions v ON v.id = d.version_id
                  WHERE d.environment_id = ? ORDER BY d.id DESC LIMIT ' . (int)$limit, 'i', [$envId]);
}
