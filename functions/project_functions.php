<?php
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/version_functions.php';

function getProjectsForUser(int $userId): array
{
    return dbAll('SELECT p.*, m.role FROM projects p JOIN memberships m ON m.project_id = p.id
                  WHERE m.user_id = ? ORDER BY p.name', 'i', [$userId]);
}

function getProject(int $id): ?array
{
    return dbOne('SELECT * FROM projects WHERE id = ?', 'i', [$id]);
}

/** Tạo dự án, đặt người tạo làm quản trị (admin) và tạo sẵn 2 môi trường kèm bản phát hành v1 rỗng. */
function createProject(string $name, string $description, int $userId): int
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 100) {
        throw new InvalidArgumentException('Tên dự án phải từ 1 đến 100 ký tự.');
    }
    return dbTransaction(function () use ($name, $description, $userId) {
        dbExec('INSERT INTO projects (name, description) VALUES (?, ?)', 'ss', [$name, mb_substr(trim($description), 0, 255)]);
        $pid = dbInsertId();
        dbExec("INSERT INTO memberships (project_id, user_id, role) VALUES (?, ?, 'admin')", 'ii', [$pid, $userId]);
        foreach (['testing' => 'Thử nghiệm', 'production_sim' => 'Vận hành mô phỏng'] as $key => $label) {
            dbExec('INSERT INTO environments (project_id, env_key, name, api_key) VALUES (?, ?, ?, ?)', 'isss',
                [$pid, $key, $label, bin2hex(random_bytes(16))]);
            $envId = dbInsertId();
            $vid = createVersionRow($envId, $userId, 'Bản khởi tạo', 'draft');
            publishVersionInternal($envId, $vid, $userId, 'Khởi tạo môi trường');
        }
        return $pid;
    });
}

function getMembers(int $projectId): array
{
    return dbAll('SELECT u.id, u.username, u.full_name, m.role FROM memberships m
                  JOIN users u ON u.id = m.user_id WHERE m.project_id = ? ORDER BY u.username', 'i', [$projectId]);
}

function setMember(int $projectId, string $username, string $role): void
{
    if (!in_array($role, ['viewer', 'editor', 'admin'], true)) {
        throw new InvalidArgumentException('Quyền không hợp lệ.');
    }
    $u = dbOne('SELECT id FROM users WHERE username = ?', 's', [trim($username)]);
    if (!$u) {
        throw new InvalidArgumentException('Không tìm thấy tài khoản "' . trim($username) . '".');
    }
    // Không để dự án mất hết quản trị viên (không ai còn quản lý được thành viên).
    if ($role !== 'admin' && getProjectRole($projectId, (int)$u['id']) === 'admin') {
        $others = dbOne("SELECT COUNT(*) AS n FROM memberships WHERE project_id = ? AND role = 'admin' AND user_id <> ?", 'ii', [$projectId, $u['id']]);
        if ((int)$others['n'] === 0) {
            throw new InvalidArgumentException('Dự án phải còn ít nhất một quản trị viên. Hãy nâng người khác lên quản trị trước.');
        }
    }
    dbExec('INSERT INTO memberships (project_id, user_id, role) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE role = VALUES(role)', 'iis', [$projectId, $u['id'], $role]);
}

function getEnvironments(int $projectId): array
{
    return dbAll('SELECT e.*, v.version_no AS active_no FROM environments e
                  LEFT JOIN versions v ON v.id = e.active_version_id
                  WHERE e.project_id = ? ORDER BY e.id', 'i', [$projectId]);
}

function getEnvironment(int $id): ?array
{
    return dbOne('SELECT * FROM environments WHERE id = ?', 'i', [$id]);
}

/* ---------- Tính năng ---------- */

function getFlags(int $projectId): array
{
    return dbAll('SELECT * FROM flags WHERE project_id = ? ORDER BY flag_key', 'i', [$projectId]);
}

function createFlag(int $projectId, string $key, string $name, string $description): int
{
    $key = trim($key);
    if (!preg_match('/^[a-z][a-z0-9_]{1,59}$/', $key)) {
        throw new InvalidArgumentException('Mã tính năng gồm chữ thường, số, gạch dưới; bắt đầu bằng chữ (2-60 ký tự).');
    }
    if (trim($name) === '') {
        throw new InvalidArgumentException('Vui lòng nhập tên tính năng.');
    }
    return dbTransaction(function () use ($projectId, $key, $name, $description) {
        try {
            dbExec('INSERT INTO flags (project_id, flag_key, name, description) VALUES (?, ?, ?, ?)', 'isss',
                [$projectId, $key, trim($name), mb_substr(trim($description), 0, 255)]);
        } catch (mysqli_sql_exception $e) {
            throw new InvalidArgumentException('Mã tính năng "' . $key . '" đã tồn tại.');
        }
        $fid = dbInsertId();
        // Bản nháp đang mở (nếu có) nhận tính năng mới với mặc định TẮT
        dbExec('INSERT INTO version_flags (version_id, flag_id, default_enabled)
                SELECT v.id, ?, 0 FROM versions v JOIN environments e ON e.id = v.environment_id
                WHERE e.project_id = ? AND v.status = \'draft\'', 'ii', [$fid, $projectId]);
        return $fid;
    });
}

/* ---------- Nhóm người dùng ---------- */

function getGroups(int $projectId): array
{
    return dbAll('SELECT g.*, (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS member_count
                  FROM user_groups g WHERE g.project_id = ? ORDER BY g.group_key', 'i', [$projectId]);
}

function createGroup(int $projectId, string $key, string $name): int
{
    $key = trim($key);
    if (!preg_match('/^[a-z][a-z0-9_]{1,39}$/', $key) || trim($name) === '') {
        throw new InvalidArgumentException('Mã nhóm gồm chữ thường/số/gạch dưới (2-40 ký tự) và phải có tên.');
    }
    try {
        dbExec('INSERT INTO user_groups (project_id, group_key, name) VALUES (?, ?, ?)', 'iss', [$projectId, $key, trim($name)]);
    } catch (mysqli_sql_exception $e) {
        throw new InvalidArgumentException('Mã nhóm "' . $key . '" đã tồn tại.');
    }
    return dbInsertId();
}

/**
 * Tách và kiểm tra danh sách mã người dùng cuối từ chuỗi (cách nhau bởi dấu cách, phẩy, xuống dòng)
 * hoặc từ mảng (ô chọn nhiều người). Trả về mảng mã không trùng.
 */
function parseEndUserIds($raw): array
{
    $parts = is_array($raw) ? array_map('strval', $raw) : preg_split('/[\s,]+/', (string)$raw);
    $ids = array_values(array_unique(array_filter(array_map('trim', $parts), 'strlen')));
    if (count($ids) > 5000) {
        throw new InvalidArgumentException('Mỗi lần chỉ xử lý tối đa 5.000 mã người dùng.');
    }
    foreach ($ids as $id) {
        if (strlen($id) > 60 || !preg_match('/^[A-Za-z0-9_.@-]+$/', $id)) {
            throw new InvalidArgumentException('Mã người dùng không hợp lệ: ' . mb_substr($id, 0, 30));
        }
    }
    return $ids;
}

/** Bảo đảm các mã đã có trong danh sách người dùng giả của dự án; trả về số mã mới được tạo. */
function ensureEndUsers(int $projectId, array $ids): int
{
    $created = 0;
    foreach ($ids as $id) {
        $created += dbExec('INSERT IGNORE INTO end_users (project_id, end_user_id) VALUES (?, ?)', 'is', [$projectId, $id]);
    }
    return $created;
}

function requireGroupOfProject(int $projectId, int $groupId): array
{
    $g = dbOne('SELECT * FROM user_groups WHERE id = ? AND project_id = ?', 'ii', [$groupId, $projectId]);
    if (!$g) {
        throw new InvalidArgumentException('Nhóm không thuộc dự án này.');
    }
    return $g;
}

/** Thay toàn bộ thành viên nhóm bằng danh sách mã (chuỗi hoặc mảng). Mã chưa có được thêm vào người dùng giả. */
function setGroupMembers(int $projectId, int $groupId, $raw): int
{
    requireGroupOfProject($projectId, $groupId);
    $ids = parseEndUserIds($raw);
    dbTransaction(function () use ($projectId, $groupId, $ids) {
        ensureEndUsers($projectId, $ids);
        dbExec('DELETE FROM group_members WHERE group_id = ?', 'i', [$groupId]);
        foreach ($ids as $id) {
            dbExec('INSERT INTO group_members (group_id, end_user_id) VALUES (?, ?)', 'is', [$groupId, $id]);
        }
    });
    return count($ids);
}

/**
 * Thêm người vào nhóm mà KHÔNG xóa người đang có. Mã chưa có trong dự án sẽ được tạo mới.
 * @return array ['added' => số người mới vào nhóm, 'already' => đã ở trong nhóm, 'created' => mã mới tạo]
 */
function addGroupMembers(int $projectId, int $groupId, $raw): array
{
    requireGroupOfProject($projectId, $groupId);
    $ids = parseEndUserIds($raw);
    if (!$ids) {
        throw new InvalidArgumentException('Chưa chọn hoặc nhập mã người dùng nào.');
    }
    return dbTransaction(function () use ($projectId, $groupId, $ids) {
        $created = ensureEndUsers($projectId, $ids);
        $added = 0;
        foreach ($ids as $id) {
            $added += dbExec('INSERT IGNORE INTO group_members (group_id, end_user_id) VALUES (?, ?)', 'is', [$groupId, $id]);
        }
        return ['added' => $added, 'already' => count($ids) - $added, 'created' => $created];
    });
}

/** Danh sách group_key mà một người dùng cuối thuộc về trong dự án. */
function getUserGroupKeys(int $projectId, string $endUserId): array
{
    $rows = dbAll('SELECT g.group_key FROM group_members gm JOIN user_groups g ON g.id = gm.group_id
                   WHERE g.project_id = ? AND gm.end_user_id = ?', 'is', [$projectId, $endUserId]);
    return array_column($rows, 'group_key');
}
