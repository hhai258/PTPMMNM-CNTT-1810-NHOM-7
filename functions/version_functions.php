<?php
/**
 * Phiên bản cấu hình của từng môi trường.
 * - Bản nháp (draft) được sửa; bản đã phát hành (published) chỉ đọc.
 * - Muốn sửa bản đã phát hành: tạo bản nháp mới từ nó (createDraft).
 * - Khôi phục bản cũ = tạo bản phát hành mới là bản sao của bản cũ, lịch sử giữ nguyên.
 */
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/config_tools.php';

/* ---------- Truy vấn ---------- */

function getVersion(int $id): ?array
{
    return dbOne('SELECT v.*, e.project_id, e.env_key FROM versions v
                  JOIN environments e ON e.id = v.environment_id WHERE v.id = ?', 'i', [$id]);
}

function getVersions(int $envId): array
{
    return dbAll('SELECT v.*, u.username FROM versions v JOIN users u ON u.id = v.created_by
                  WHERE v.environment_id = ? ORDER BY v.version_no DESC', 'i', [$envId]);
}

function getDraft(int $envId): ?array
{
    return dbOne("SELECT * FROM versions WHERE environment_id = ? AND status = 'draft' LIMIT 1", 'i', [$envId]);
}

/** Đọc một version thành mảng cấu hình chuẩn (xem evaluator.php). */
function loadVersionConfig(int $versionId): array
{
    $flags = dbAll('SELECT f.id, f.flag_key, vf.default_enabled FROM version_flags vf
                    JOIN flags f ON f.id = vf.flag_id WHERE vf.version_id = ? ORDER BY f.flag_key', 'i', [$versionId]);
    $rules = dbAll('SELECT * FROM rules WHERE version_id = ? ORDER BY priority, id', 'i', [$versionId]);
    $cfg = ['format' => FFM_FORMAT, 'flags' => []];
    $byId = [];
    foreach ($flags as $f) {
        $cfg['flags'][$f['flag_key']] = ['default' => (bool)$f['default_enabled'], 'rules' => []];
        $byId[$f['id']] = $f['flag_key'];
    }
    foreach ($rules as $r) {
        if (!isset($byId[$r['flag_id']])) {
            continue;
        }
        $rule = ['priority' => (int)$r['priority'], 'type' => $r['rule_type'], 'value' => (bool)$r['result_enabled']];
        if ($r['rule_type'] === 'user_list') {
            $rule['users'] = array_values(array_filter(array_map('trim', explode(',', $r['target'])), 'strlen'));
        } elseif ($r['rule_type'] === 'group') {
            $rule['group'] = $r['target'];
            $rule['percentage'] = (int)$r['percentage'];
        } else {
            $rule['percentage'] = (int)$r['percentage'];
        }
        $cfg['flags'][$byId[$r['flag_id']]]['rules'][] = $rule;
    }
    return $cfg;
}

/** Các dòng quy tắc (kèm id) của một tính năng trong một version, để hiển thị và xóa. */
function getRuleRows(int $versionId, int $flagId): array
{
    return dbAll('SELECT * FROM rules WHERE version_id = ? AND flag_id = ? ORDER BY priority, id', 'ii', [$versionId, $flagId]);
}

function getVersionFlagRows(int $versionId): array
{
    return dbAll('SELECT f.id, f.flag_key, f.name, vf.default_enabled FROM version_flags vf
                  JOIN flags f ON f.id = vf.flag_id WHERE vf.version_id = ? ORDER BY f.flag_key', 'i', [$versionId]);
}

/* ---------- Lịch sử ---------- */

function logChange(int $envId, ?int $versionId, int $userId, string $action, string $reason = '', $detail = null): void
{
    dbExec('INSERT INTO change_logs (environment_id, version_id, user_id, action, reason, detail) VALUES (?, ?, ?, ?, ?, ?)',
        'iiisss', [$envId, $versionId, $userId, $action, mb_substr($reason, 0, 255),
            $detail === null ? null : (is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE))]);
}

function getChangeLogs(int $envId, ?int $versionId = null): array
{
    $sql = 'SELECT c.*, u.username, v.version_no FROM change_logs c
            JOIN users u ON u.id = c.user_id LEFT JOIN versions v ON v.id = c.version_id
            WHERE c.environment_id = ?';
    $types = 'i';
    $params = [$envId];
    if ($versionId !== null) {
        $sql .= ' AND c.version_id = ?';
        $types .= 'i';
        $params[] = $versionId;
    }
    return dbAll($sql . ' ORDER BY c.id DESC', $types, $params);
}

/* ---------- Tạo / sao chép version ---------- */

/** Tạo dòng version mới với số thứ tự kế tiếp. Phải gọi trong giao dịch. */
function createVersionRow(int $envId, int $userId, string $note, string $status = 'draft'): int
{
    dbOne('SELECT id FROM environments WHERE id = ? FOR UPDATE', 'i', [$envId]); // khóa để tránh trùng số
    $max = dbOne('SELECT COALESCE(MAX(version_no), 0) AS m FROM versions WHERE environment_id = ?', 'i', [$envId]);
    dbExec('INSERT INTO versions (environment_id, version_no, status, note, created_by) VALUES (?, ?, ?, ?, ?)',
        'iissi', [$envId, (int)$max['m'] + 1, $status, mb_substr($note, 0, 255), $userId]);
    return dbInsertId();
}

function copyVersionContents(int $fromId, int $toId): void
{
    dbExec('INSERT INTO version_flags (version_id, flag_id, default_enabled)
            SELECT ?, flag_id, default_enabled FROM version_flags WHERE version_id = ?', 'ii', [$toId, $fromId]);
    dbExec('INSERT INTO rules (version_id, flag_id, priority, rule_type, target, percentage, result_enabled)
            SELECT ?, flag_id, priority, rule_type, target, percentage, result_enabled FROM rules WHERE version_id = ?',
        'ii', [$toId, $fromId]);
}

/** Bổ sung các tính năng của dự án mà version chưa có (mặc định TẮT). */
function fillMissingFlags(int $versionId, int $projectId): void
{
    dbExec('INSERT INTO version_flags (version_id, flag_id, default_enabled)
            SELECT ?, f.id, 0 FROM flags f WHERE f.project_id = ?
            AND f.id NOT IN (SELECT flag_id FROM version_flags WHERE version_id = ?)', 'iii', [$versionId, $projectId, $versionId]);
}

/** Tạo bản nháp từ một version có sẵn (hoặc rỗng nếu $sourceId = null). Mỗi môi trường chỉ có một bản nháp. */
function createDraft(int $envId, ?int $sourceId, int $userId, string $note = ''): int
{
    $env = dbOne('SELECT * FROM environments WHERE id = ?', 'i', [$envId]);
    if (!$env) {
        throw new InvalidArgumentException('Không tìm thấy môi trường.');
    }
    return dbTransaction(function () use ($env, $envId, $sourceId, $userId, $note) {
        if (getDraft($envId)) {
            throw new RuntimeException('Môi trường đã có bản nháp. Hãy phát hành hoặc hủy bản nháp trước.');
        }
        $src = null;
        if ($sourceId !== null) {
            $src = getVersion($sourceId);
            if (!$src || (int)$src['environment_id'] !== $envId) {
                throw new InvalidArgumentException('Bản nguồn không thuộc môi trường này.');
            }
        }
        $vid = createVersionRow($envId, $userId, $note !== '' ? $note : ($src ? 'Sao từ v' . $src['version_no'] : 'Bản nháp mới'));
        if ($src) {
            copyVersionContents($sourceId, $vid);
        }
        fillMissingFlags($vid, (int)$env['project_id']);
        logChange($envId, $vid, $userId, 'create_draft', '', $src ? ['from_version' => (int)$src['version_no']] : null);
        return $vid;
    });
}

function discardDraft(int $versionId, int $userId): void
{
    $v = requireDraft($versionId);
    dbTransaction(function () use ($v, $userId) {
        logChange((int)$v['environment_id'], null, $userId, 'discard_draft', '', ['version_no' => (int)$v['version_no']]);
        dbExec('DELETE FROM versions WHERE id = ?', 'i', [$v['id']]);
    });
}

/** Chỉ cho phép sửa bản nháp; bản đã phát hành bị từ chối ở tầng xử lý, không chỉ ở giao diện. */
function requireDraft(int $versionId): array
{
    $v = getVersion($versionId);
    if (!$v) {
        throw new InvalidArgumentException('Không tìm thấy phiên bản.');
    }
    if ($v['status'] !== 'draft') {
        throw new RuntimeException('Phiên bản v' . $v['version_no'] . ' đã phát hành, không được sửa. Hãy tạo bản nháp mới từ bản này.');
    }
    return $v;
}

/* ---------- Sửa bản nháp ---------- */

function setFlagDefault(int $versionId, int $flagId, bool $enabled, string $reason, int $userId): void
{
    $v = requireDraft($versionId);
    if (trim($reason) === '') {
        throw new InvalidArgumentException('Vui lòng nhập lý do bật/tắt.');
    }
    $f = dbOne('SELECT f.flag_key FROM flags f JOIN version_flags vf ON vf.flag_id = f.id
                WHERE f.id = ? AND vf.version_id = ? AND f.project_id = ?', 'iii', [$flagId, $versionId, $v['project_id']]);
    if (!$f) {
        throw new InvalidArgumentException('Tính năng không thuộc bản nháp này.');
    }
    dbExec('UPDATE version_flags SET default_enabled = ? WHERE version_id = ? AND flag_id = ?', 'iii', [(int)$enabled, $versionId, $flagId]);
    logChange((int)$v['environment_id'], $versionId, $userId, $enabled ? 'flag_on' : 'flag_off', trim($reason), ['flag' => $f['flag_key']]);
}

/** Chuyển dữ liệu form thành quy tắc chuẩn rồi kiểm tra bằng đúng bộ kiểm tra của nhập/xuất. */
function buildRuleFromInput(array $in): array
{
    $type = $in['rule_type'] ?? '';
    $rule = ['priority' => filter_var($in['priority'] ?? null, FILTER_VALIDATE_INT),
             'type' => $type, 'value' => ($in['value'] ?? '1') === '1'];
    if ($type === 'user_list') {
        $rule['users'] = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,]+/', (string)($in['users'] ?? ''))), 'strlen')));
    } elseif ($type === 'group') {
        $rule['group'] = trim((string)($in['group'] ?? ''));
    }
    if ($type !== 'user_list') {
        $rule['percentage'] = filter_var($in['percentage'] ?? null, FILTER_VALIDATE_INT);
    }
    return $rule;
}

function addRule(int $versionId, int $flagId, array $input, int $userId): int
{
    $v = requireDraft($versionId);
    $f = dbOne('SELECT f.flag_key FROM flags f JOIN version_flags vf ON vf.flag_id = f.id
                WHERE f.id = ? AND vf.version_id = ?', 'ii', [$flagId, $versionId]);
    if (!$f) {
        throw new InvalidArgumentException('Tính năng không thuộc bản nháp này.');
    }
    $rule = buildRuleFromInput($input);
    $errors = ffmValidateConfig(['format' => FFM_FORMAT, 'flags' => [$f['flag_key'] => ['default' => false, 'rules' => [$rule]]]],
        [$f['flag_key']], array_column(dbAll('SELECT group_key FROM user_groups WHERE project_id = ?', 'i', [$v['project_id']]), 'group_key'));
    if ($errors) {
        throw new InvalidArgumentException(implode(' ', $errors));
    }
    $target = $rule['type'] === 'user_list' ? implode(',', $rule['users']) : ($rule['type'] === 'group' ? $rule['group'] : '');
    $pct = $rule['percentage'] ?? 100;
    return dbTransaction(function () use ($v, $versionId, $flagId, $f, $rule, $target, $pct, $input, $userId) {
        // Chèn: số ưu tiên đã có thì các quy tắc từ số đó trở xuống dồn xuống một bậc, quy tắc mới được xét trước.
        $shifted = dbExec('UPDATE rules SET priority = priority + 1 WHERE version_id = ? AND flag_id = ? AND priority >= ?',
            'iii', [$versionId, $flagId, $rule['priority']]);
        dbExec('INSERT INTO rules (version_id, flag_id, priority, rule_type, target, percentage, result_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iiissii', [$versionId, $flagId, $rule['priority'], $rule['type'], $target, $pct, (int)$rule['value']]);
        $rid = dbInsertId();
        logChange((int)$v['environment_id'], $versionId, $userId, 'rule_add', trim((string)($input['reason'] ?? '')),
            ['flag' => $f['flag_key'], 'shifted' => $shifted,
             'rule' => ffmDescribeRule($rule + ['users' => $rule['users'] ?? [], 'group' => $rule['group'] ?? '', 'percentage' => $pct])]);
        return $rid;
    });
}

function deleteRule(int $versionId, int $ruleId, int $userId): void
{
    $v = requireDraft($versionId);
    $r = dbOne('SELECT r.*, f.flag_key FROM rules r JOIN flags f ON f.id = r.flag_id WHERE r.id = ? AND r.version_id = ?', 'ii', [$ruleId, $versionId]);
    if (!$r) {
        throw new InvalidArgumentException('Quy tắc không thuộc bản nháp này.');
    }
    dbExec('DELETE FROM rules WHERE id = ?', 'i', [$ruleId]);
    logChange((int)$v['environment_id'], $versionId, $userId, 'rule_delete', '', ['flag' => $r['flag_key'], 'type' => $r['rule_type'], 'priority' => (int)$r['priority']]);
}

/* ---------- Phát hành và khôi phục ---------- */

function knownGroupKeys(int $projectId): array
{
    return array_column(dbAll('SELECT group_key FROM user_groups WHERE project_id = ?', 'i', [$projectId]), 'group_key');
}

/** Đặt version thành bản đang phát hành của môi trường. Gọi trong giao dịch. */
function publishVersionInternal(int $envId, int $versionId, int $userId, string $reason, string $action = 'publish'): void
{
    dbExec("UPDATE versions SET status = 'published', published_at = NOW() WHERE id = ?", 'i', [$versionId]);
    dbExec('UPDATE environments SET active_version_id = ? WHERE id = ?', 'ii', [$versionId, $envId]);
    logChange($envId, $versionId, $userId, $action, $reason);
}

function publishVersion(int $versionId, string $reason, int $userId): void
{
    $v = requireDraft($versionId);
    if (trim($reason) === '') {
        throw new InvalidArgumentException('Vui lòng nhập lý do phát hành.');
    }
    $cfg = loadVersionConfig($versionId);
    $errors = ffmValidateConfig($cfg, array_keys($cfg['flags']), knownGroupKeys((int)$v['project_id']));
    if ($errors) {
        throw new InvalidArgumentException('Cấu hình chưa hợp lệ: ' . implode(' ', $errors));
    }
    dbTransaction(function () use ($v, $versionId, $reason, $userId) {
        dbOne('SELECT id FROM environments WHERE id = ? FOR UPDATE', 'i', [$v['environment_id']]);
        requireDraft($versionId); // kiểm tra lại sau khi khóa: tránh phát hành hai lần
        publishVersionInternal((int)$v['environment_id'], $versionId, $userId, trim($reason));
    });
}

/**
 * Khôi phục: sao nội dung của một bản đã phát hành thành bản phát hành MỚI.
 * Các bản cũ và lịch sử không bị xóa hay sửa.
 */
function restoreVersion(int $sourceId, string $reason, int $userId): int
{
    $src = getVersion($sourceId);
    if (!$src || $src['status'] !== 'published') {
        throw new InvalidArgumentException('Chỉ khôi phục từ bản đã phát hành.');
    }
    if (trim($reason) === '') {
        throw new InvalidArgumentException('Vui lòng nhập lý do khôi phục.');
    }
    return dbTransaction(function () use ($src, $reason, $userId) {
        $envId = (int)$src['environment_id'];
        $vid = createVersionRow($envId, $userId, 'Khôi phục từ v' . $src['version_no'], 'draft');
        copyVersionContents((int)$src['id'], $vid);
        publishVersionInternal($envId, $vid, $userId, trim($reason), 'restore');
        return $vid;
    });
}

/* ---------- So sánh, nhập, xuất ---------- */

function compareVersions(int $aId, int $bId): array
{
    return ffmDiffConfigs(loadVersionConfig($aId), loadVersionConfig($bId));
}

function exportVersionJson(int $versionId): string
{
    return json_encode(loadVersionConfig($versionId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/** Nhập JSON vào một bản nháp mới. Kiểm tra toàn bộ trước, có lỗi thì không ghi gì. */
function importConfigAsDraft(int $envId, string $json, int $userId, string $reason): int
{
    $env = dbOne('SELECT * FROM environments WHERE id = ?', 'i', [$envId]);
    if (!$env) {
        throw new InvalidArgumentException('Không tìm thấy môi trường.');
    }
    if (strlen($json) > 1048576) {
        throw new InvalidArgumentException('Tệp quá lớn (tối đa 1 MB).');
    }
    $cfg = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('Tệp không phải JSON hợp lệ: ' . json_last_error_msg());
    }
    $flagRows = dbAll('SELECT id, flag_key FROM flags WHERE project_id = ?', 'i', [$env['project_id']]);
    $flagIds = array_column($flagRows, 'id', 'flag_key');
    $errors = ffmValidateConfig($cfg, array_keys($flagIds), knownGroupKeys((int)$env['project_id']));
    if ($errors) {
        throw new InvalidArgumentException(implode("\n", $errors));
    }
    return dbTransaction(function () use ($envId, $env, $cfg, $flagIds, $userId, $reason) {
        $vid = createDraft($envId, null, $userId, 'Nhập từ tệp JSON');
        foreach ($cfg['flags'] as $key => $f) {
            $fid = (int)$flagIds[(string)$key];
            dbExec('UPDATE version_flags SET default_enabled = ? WHERE version_id = ? AND flag_id = ?', 'iii', [(int)$f['default'], $vid, $fid]);
            foreach ($f['rules'] ?? [] as $r) {
                $target = $r['type'] === 'user_list' ? implode(',', $r['users']) : ($r['type'] === 'group' ? $r['group'] : '');
                dbExec('INSERT INTO rules (version_id, flag_id, priority, rule_type, target, percentage, result_enabled) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'iiissii', [$vid, $fid, $r['priority'], $r['type'], $target, $r['percentage'] ?? 100, (int)$r['value']]);
            }
        }
        logChange($envId, $vid, $userId, 'import', $reason, ['flags' => count($cfg['flags'])]);
        return $vid;
    });
}
