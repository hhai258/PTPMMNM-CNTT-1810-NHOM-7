<?php
/**
 * Danh sách tổng hợp người dùng giả: mỗi người thuộc nhóm nào, kết quả bật/tắt của một tính năng
 * và quy tắc (kèm số ưu tiên) đã quyết định kết quả đó. Chỉ đọc; dùng đúng ffmEvaluate như API.
 * Các hàm ffm... không đụng CSDL nên kiểm thử được bằng tests/unit_tests.php.
 */
require_once __DIR__ . '/evaluator.php';
require_once __DIR__ . '/config_tools.php';
require_once __DIR__ . '/overview_functions.php';

/** Số ưu tiên nằm trong lý do "rule:group#p2" → 2; "default" → null. */
function ffmPriorityFromReason(string $reason): ?int
{
    return preg_match('/#p(\d+)$/', $reason, $m) ? (int)$m[1] : null;
}

/** Nhãn dễ đọc cho từng lý do của một tính năng: [reason => "p2 nhóm beta 50% → BẬT"]. */
function ffmReasonLabels(array $flagCfg): array
{
    $labels = ['default' => 'Mặc định của tính năng → ' . (!empty($flagCfg['default']) ? 'BẬT' : 'TẮT')];
    foreach ($flagCfg['rules'] ?? [] as $r) {
        $reason = 'rule:' . $r['type'] . '#p' . (int)$r['priority'];
        $labels[$reason] ??= ffmDescribeRule($r + ['users' => [], 'group' => '', 'percentage' => 100]);
    }
    return $labels;
}

/**
 * Các thành phần của từng lý do, để hiển thị riêng từng cột: [reason => ['priority'=>?int, 'rule'=>string, 'value'=>bool]].
 * 'rule' không chứa số ưu tiên và kết quả (vd "nhóm vip 100%"); lý do 'default' không có số ưu tiên.
 */
function ffmReasonParts(array $flagCfg): array
{
    $parts = ['default' => ['priority' => null, 'rule' => 'Mặc định của tính năng', 'value' => !empty($flagCfg['default'])]];
    foreach ($flagCfg['rules'] ?? [] as $r) {
        $reason = 'rule:' . $r['type'] . '#p' . (int)$r['priority'];
        $text = $r['type'] === 'user_list' ? 'danh sách ' . count($r['users'] ?? []) . ' người'
            : ($r['type'] === 'group' ? 'nhóm ' . $r['group'] . ' ' . $r['percentage'] . '%' : 'tỷ lệ ' . $r['percentage'] . '%');
        $parts[$reason] ??= ['priority' => (int)$r['priority'], 'rule' => $text, 'value' => !empty($r['value'])];
    }
    return $parts;
}

/**
 * Mỗi dòng: người dùng, các nhóm, kết quả và quy tắc quyết định của một tính năng.
 * @return array[] ['user'=>, 'groups'=>[], 'enabled'=>bool, 'reason'=>, 'priority'=>?int, 'label'=>, 'rule'=>]
 */
function ffmBuildDirectory(array $users, array $groupMap, array $flagCfg, string $flagKey, string $envKey): array
{
    $labels = ffmReasonLabels($flagCfg);
    $parts = ffmReasonParts($flagCfg);
    $rows = [];
    foreach ($users as $u) {
        $groups = $groupMap[$u] ?? [];
        $res = ffmEvaluate($flagCfg, $flagKey, $envKey, $u, $groups);
        $rows[] = ['user' => $u, 'groups' => $groups, 'enabled' => $res['enabled'], 'reason' => $res['reason'],
            'priority' => ffmPriorityFromReason($res['reason']), 'label' => $labels[$res['reason']] ?? $res['reason'],
            'rule' => $parts[$res['reason']]['rule'] ?? $res['reason']];
    }
    return $rows;
}

/**
 * Lọc danh sách: $q = đoạn mã người dùng, $group = mã nhóm ('' = mọi nhóm, '_none' = không thuộc nhóm nào),
 * $result = 'on' | 'off' | '' , $reason = lý do quyết định ('' = mọi lý do).
 */
function ffmFilterDirectory(array $rows, string $q = '', string $group = '', string $result = '', string $reason = ''): array
{
    $q = strtolower(trim($q));
    return array_values(array_filter($rows, function ($r) use ($q, $group, $result, $reason) {
        if ($q !== '' && strpos(strtolower($r['user']), $q) === false) return false;
        if ($group === '_none' && $r['groups']) return false;
        if ($group !== '' && $group !== '_none' && !in_array($group, $r['groups'], true)) return false;
        if ($result === 'on' && !$r['enabled']) return false;
        if ($result === 'off' && $r['enabled']) return false;
        if ($reason !== '' && $r['reason'] !== $reason) return false;
        return true;
    }));
}

/** Cắt trang: trả [các dòng của trang, tổng số trang, trang hiện tại đã được kẹp vào khoảng hợp lệ]. */
function ffmPaginate(array $rows, int $page, int $per = 50): array
{
    $per = max(1, $per);
    $pages = max(1, (int)ceil(count($rows) / $per));
    $page = min(max(1, $page), $pages);
    return [array_slice($rows, ($page - 1) * $per, $per), $pages, $page];
}

/** Số người theo từng nhóm và số người không thuộc nhóm nào. */
function ffmGroupCounts(array $users, array $groupMap): array
{
    $out = ['_none' => 0];
    foreach ($users as $u) {
        $gs = $groupMap[$u] ?? [];
        if (!$gs) {
            $out['_none']++;
        }
        foreach ($gs as $g) {
            $out[$g] = ($out[$g] ?? 0) + 1;
        }
    }
    return $out;
}

/** Kết quả của MỌI tính năng cho một người dùng trong một cấu hình: [flag_key => [enabled, reason, priority, label]]. */
function ffmUserDecisions(array $cfg, string $envKey, string $user, array $groups): array
{
    $out = [];
    foreach ($cfg['flags'] as $key => $flagCfg) {
        $res = ffmEvaluate($flagCfg, (string)$key, $envKey, $user, $groups);
        $labels = ffmReasonLabels($flagCfg);
        $out[$key] = ['enabled' => $res['enabled'], 'reason' => $res['reason'], 'priority' => ffmPriorityFromReason($res['reason']),
            'label' => $labels[$res['reason']] ?? $res['reason']];
    }
    return $out;
}

/** Dữ liệu người dùng giả để chọn nhiều người: [id, danh sách nhóm của từng người] cho giao diện. */
function ffmPickerData(int $projectId): array
{
    [$users, $groupMap] = ffmSimUsers($projectId);
    $groups = [];
    foreach (getGroups($projectId) as $g) {
        $groups[(int)$g['id']] = ['key' => $g['group_key'], 'name' => $g['name'], 'members' => ffmGroupMemberIds((int)$g['id'])];
    }
    return ['users' => $users, 'userGroups' => (object)$groupMap, 'groups' => $groups];
}
