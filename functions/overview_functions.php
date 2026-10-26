<?php
/**
 * Truy vấn chỉ đọc phục vụ giao diện: tổng quan dự án, mức phủ của từng tính năng,
 * và "ai được quyết định bởi quy tắc nào". Không ghi CSDL, không đổi hành vi của evaluator.
 */
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/evaluator.php';
require_once __DIR__ . '/config_tools.php';
require_once __DIR__ . '/version_functions.php';
require_once __DIR__ . '/decision_functions.php';

/** Mỗi dự án người dùng tham gia, kèm số liệu để hiện ở danh sách. */
function ffmProjectSummaries(int $userId): array
{
    return dbAll(
        "SELECT p.id, p.name, p.description, p.created_at, m.role,
            (SELECT COUNT(*) FROM flags f WHERE f.project_id = p.id) AS flag_count,
            (SELECT COUNT(*) FROM memberships mm WHERE mm.project_id = p.id) AS member_count,
            (SELECT COUNT(*) FROM versions v JOIN environments e ON e.id = v.environment_id
              WHERE e.project_id = p.id AND v.status = 'draft') AS draft_count,
            (SELECT MAX(v.published_at) FROM versions v JOIN environments e ON e.id = v.environment_id
              WHERE e.project_id = p.id) AS last_release
         FROM projects p JOIN memberships m ON m.project_id = p.id
         WHERE m.user_id = ? ORDER BY p.name", 'i', [$userId]);
}

/** Số tính năng, số tính năng bật mặc định và số quy tắc của một phiên bản. */
function ffmVersionStats(int $versionId): array
{
    $r = dbOne('SELECT COUNT(*) AS flags, COALESCE(SUM(default_enabled), 0) AS defaults_on
                FROM version_flags WHERE version_id = ?', 'i', [$versionId]);
    $q = dbOne('SELECT COUNT(*) AS n FROM rules WHERE version_id = ?', 'i', [$versionId]);
    return ['flags' => (int)$r['flags'], 'defaults_on' => (int)$r['defaults_on'], 'rules' => (int)$q['n']];
}

/** Người dùng giả của dự án và nhóm của từng người: [$users, $groupMap]. */
function ffmSimUsers(int $projectId): array
{
    $users = array_column(dbAll('SELECT end_user_id FROM end_users WHERE project_id = ? ORDER BY end_user_id', 'i', [$projectId]), 'end_user_id');
    $groupMap = [];
    foreach (dbAll('SELECT g.group_key, gm.end_user_id FROM group_members gm JOIN user_groups g ON g.id = gm.group_id
                    WHERE g.project_id = ?', 'i', [$projectId]) as $r) {
        $groupMap[$r['end_user_id']][] = $r['group_key'];
    }
    return [$users, $groupMap];
}

/**
 * Thuần (không CSDL): với một cấu hình tính năng, mỗi người dùng được quyết định bởi quy tắc nào.
 * Dùng đúng ffmEvaluate nên khớp với API. Các đoạn theo thứ tự xét: từng quy tắc (priority tăng dần), rồi mặc định.
 *
 * @return array ['total'=>int, 'on'=>int, 'segments'=>[['reason','label','count','enabled'], ...]]
 */
function ffmBreakdownFromConfig(array $flagCfg, string $flagKey, string $envKey, array $users, array $groupMap): array
{
    $rules = array_values($flagCfg['rules'] ?? []);
    $idx = [];
    foreach ($rules as $i => $r) {
        $idx[] = [$r, $i];
    }
    usort($idx, fn($a, $b) => [(int)$a[0]['priority'], $a[1]] <=> [(int)$b[0]['priority'], $b[1]]);

    $segments = [];
    foreach ($idx as [$r]) {
        $reason = 'rule:' . $r['type'] . '#p' . (int)$r['priority'];
        if (!isset($segments[$reason])) {
            $segments[$reason] = ['reason' => $reason, 'label' => ffmDescribeRule($r + ['users' => [], 'group' => '', 'percentage' => 100]),
                'count' => 0, 'enabled' => (bool)$r['value']];
        }
    }
    $segments['default'] = ['reason' => 'default', 'label' => 'Mặc định của tính năng', 'count' => 0, 'enabled' => (bool)($flagCfg['default'] ?? false)];

    $on = 0;
    foreach ($users as $u) {
        $res = ffmEvaluate($flagCfg, $flagKey, $envKey, $u, $groupMap[$u] ?? []);
        $segments[$res['reason']]['count']++;
        $on += $res['enabled'] ? 1 : 0;
    }
    return ['total' => count($users), 'on' => $on, 'segments' => array_values($segments)];
}

/** Phân rã quyết định của một tính năng trong một phiên bản trên toàn bộ người dùng giả. */
function ffmFlagBreakdown(int $versionId, string $flagKey): ?array
{
    $v = getVersion($versionId);
    $cfg = loadVersionConfig($versionId);
    if (!$v || !isset($cfg['flags'][$flagKey])) {
        return null;
    }
    [$users, $groupMap] = ffmSimUsers((int)$v['project_id']);
    return ffmBreakdownFromConfig($cfg['flags'][$flagKey], $flagKey, $v['env_key'], $users, $groupMap);
}

/**
 * Thuần (không CSDL): trong số $listed, bao nhiêu người đã bị các quy tắc xét TRƯỚC ($earlierRules) quyết định rồi,
 * nên không bao giờ tới lượt quy tắc đang xét. Dùng đúng ffmEvaluate nên khớp với API.
 *
 * @return array ['listed'=>int, 'taken'=>int, 'by'=>['p1'=>3, 'p2'=>1, ...]]  ('by' xếp theo ưu tiên tăng dần)
 */
function ffmShadowedBy(array $earlierRules, array $listed, string $flagKey, string $envKey, array $groupMap): array
{
    $cfg = ['default' => false, 'rules' => $earlierRules];
    $by = [];
    $taken = 0;
    foreach (array_unique($listed) as $u) {
        $e = ffmEvaluate($cfg, $flagKey, $envKey, $u, $groupMap[$u] ?? []);
        if ($e['reason'] !== 'default') {
            $taken++;
            $p = preg_replace('~^rule:[a-z_]+#~', '', $e['reason']);
            $by[$p] = ($by[$p] ?? 0) + 1;
        }
    }
    uksort($by, fn($a, $b) => (int)substr($a, 1) <=> (int)substr($b, 1));
    return ['listed' => count(array_unique($listed)), 'taken' => $taken, 'by' => $by];
}

/** Mức phủ (số người được bật / tổng) của mọi tính năng trong phiên bản đang phát hành của từng môi trường. */
function ffmCoverageByEnvironment(array $envs): array
{
    $out = [];
    foreach ($envs as $env) {
        $out[$env['id']] = !empty($env['active_version_id']) ? simulateVersion((int)$env['active_version_id']) : [];
    }
    return $out;
}

/** Với mỗi phiên bản (mới → cũ): số thay đổi so với phiên bản liền trước trong cùng môi trường. */
function ffmChangeCounts(array $versions): array
{
    $byNo = [];
    foreach ($versions as $v) {
        $byNo[(int)$v['version_no']] = (int)$v['id'];
    }
    $out = [];
    foreach ($versions as $v) {
        $prev = $byNo[(int)$v['version_no'] - 1] ?? null;
        $out[$v['id']] = $prev ? count(compareVersions($prev, (int)$v['id'])) : null;
    }
    return $out;
}

/** Mã người dùng cuối thuộc một nhóm (để điền sẵn vào ô sửa thành viên). */
function ffmGroupMemberIds(int $groupId): array
{
    return array_column(dbAll('SELECT end_user_id FROM group_members WHERE group_id = ? ORDER BY end_user_id', 'i', [$groupId]), 'end_user_id');
}

/** Các thay đổi mới nhất trong mọi dự án mà người dùng tham gia. */
function ffmRecentActivity(int $userId, int $limit = 8): array
{
    return dbAll('SELECT c.created_at, c.action, c.reason, c.version_id, u.username, v.version_no,
                         e.id AS env_id, e.name AS env_name, p.id AS project_id, p.name AS project_name
                  FROM change_logs c
                  JOIN environments e ON e.id = c.environment_id
                  JOIN projects p ON p.id = e.project_id
                  JOIN memberships m ON m.project_id = p.id AND m.user_id = ?
                  JOIN users u ON u.id = c.user_id
                  LEFT JOIN versions v ON v.id = c.version_id
                  ORDER BY c.id DESC LIMIT ' . (int)$limit, 'i', [$userId]);
}

/** Tài khoản đăng nhập của hệ thống (để chọn thành viên dự án thay vì gõ tên). */
function ffmAllAccounts(): array
{
    return dbAll('SELECT id, username, full_name FROM users ORDER BY username');
}
