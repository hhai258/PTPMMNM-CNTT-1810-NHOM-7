<?php
/**
 * Kiểm thử tích hợp (CSDL + HTTP). CHỈ chạy trên CSDL có tên kết thúc bằng "_test"
 * vì bước chuẩn bị xóa và tạo lại toàn bộ bảng.
 *   mysql -uroot -e "CREATE DATABASE ffm_test CHARACTER SET utf8mb4; GRANT ALL ON ffm_test.* TO 'ffm'@'localhost'"
 *   FFM_DB_NAME=ffm_test FFM_DB_PASS=... php tests/integration_tests.php
 */
require_once __DIR__ . '/lib.php';
if (getenv('FFM_DB_NAME') === false || getenv('FFM_DB_NAME') === '') {
    putenv('FFM_DB_NAME=ffm_test');
}
require_once __DIR__ . '/../functions/project_functions.php';
require_once __DIR__ . '/../functions/decision_functions.php';
require_once __DIR__ . '/../functions/overview_functions.php';

$dbName = ffmConfig()['db']['name'];
if (!preg_match('/_test$/', $dbName)) {
    fwrite(STDERR, "Từ chối chạy: CSDL \"$dbName\" không kết thúc bằng _test.\n");
    exit(2);
}

/* ---------- Chuẩn bị ---------- */
$conn = getDbConnection();
$conn->multi_query(file_get_contents(__DIR__ . '/../database/schema.sql'));
do { } while ($conn->more_results() && $conn->next_result());

function mkUser(string $name): int
{
    dbExec('INSERT INTO users (username, password_hash, full_name) VALUES (?, ?, ?)', 'sss', [$name, password_hash($name . '_pw', PASSWORD_DEFAULT), $name]);
    return dbInsertId();
}
$admin = mkUser('admin');
$viewer = mkUser('viewer');
$other = mkUser('other');
$P = createProject('Dự án kiểm thử', '', $admin);
setMember($P, 'viewer', 'viewer');
$dev = mkUser('dev');
setMember($P, 'dev', 'editor');
$P2 = createProject('Dự án khác', '', $other);
foreach (['alpha' => 'Alpha', 'beta_f' => 'Beta F', 'gamma' => 'Gamma'] as $k => $n) {
    createFlag($P, $k, $n, '');
}
$fid = array_column(getFlags($P), 'id', 'flag_key');
createGroup($P, 'beta', 'Beta');
for ($i = 1; $i <= 200; $i++) {
    dbExec('INSERT INTO end_users (project_id, end_user_id) VALUES (?, ?)', 'is', [$P, sprintf('user%04d', $i)]);
}
$envs = array_column(getEnvironments($P), null, 'env_key');
$test = $envs['testing'];
$prod = $envs['production_sim'];
$testId = (int)$test['id'];
$users = array_map(fn($i) => sprintf('user%04d', $i), range(1, 100));
$snapshot = function (int $envId) use ($users): array {
    $env = getEnvironment($envId);
    $out = [];
    foreach ($users as $u) {
        $out[$u] = array_map(fn($r) => $r['enabled'], evaluateForEnvironment($env, $u)['results']);
    }
    return $out;
};

/* ---------- A. Bản đã phát hành không được sửa ---------- */
section('A. Phiên bản: bản đã phát hành chỉ đọc, bản nháp mới để sửa');
$v1 = (int)$test['active_version_id'];
throwsA(RuntimeException::class, fn() => setFlagDefault($v1, (int)$fid['alpha'], true, 'thử', $admin), 'Sửa mặc định trên bản đã phát hành bị từ chối');
throwsA(RuntimeException::class, fn() => addRule($v1, (int)$fid['alpha'], ['rule_type' => 'percentage', 'percentage' => 5, 'priority' => 1, 'value' => '1'], $admin), 'Thêm quy tắc vào bản đã phát hành bị từ chối');
$d2 = createDraft($testId, $v1, $admin, 'Bản thử nghiệm 1');
check($d2 > 0, 'Tạo bản nháp từ bản đã phát hành');
throwsA(RuntimeException::class, fn() => createDraft($testId, $v1, $admin), 'Chỉ cho một bản nháp mỗi môi trường');
throwsA(InvalidArgumentException::class, fn() => setFlagDefault($d2, (int)$fid['alpha'], true, '  ', $admin), 'Bật/tắt bắt buộc có lý do');
setFlagDefault($d2, (int)$fid['alpha'], true, 'Bật alpha cho mọi người', $admin);
addRule($d2, (int)$fid['beta_f'], ['rule_type' => 'percentage', 'percentage' => 50, 'priority' => 2, 'value' => '1'], $admin);
addRule($d2, (int)$fid['beta_f'], ['rule_type' => 'user_list', 'users' => 'user0001, user0002', 'priority' => 1, 'value' => '0'], $admin);
throwsA(InvalidArgumentException::class, fn() => addRule($d2, (int)$fid['beta_f'], ['rule_type' => 'percentage', 'percentage' => 101, 'priority' => 3, 'value' => '1'], $admin), 'Tỷ lệ 101% bị từ chối khi thêm quy tắc');
throwsA(InvalidArgumentException::class, fn() => addRule($d2, (int)$fid['beta_f'], ['rule_type' => 'group', 'group' => 'ma_nhom_khong_co', 'percentage' => 10, 'priority' => 3, 'value' => '1'], $admin), 'Nhóm không tồn tại bị từ chối');
// Chèn quy tắc: số ưu tiên đã có thì các quy tắc từ số đó trở xuống dồn xuống một bậc (làm trên tính năng alpha rồi dọn lại)
$r1 = addRule($d2, (int)$fid['alpha'], ['rule_type' => 'percentage', 'percentage' => 10, 'priority' => 1, 'value' => '1'], $admin);
$r2 = addRule($d2, (int)$fid['alpha'], ['rule_type' => 'percentage', 'percentage' => 20, 'priority' => 2, 'value' => '1'], $admin);
$r3 = addRule($d2, (int)$fid['alpha'], ['rule_type' => 'user_list', 'users' => 'user0001', 'priority' => 1, 'value' => '0'], $admin);
$ins = loadVersionConfig($d2)['flags']['alpha']['rules'];
same([1, 2, 3], array_column($ins, 'priority'), 'Chèn ở ưu tiên 1: quy tắc cũ dồn xuống p2, p3, không trùng số');
same(['user_list', 'percentage', 'percentage'], array_column($ins, 'type'), 'Quy tắc vừa chèn đứng đầu, thứ tự tương đối của các quy tắc cũ được giữ nguyên');
same([10, 20], array_column(array_slice($ins, 1), 'percentage'), 'Hai quy tắc cũ giữ đúng nội dung sau khi dồn');
foreach ([$r1, $r2, $r3] as $rid) { deleteRule($d2, $rid, $admin); }
same([], loadVersionConfig($d2)['flags']['alpha']['rules'], 'Dọn xong, alpha về lại không có quy tắc');
throwsA(InvalidArgumentException::class, fn() => publishVersion($d2, '', $admin), 'Phát hành bắt buộc có lý do');
publishVersion($d2, 'Phát hành cấu hình thử nghiệm 1', $admin);
throwsA(RuntimeException::class, fn() => publishVersion($d2, 'lần hai', $admin), 'Phát hành lại bản đã phát hành bị từ chối');
same($d2, (int)getEnvironment($testId)['active_version_id'], 'Môi trường trỏ tới bản vừa phát hành');
same(null, getDraft($testId), 'Sau khi phát hành không còn bản nháp');

/* ---------- B. Tình huống 1: tạo cấu hình → kiểm tra → phát hành → gọi API ---------- */
section('B. Tình huống 1: gọi API từ ứng dụng thử nghiệm');
$env = getEnvironment($testId);
$r = evaluateForEnvironment($env, 'user0001', 'beta_f');
same(false, $r['results']['beta_f']['enabled'], 'Người trong danh sách (p1, TẮT) thắng tỷ lệ 50% (p2)');
same(true, evaluateForEnvironment($env, 'user0077', 'alpha')['results']['alpha']['enabled'], 'Tính năng bật mặc định trả BẬT');
throwsA(OutOfBoundsException::class, fn() => evaluateForEnvironment($env, 'u', 'khong_co'), 'Tính năng không tồn tại báo lỗi riêng');
$prodSnap = $snapshot((int)$prod['id']);
$anyOn = false;
foreach ($prodSnap as $perUser) {
    $anyOn = $anyOn || in_array(true, $perUser, true);
}
check(!$anyOn, 'Môi trường vận hành mô phỏng độc lập: chưa bật gì');
check(count(getRecentDecisions($testId, 5)) > 0, 'Quyết định của API được ghi vào decision_logs');

/* ---------- C. Tình huống 2: đổi tỷ lệ → so sánh → khôi phục ---------- */
section('C. Tình huống 2: đổi tỷ lệ, so sánh, khôi phục');
$snapV2 = $snapshot($testId);
$d3 = createDraft($testId, $d2, $admin, 'Đổi tỷ lệ');
foreach (getRuleRows($d3, (int)$fid['beta_f']) as $row) {
    if ($row['rule_type'] === 'percentage') {
        deleteRule($d3, (int)$row['id'], $admin);
    }
}
addRule($d3, (int)$fid['beta_f'], ['rule_type' => 'percentage', 'percentage' => 100, 'priority' => 2, 'value' => '1'], $admin);
setFlagDefault($d3, (int)$fid['alpha'], false, 'Tắt alpha vì lỗi', $admin);
$diff = compareVersions($d2, $d3);
same(['default', 'rule_changed'], array_column($diff, 'kind'), 'So sánh v2 → v3 chỉ ra đổi mặc định và đổi tỷ lệ');
publishVersion($d3, 'Mở rộng beta_f 100%, tắt alpha', $admin);
$snapV3 = $snapshot($testId);
check($snapV3 !== $snapV2, 'Cấu hình mới cho kết quả khác bản trước');
$logsBefore = count(getChangeLogs($testId));
$versionsBefore = count(getVersions($testId));
$restored = restoreVersion($d2, 'Lỗi ở v3, quay lại v2', $admin);
$snapRestored = $snapshot($testId);
same($snapV2, $snapRestored, 'Khôi phục v2: 100 người dùng nhận đúng kết quả như trước');
same($versionsBefore + 1, count(getVersions($testId)), 'Khôi phục tạo bản mới, không xóa bản nào');
check(count(getChangeLogs($testId)) > $logsBefore, 'Lịch sử có thêm bản ghi khôi phục');
$actions = array_column(getChangeLogs($testId), 'action');
check(in_array('publish', $actions, true) && in_array('restore', $actions, true), 'Lịch sử phát hành cũ vẫn còn nguyên');
same('published', getVersion($d3)['status'], 'Bản v3 vẫn tồn tại ở trạng thái đã phát hành');
same(getRuleRows($d3, (int)$fid['beta_f']) !== [], true, 'Quy tắc của v3 không bị đổi bởi khôi phục');
throwsA(InvalidArgumentException::class, fn() => restoreVersion(createDraft($testId, null, $admin), 'x', $admin), 'Không khôi phục từ bản nháp');
discardDraft((int)getDraft($testId)['id'], $admin);
same(null, getDraft($testId), 'Hủy bản nháp');

/* ---------- D. Nhập / xuất ---------- */
section('D. Nhập, xuất cấu hình');
$json = exportVersionJson($restored);
$before = count(getVersions($testId));
throwsA(InvalidArgumentException::class, fn() => importConfigAsDraft($testId, '{không phải json', $admin, ''), 'JSON hỏng bị từ chối');
$badJson = json_encode(['format' => FFM_FORMAT, 'flags' => ['alpha' => ['default' => true, 'rules' => [['priority' => 1, 'type' => 'percentage', 'percentage' => 999, 'value' => true]]], 'ghost' => ['default' => true]]]);
try {
    importConfigAsDraft($testId, $badJson, $admin, '');
    check(false, 'Nhập dữ liệu sai phải báo lỗi');
} catch (InvalidArgumentException $e) {
    check(substr_count($e->getMessage(), "\n") >= 1, 'Báo đủ nhiều lỗi cùng lúc');
}
same($before, count(getVersions($testId)), 'Nhập lỗi không tạo bản nháp nào');
$d4 = importConfigAsDraft($testId, $json, $admin, 'Nhập lại bản xuất');
same(loadVersionConfig($restored), loadVersionConfig($d4), 'Xuất rồi nhập lại cho cấu hình giống hệt');
same([], compareVersions($restored, $d4), 'So sánh bản gốc và bản nhập: không khác biệt');
discardDraft($d4, $admin);

/* ---------- E. HTTP: quyền, CSRF, truy cập chéo dự án ---------- */
section('E. HTTP: quyền xem/sửa, CSRF, dự án khác');
$port = 8099;
$cmd = ['php', '-S', "127.0.0.1:$port", '-t', dirname(__DIR__)];
$env = array_merge(getenv(), ['FFM_DB_NAME' => $dbName]);
$proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
register_shutdown_function(function () use ($proc) { @proc_terminate($proc); });
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}
$base = "http://127.0.0.1:$port";

function http(string $method, string $url, array $post = [], array &$jar = []): array
{
    $cookie = implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($jar), $jar));
    $o = ['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10, 'header' => "Cookie: $cookie\r\n"]];
    if ($method === 'POST') {
        $o['http']['content'] = http_build_query($post);
        $o['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $body = file_get_contents($url, false, stream_context_create($o));
    $status = 0;
    $loc = null;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $status = (int)$m[1];
        if (stripos($h, 'Set-Cookie:') === 0 && preg_match('/Set-Cookie:\s*([^=]+)=([^;]*)/i', $h, $m)) $jar[$m[1]] = $m[2];
        if (stripos($h, 'Location:') === 0) $loc = trim(substr($h, 9));
    }
    return ['status' => $status, 'body' => (string)$body, 'location' => $loc];
}
function login(string $base, string $user): array
{
    $jar = [];
    $page = http('GET', "$base/index.php", [], $jar);
    preg_match('/name="csrf" value="([^"]+)"/', $page['body'], $m);
    $r = http('POST', "$base/handle/login_process.php", ['csrf' => $m[1], 'username' => $user, 'password' => $user . '_pw'], $jar);
    $home = http('GET', "$base/views/projects.php", [], $jar);
    preg_match('/name="csrf" value="([^"]+)"/', $home['body'], $m2);
    return [$jar, $m2[1] ?? '', $r['status'], $home['status']];
}

[$vj, $vcsrf, $st1, $st2] = login($base, 'viewer');
check($st1 === 302 && $st2 === 200, 'Đăng nhập tài khoản chỉ xem thành công');
same(200, http('GET', "$base/views/project.php?id=$P", [], $vj)['status'], 'Tài khoản chỉ xem vào được trang dự án');
$page = http('GET', "$base/views/project.php?id=$P", [], $vj)['body'];
check(strpos($page, 'create_flag') === false && strpos($page, $test['api_key']) === false, 'Giao diện chỉ xem ẩn form sửa và API key');
$draftsBefore = (int)dbOne("SELECT COUNT(*) c FROM versions WHERE status='draft'")['c'];
$r = http('POST', "$base/handle/version_process.php", ['csrf' => $vcsrf, 'action' => 'create_draft', 'environment_id' => $testId], $vj);
same(403, $r['status'], 'Tài khoản chỉ xem gửi yêu cầu tạo bản nháp trực tiếp: bị 403');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $vcsrf, 'action' => 'create_flag', 'project_id' => $P, 'flag_key' => 'hack', 'name' => 'Hack'], $vj);
same(403, $r['status'], 'Tài khoản chỉ xem gửi yêu cầu khai báo tính năng trực tiếp: bị 403');
same($draftsBefore, (int)dbOne("SELECT COUNT(*) c FROM versions WHERE status='draft'")['c'], 'Dữ liệu không bị thay đổi sau yêu cầu bị từ chối');
same(null, dbOne("SELECT id FROM flags WHERE flag_key='hack'"), 'Tính năng "hack" không được tạo');
same(200, http('GET', "$base/handle/export.php?version_id=" . (int)getEnvironment($testId)['active_version_id'], [], $vj)['status'], 'Người chỉ xem tải được JSON cấu hình');

[$aj, $acsrf] = login($base, 'admin');
$r = http('POST', "$base/handle/version_process.php", ['action' => 'create_draft', 'environment_id' => $testId], $aj);
same(403, $r['status'], 'Thiếu token CSRF: bị 403');
$active = (int)getEnvironment($testId)['active_version_id'];
$rulesBefore = count(dbAll('SELECT id FROM rules WHERE version_id = ?', 'i', [$active]));
$r = http('POST', "$base/handle/version_process.php", ['csrf' => $acsrf, 'action' => 'add_rule', 'version_id' => $active, 'flag_id' => $fid['alpha'], 'rule_type' => 'percentage', 'percentage' => 10, 'priority' => 9, 'value' => '1'], $aj);
same($rulesBefore, count(dbAll('SELECT id FROM rules WHERE version_id = ?', 'i', [$active])), 'Gửi trực tiếp yêu cầu sửa bản đã phát hành: không có quy tắc nào được thêm');

[$oj, $ocsrf] = login($base, 'other');
same(403, http('GET', "$base/views/project.php?id=$P", [], $oj)['status'], 'Thành viên dự án khác không xem được dự án này');
$r = http('POST', "$base/handle/version_process.php", ['csrf' => $ocsrf, 'action' => 'create_draft', 'version_id' => $active, 'environment_id' => $testId], $oj);
same(403, $r['status'], 'Thành viên dự án khác không sửa được (dù có quyền sửa dự án của họ)');

$key = $test['api_key'];
same(401, http('GET', "$base/api/evaluate.php?key=sai&user=u1&feature=alpha")['status'], 'API: key sai trả 401');
same(400, http('GET', "$base/api/evaluate.php?key=$key&feature=alpha")['status'], 'API: thiếu user trả 400');
same(404, http('GET', "$base/api/evaluate.php?key=$key&user=u1&feature=khong_co")['status'], 'API: tính năng lạ trả 404');
$j = json_decode(http('GET', "$base/api/evaluate.php?key=$key&user=user0001&feature=beta_f")['body'], true);
check(($j['enabled'] ?? null) === false && ($j['version'] ?? 0) >= 2, 'API trả JSON bật/tắt kèm số phiên bản');
$a1 = http('GET', "$base/api/evaluate.php?key=$key&user=user0050")['body'];
$a2 = http('GET', "$base/api/evaluate.php?key=$key&user=user0050")['body'];
same($a1, $a2, 'API: hai lần gọi liên tiếp cho cùng kết quả');

/* ---------- F. Ứng dụng thử nghiệm khi API lỗi ---------- */
section('G. Giao diện: số liệu hiển thị khớp mô phỏng, quyền xem/sửa trên các trang mới');
$actId = (int)getEnvironment($testId)['active_version_id'];
$simNow = simulateVersion($actId);
$bdNow = ffmFlagBreakdown($actId, 'alpha');
same($simNow['alpha']['on'], $bdNow['on'], 'Phân rã quyết định khớp simulateVersion cho cùng phiên bản');
same(200, $bdNow['total'], 'Tổng người dùng giả của dự án kiểm thử là 200');
same(null, ffmFlagBreakdown($actId, 'khong_co'), 'Tính năng không có trong phiên bản: trả null thay vì lỗi');
$pg = http('GET', "$base/views/projects.php", [], $aj);
check($pg['status'] === 200 && strpos($pg['body'], 'Hoạt động gần đây') !== false, 'Trang danh sách dự án hiển thị hoạt động gần đây');
$vp = http('GET', "$base/views/version.php?id=$actId&flag=" . (int)$fid['alpha'], [], $aj);
check($vp['status'] === 200 && strpos($vp['body'], 'add_rule') === false && strpos($vp['body'], 'delete_rule') === false, 'Bản đã phát hành hiển thị chỉ đọc: không có form thêm/xóa quy tắc');
$ev = http('GET', "$base/views/environment.php?id=$testId", [], $vj);
check($ev['status'] === 200 && strpos($ev['body'], $test['api_key']) === false && strpos($ev['body'], 'name="restore"') === false, 'Trang môi trường cho người chỉ xem: ẩn API key và thao tác khôi phục');
$ea = http('GET', "$base/views/environment.php?id=$testId", [], $aj);
check(strpos($ea['body'], $test['api_key']) !== false, 'Trang môi trường cho người được sửa: hiển thị API key');
same(404, http('GET', "$base/views/version.php?id=999999", [], $aj)['status'], 'Phiên bản không tồn tại: 404');
$noJar = [];
$anon = http('GET', "$base/views/projects.php", [], $noJar);
same(302, $anon['status'], 'Chưa đăng nhập: chuyển về trang đăng nhập');
same('../index.php', $anon['location'], 'Đích chuyển hướng là địa chỉ web tương đối, không lộ đường dẫn tệp trên máy chủ');
same(404, http('GET', "$base/views/compare.php?a=$actId&b=999999", [], $aj)['status'], 'So sánh với phiên bản không tồn tại: 404');

section('H. Nhóm người dùng: thêm/sửa nhiều người, danh sách tổng hợp, nhập JSON mẫu');
$gBeta = (int)dbOne("SELECT id FROM user_groups WHERE project_id = ? AND group_key = 'beta'", 'i', [$P])['id'];
setGroupMembers($P, $gBeta, "user0001\nuser0002");
$r = addGroupMembers($P, $gBeta, ['user0002', 'user0003', 'user0004']);
same(['added' => 2, 'already' => 1, 'created' => 0], $r, 'Thêm nhiều người: đếm đúng người mới và người đã có');
same(['user0001', 'user0002', 'user0003', 'user0004'], ffmGroupMemberIds($gBeta), 'Thêm thành viên không xóa người đang có trong nhóm');
$r = addGroupMembers($P, $gBeta, "user9001, user9002\nuser9001");
same(['added' => 2, 'already' => 0, 'created' => 2], $r, 'Mã mới được tạo thành người dùng giả và vào nhóm (mã trùng chỉ tính một lần)');
same(1, (int)dbOne('SELECT COUNT(*) c FROM end_users WHERE project_id = ? AND end_user_id = ?', 'is', [$P, 'user9001'])['c'], 'Người dùng giả mới có trong danh sách của dự án');
$before = count(ffmGroupMemberIds($gBeta));
throwsA(InvalidArgumentException::class, fn() => addGroupMembers($P, $gBeta, ['user0010', 'sai ma!']), 'Mã chứa ký tự lạ bị từ chối');
same($before, count(ffmGroupMemberIds($gBeta)), 'Mã sai: không người nào được thêm một phần');
throwsA(InvalidArgumentException::class, fn() => addGroupMembers($P, $gBeta, ''), 'Không chọn ai: báo lỗi');
$P3 = createProject('Dự án nhập JSON mẫu', '', $admin);
$gx = createGroup($P3, 'beta', 'Beta');
throwsA(InvalidArgumentException::class, fn() => addGroupMembers($P, $gx, ['user0001']), 'Nhóm của dự án khác: bị từ chối');
setGroupMembers($P, $gBeta, array_map(fn($i) => sprintf('user%04d', $i), range(1, 150)));
same(150, count(ffmGroupMemberIds($gBeta)), 'Sửa thành viên bằng mảng (ô chọn nhiều người) thay toàn bộ danh sách');
setGroupMembers($P, $gBeta, '');
same(0, count(ffmGroupMemberIds($gBeta)), 'Bỏ chọn hết: nhóm rỗng');

// HTTP: người chỉ xem không thêm/sửa được nhóm; người được sửa thêm được, gửi 1.000 mã một lần
$thousand = implode("\n", array_map(fn($i) => sprintf('user%04d', $i), range(1, 1000)));
$gCnt = fn() => (int)dbOne('SELECT COUNT(*) c FROM group_members WHERE group_id = ?', 'i', [$gBeta])['c'];
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $vcsrf, 'action' => 'add_group_members', 'project_id' => $P, 'group_id' => $gBeta, 'members' => 'user0001'], $vj);
same(403, $r['status'], 'Người chỉ xem gửi trực tiếp yêu cầu thêm thành viên nhóm: bị 403');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $vcsrf, 'action' => 'set_group_members', 'project_id' => $P, 'group_id' => $gBeta, 'members' => 'user0001'], $vj);
same(403, $r['status'], 'Người chỉ xem gửi trực tiếp yêu cầu sửa thành viên nhóm: bị 403');
same(0, $gCnt(), 'Yêu cầu bị từ chối không làm đổi nhóm');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $acsrf, 'action' => 'add_group_members', 'project_id' => $P, 'group_id' => $gBeta, 'members' => "user0001\nuser0002", 'new_ids' => 'user7777'], $aj);
same(302, $r['status'], 'Người được sửa thêm thành viên nhóm: chuyển về trang dự án');
same(3, $gCnt(), 'Thêm cả người đã chọn và mã nhập mới');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $acsrf, 'action' => 'set_group_members', 'project_id' => $P, 'group_id' => $gBeta, 'members' => $thousand], $aj);
same(1000, $gCnt(), 'Sửa nhóm với 1.000 mã trong một yêu cầu (một ô ẩn, không vướng giới hạn số trường)');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $acsrf, 'action' => 'add_group_members', 'project_id' => $P, 'group_id' => $gx, 'members' => 'user0001'], $aj);
same(0, (int)dbOne('SELECT COUNT(*) c FROM group_members WHERE group_id = ?', 'i', [$gx])['c'], 'Gửi nhóm của dự án khác: không ghi gì');
setGroupMembers($P, $gBeta, array_map(fn($i) => sprintf('user%04d', $i), range(1, 20)));

// Trang "Người dùng giả": đọc được với quyền xem, nút thêm/sửa chỉ hiện cho người được sửa
$dv = http('GET', "$base/views/directory.php?id=$P", [], $vj);
check($dv['status'] === 200 && strpos($dv['body'], 'user0001') !== false, 'Người chỉ xem mở được danh sách người dùng giả');
$pv = http('GET', "$base/views/project.php?id=$P", [], $vj)['body'];
check(strpos($pv, 'data-picker') === false && strpos($pv, 'picker-data') === false, 'Người chỉ xem: không có nút thêm/sửa thành viên nhóm');
$pa = http('GET', "$base/views/project.php?id=$P", [], $aj)['body'];
check(strpos($pa, 'data-picker="add"') !== false && strpos($pa, 'data-picker="set"') !== false, 'Người được sửa: có nút Thêm thành viên và Sửa thành viên');
same(403, http('GET', "$base/views/directory.php?id=$P", [], $oj)['status'], 'Thành viên dự án khác không xem được danh sách người dùng giả');
$df = http('GET', "$base/views/directory.php?id=$P&group=beta&result=on&flag=alpha", [], $aj);
same(200, $df['status'], 'Bộ lọc nhóm + kết quả hoạt động');

// Nhập tệp JSON mẫu vào dự án có đủ 10 tính năng, 3 nhóm như seed
foreach (['new_checkout', 'dark_mode', 'product_reviews', 'fast_search', 'wishlist', 'live_chat', 'promo_banner', 'one_click_buy', 'recommendations', 'beta_dashboard'] as $k) {
    createFlag($P3, $k, $k, '');
}
createGroup($P3, 'vip', 'VIP');
createGroup($P3, 'staff', 'Staff');
$e3 = array_column(getEnvironments($P3), null, 'env_key')['testing'];
$bad = (string)file_get_contents(__DIR__ . '/../database/samples/import_invalid.json');
$draftsBefore = count(dbAll("SELECT id FROM versions WHERE environment_id = ? AND status = 'draft'", 'i', [$e3['id']]));
$msg = '';
try { importConfigAsDraft((int)$e3['id'], $bad, $admin, 'thử'); } catch (InvalidArgumentException $ex) { $msg = $ex->getMessage(); }
check(substr_count($msg, "\n") >= 6, 'Tệp import_invalid.json bị từ chối và liệt kê đủ các lỗi');
same($draftsBefore, count(dbAll("SELECT id FROM versions WHERE environment_id = ? AND status = 'draft'", 'i', [$e3['id']])), 'Tệp sai: không tạo bản nháp nào');
$good = (string)file_get_contents(__DIR__ . '/../database/samples/import_valid.json');
$did = importConfigAsDraft((int)$e3['id'], $good, $admin, 'Nhập tệp mẫu');
$imp = loadVersionConfig($did);
same(10, count($imp['flags']), 'Tệp import_valid.json: nhập đủ 10 tính năng vào bản nháp');
same(true, $imp['flags']['dark_mode']['default'], 'Giá trị mặc định được nhập đúng');
same(3, count($imp['flags']['recommendations']['rules']), 'Quy tắc ưu tiên được nhập đủ');
$expFlags = json_decode($good, true)['flags'];
$gotFlags = array_map(fn($f) => ['default' => $f['default'], 'rules' => $f['rules']], $imp['flags']);
ksort($expFlags);
ksort($gotFlags);
same($expFlags, $gotFlags, 'Cấu hình sau khi nhập giống hệt nội dung tệp');

section('I. Quyền quản trị: chỉ quản trị viên thêm hoặc đổi quyền thành viên');
same('admin', getProjectRole($P, $admin), 'Người tạo dự án là quản trị viên');
same('editor', getProjectRole($P, $dev), 'Tài khoản dev chỉ được sửa cấu hình');
throwsA(InvalidArgumentException::class, fn() => setMember($P, 'admin', 'editor'), 'Hạ quyền quản trị viên cuối cùng: bị từ chối');
same('admin', getProjectRole($P, $admin), 'Quản trị viên cuối cùng vẫn giữ quyền');
setMember($P, 'dev', 'admin');
setMember($P, 'admin', 'editor');
same(['editor', 'admin'], [getProjectRole($P, $admin), getProjectRole($P, $dev)], 'Còn quản trị viên khác thì hạ quyền được');
setMember($P, 'admin', 'admin');
setMember($P, 'dev', 'editor');
same(['admin', 'editor'], [getProjectRole($P, $admin), getProjectRole($P, $dev)], 'Khôi phục quyền ban đầu của hai tài khoản');
[$dj, $dcsrf] = login($base, 'dev');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $dcsrf, 'action' => 'add_member', 'project_id' => $P, 'username' => 'other', 'role' => 'editor'], $dj);
same(403, $r['status'], 'Tài khoản được sửa (dev) gửi trực tiếp yêu cầu thêm thành viên: bị 403');
same(null, getProjectRole($P, $other), 'Thành viên không được thêm sau yêu cầu bị từ chối');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $vcsrf, 'action' => 'add_member', 'project_id' => $P, 'username' => 'other', 'role' => 'viewer'], $vj);
same(403, $r['status'], 'Tài khoản chỉ xem gửi yêu cầu thêm thành viên: bị 403');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $dcsrf, 'action' => 'create_flag', 'project_id' => $P, 'flag_key' => 'dev_flag', 'name' => 'Do dev tạo'], $dj);
same(302, $r['status'], 'Tài khoản được sửa vẫn khai báo được tính năng');
check(dbOne("SELECT id FROM flags WHERE flag_key = 'dev_flag'") !== null, 'Tính năng do dev khai báo đã được tạo');
$devPage = http('GET', "$base/views/project.php?id=$P", [], $dj)['body'];
check(strpos($devPage, 'add_member') === false && strpos($devPage, 'create_flag') !== false, 'Trang dự án của dev: có form khai báo tính năng, không có form thêm thành viên');
check(strpos(http('GET', "$base/views/project.php?id=$P", [], $aj)['body'], 'add_member') !== false, 'Trang dự án của quản trị viên có form thêm thành viên');
$r = http('POST', "$base/handle/project_process.php", ['csrf' => $acsrf, 'action' => 'add_member', 'project_id' => $P, 'username' => 'other', 'role' => 'viewer'], $aj);
same(302, $r['status'], 'Quản trị viên thêm thành viên bằng form: được chuyển về trang dự án');
same('viewer', getProjectRole($P, $other), 'Thành viên mới có đúng quyền đã chọn');

section('H. Phiên cũ: tài khoản đã bị xóa khỏi CSDL thì không còn đăng nhập được');
$ghost = mkUser('ghost');
setMember($P, 'ghost', 'viewer');
[$gj] = login($base, 'ghost');
same(200, http('GET', "$base/views/projects.php", [], $gj)['status'], 'Tài khoản còn trong CSDL: vào được trang dự án');
dbExec('DELETE FROM users WHERE id = ?', 'i', [$ghost]);
$stale = http('GET', "$base/views/projects.php", [], $gj);
same(302, $stale['status'], 'Tài khoản đã bị xóa: phiên cũ bị chuyển về trang đăng nhập, không hiện trang trống');
$loginPage = http('GET', "$base/index.php", [], $gj);
check(strpos($loginPage['body'], 'không còn hợp lệ') !== false, 'Trang đăng nhập nói rõ lý do phiên bị hủy');

section('F. Ứng dụng thử nghiệm dùng giá trị dự phòng khi API lỗi');
putenv('FFM_API_BASE=http://127.0.0.1:1/api/evaluate.php');   // cổng đóng
putenv('FFM_API_KEY=' . $key);
require_once __DIR__ . '/../demo_app/client.php';
@unlink(__DIR__ . '/../demo_app/logs/error.log');
$res = ffmFetchFlags('user0001');
same('fallback', $res['source'], 'API ngắt: ứng dụng chuyển sang giá trị dự phòng');
same(FFM_FALLBACK, $res['flags'], 'Giá trị dự phòng đúng như quy định');
check(is_file(__DIR__ . '/../demo_app/logs/error.log') && strlen((string)file_get_contents(__DIR__ . '/../demo_app/logs/error.log')) > 0, 'Lỗi được ghi vào nhật ký');
putenv('FFM_API_BASE=' . $base . '/api/evaluate.php');
$res = ffmFetchFlags('user0050');
same('api', $res['source'], 'API hoạt động lại: ứng dụng dùng kết quả thật');
putenv('FFM_API_KEY=sai_key');
same('fallback', ffmFetchFlags('user0050')['source'], 'API key sai (401): ứng dụng cũng dùng giá trị dự phòng');
@unlink(__DIR__ . '/../demo_app/logs/error.log');

finish();
