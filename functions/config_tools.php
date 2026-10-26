<?php
/**
 * Kiểm tra dữ liệu nhập/xuất và so sánh hai bản cấu hình (không dùng CSDL).
 * Định dạng tệp: {"format":"ffm-config/1","flags":{"<flag_key>":{"default":false,"rules":[...]}}}
 */

const FFM_FORMAT = 'ffm-config/1';
const FFM_RULE_TYPES = ['user_list', 'group', 'percentage'];

/** Kiểm tra mảng cấu hình; trả về danh sách lỗi (rỗng nghĩa là hợp lệ). */
function ffmValidateConfig($cfg, array $knownFlagKeys, array $knownGroupKeys): array
{
    $errors = [];
    if (!is_array($cfg)) {
        return ['Dữ liệu không phải đối tượng JSON.'];
    }
    if (($cfg['format'] ?? null) !== FFM_FORMAT) {
        $errors[] = 'Thiếu hoặc sai "format" (cần "' . FFM_FORMAT . '").';
    }
    if (!isset($cfg['flags']) || !is_array($cfg['flags'])) {
        $errors[] = 'Thiếu mục "flags".';
        return $errors;
    }
    foreach ($cfg['flags'] as $key => $f) {
        $key = (string)$key;
        if (!in_array($key, $knownFlagKeys, true)) {
            $errors[] = "Tính năng \"$key\" chưa được khai báo trong dự án.";
            continue;
        }
        if (!is_array($f) || !array_key_exists('default', $f) || !is_bool($f['default'])) {
            $errors[] = "[$key] \"default\" phải là true hoặc false.";
        }
        $rules = $f['rules'] ?? [];
        if (!is_array($rules)) {
            $errors[] = "[$key] \"rules\" phải là mảng.";
            continue;
        }
        foreach ($rules as $i => $r) {
            $where = "[$key] quy tắc #" . ($i + 1);
            if (!is_array($r)) {
                $errors[] = "$where không hợp lệ.";
                continue;
            }
            if (!isset($r['priority']) || !is_int($r['priority']) || $r['priority'] < 1) {
                $errors[] = "$where: priority phải là số nguyên >= 1.";
            }
            if (!isset($r['value']) || !is_bool($r['value'])) {
                $errors[] = "$where: value phải là true hoặc false.";
            }
            $type = $r['type'] ?? null;
            if (!in_array($type, FFM_RULE_TYPES, true)) {
                $errors[] = "$where: type phải là user_list, group hoặc percentage.";
                continue;
            }
            if ($type === 'user_list') {
                if (empty($r['users']) || !is_array($r['users']) || array_filter($r['users'], 'is_string') !== $r['users']) {
                    $errors[] = "$where: users phải là mảng chuỗi không rỗng.";
                }
            }
            if ($type === 'group' && !in_array($r['group'] ?? null, $knownGroupKeys, true)) {
                $errors[] = "$where: nhóm \"" . (is_string($r['group'] ?? null) ? $r['group'] : '?') . '" chưa được khai báo.';
            }
            if ($type !== 'user_list') {
                $p = $r['percentage'] ?? null;
                if (!is_int($p) || $p < 0 || $p > 100) {
                    $errors[] = "$where: percentage phải là số nguyên từ 0 đến 100.";
                }
            }
        }
    }
    return $errors;
}

/** Khóa nhận diện quy tắc để ghép cặp khi so sánh. */
function ffmRuleKey(array $r): string
{
    if ($r['type'] === 'user_list') {
        $u = $r['users'] ?? [];
        sort($u);
        return 'user_list:' . implode(',', $u);
    }
    if ($r['type'] === 'group') {
        return 'group:' . ($r['group'] ?? '');
    }
    return 'percentage';
}

/** Mô tả quy tắc cho người đọc. */
function ffmDescribeRule(array $r): string
{
    $v = $r['value'] ? 'BẬT' : 'TẮT';
    switch ($r['type']) {
        case 'user_list':
            return 'p' . $r['priority'] . ' danh sách ' . count($r['users']) . ' người → ' . $v;
        case 'group':
            return 'p' . $r['priority'] . ' nhóm ' . $r['group'] . ' ' . $r['percentage'] . '% → ' . $v;
        default:
            return 'p' . $r['priority'] . ' tỷ lệ ' . $r['percentage'] . '% → ' . $v;
    }
}

/**
 * So sánh hai cấu hình ($a = bản cũ, $b = bản mới).
 * @return array danh sách ['flag'=>, 'kind'=>added|removed|default|rule_added|rule_removed|rule_changed, 'text'=>]
 */
function ffmDiffConfigs(array $a, array $b): array
{
    $out = [];
    $fa = $a['flags'] ?? [];
    $fb = $b['flags'] ?? [];
    $keys = array_unique(array_merge(array_keys($fa), array_keys($fb)));
    sort($keys);
    foreach ($keys as $k) {
        $k = (string)$k;
        if (!isset($fa[$k])) {
            $out[] = ['flag' => $k, 'kind' => 'added', 'text' => 'Tính năng mới trong bản này'];
            continue;
        }
        if (!isset($fb[$k])) {
            $out[] = ['flag' => $k, 'kind' => 'removed', 'text' => 'Tính năng không còn trong bản này'];
            continue;
        }
        if ((bool)$fa[$k]['default'] !== (bool)$fb[$k]['default']) {
            $out[] = ['flag' => $k, 'kind' => 'default',
                'text' => 'Mặc định: ' . ($fa[$k]['default'] ? 'BẬT' : 'TẮT') . ' → ' . ($fb[$k]['default'] ? 'BẬT' : 'TẮT')];
        }
        $ra = [];
        foreach ($fa[$k]['rules'] ?? [] as $r) {
            $ra[ffmRuleKey($r)] = $r;
        }
        $rb = [];
        foreach ($fb[$k]['rules'] ?? [] as $r) {
            $rb[ffmRuleKey($r)] = $r;
        }
        foreach ($ra as $rk => $r) {
            if (!isset($rb[$rk])) {
                $out[] = ['flag' => $k, 'kind' => 'rule_removed', 'text' => 'Xóa quy tắc: ' . ffmDescribeRule($r)];
            } elseif (ffmDescribeRule($r) !== ffmDescribeRule($rb[$rk])) {
                $out[] = ['flag' => $k, 'kind' => 'rule_changed',
                    'text' => 'Sửa quy tắc: ' . ffmDescribeRule($r) . ' ⇒ ' . ffmDescribeRule($rb[$rk])];
            }
        }
        foreach ($rb as $rk => $r) {
            if (!isset($ra[$rk])) {
                $out[] = ['flag' => $k, 'kind' => 'rule_added', 'text' => 'Thêm quy tắc: ' . ffmDescribeRule($r)];
            }
        }
    }
    return $out;
}
