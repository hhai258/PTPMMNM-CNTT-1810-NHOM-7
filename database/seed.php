<?php
/**
 * Nạp dữ liệu mẫu (toàn bộ là dữ liệu giả):
 *   php database/seed.php
 * Tạo: 3 tài khoản, 1 dự án, 2 môi trường, 10 tính năng, 3 nhóm, 1.000 mã người dùng giả,
 * mỗi môi trường có bản v1 (rỗng) và v2 (cấu hình đầu tiên); riêng môi trường Thử nghiệm có thêm v3
 * (đã phát hành) khác v2 ở nhiều điểm để thử so sánh/khôi phục. Tệp database/samples/*.json dùng để thử nhập cấu hình.
 */
if (PHP_SAPI !== 'cli') {
    exit("Chỉ chạy bằng dòng lệnh.\n");
}
require_once __DIR__ . '/../functions/project_functions.php';

$conn = getDbConnection();
$conn->multi_query(file_get_contents(__DIR__ . '/schema.sql'));
do { /* chạy hết các lệnh trong schema */ } while ($conn->more_results() && $conn->next_result());

// Tài khoản demo (mật khẩu giả, chỉ dùng thử cục bộ)
$accounts = [['admin', 'admin123', 'Quản trị viên'], ['dev', 'dev123', 'Lập trình viên'], ['viewer', 'viewer123', 'Người chỉ xem']];
$ids = [];
foreach ($accounts as [$u, $p, $n]) {
    dbExec('INSERT INTO users (username, password_hash, full_name) VALUES (?, ?, ?)', 'sss', [$u, password_hash($p, PASSWORD_DEFAULT), $n]);
    $ids[$u] = dbInsertId();
}

$pid = createProject('Cửa hàng demo', 'Dự án mẫu: thử nghiệm dần các tính năng mới của cửa hàng trực tuyến', $ids['admin']);
setMember($pid, 'dev', 'editor');
setMember($pid, 'viewer', 'viewer');

$flagDefs = [
    ['new_checkout', 'Thanh toán mới'], ['dark_mode', 'Giao diện tối'], ['product_reviews', 'Đánh giá sản phẩm'],
    ['fast_search', 'Tìm kiếm nhanh'], ['wishlist', 'Danh sách yêu thích'], ['live_chat', 'Chat trực tuyến'],
    ['promo_banner', 'Banner khuyến mãi'], ['one_click_buy', 'Mua một chạm'], ['recommendations', 'Gợi ý sản phẩm'], ['beta_dashboard', 'Bảng điều khiển beta'],
];
foreach ($flagDefs as [$k, $n]) {
    createFlag($pid, $k, $n, 'Tính năng mẫu: ' . $n);
}

// 1.000 mã người dùng giả + 3 nhóm
$groupIds = [];
foreach (['beta' => 'Người dùng thử nghiệm', 'vip' => 'Khách VIP', 'staff' => 'Nhân viên nội bộ'] as $k => $n) {
    $groupIds[$k] = createGroup($pid, $k, $n);
}
$members = ['beta' => [], 'vip' => [], 'staff' => []];
for ($i = 1; $i <= 1000; $i++) {
    $uid = sprintf('user%04d', $i);
    dbExec('INSERT INTO end_users (project_id, end_user_id) VALUES (?, ?)', 'is', [$pid, $uid]);
    if ($i % 5 === 0) $members['beta'][] = $uid;   // 200 người
    if ($i % 7 === 0) $members['vip'][] = $uid;    // 142 người, một phần trùng nhóm beta (i chia hết cho 35)
    if ($i <= 20) $members['staff'][] = $uid;      // 20 người
}
foreach ($members as $k => $list) {
    setGroupMembers($pid, $groupIds[$k], implode("\n", $list));
}

// Cấu hình v2 cho từng môi trường (v1 là bản rỗng, mọi tính năng TẮT)
$flagIdByKey = array_column(getFlags($pid), 'id', 'flag_key');
$adminId = $ids['admin'];
foreach (getEnvironments($pid) as $env) {
    $d = createDraft((int)$env['id'], (int)$env['active_version_id'], $adminId, 'Cấu hình thử nghiệm đầu tiên');
    setFlagDefault($d, $flagIdByKey['dark_mode'], true, 'Bật giao diện tối cho mọi người', $adminId);
    addRule($d, $flagIdByKey['new_checkout'], ['rule_type' => 'user_list', 'users' => 'user0001 user0002', 'value' => '1', 'priority' => '1'], $adminId);
    addRule($d, $flagIdByKey['new_checkout'], ['rule_type' => 'group', 'group' => 'beta', 'percentage' => '50', 'value' => '1', 'priority' => '2'], $adminId);
    addRule($d, $flagIdByKey['new_checkout'], ['rule_type' => 'percentage', 'percentage' => '10', 'value' => '1', 'priority' => '3'], $adminId);
    addRule($d, $flagIdByKey['fast_search'], ['rule_type' => 'percentage', 'percentage' => '100', 'value' => '1', 'priority' => '1'], $adminId);
    addRule($d, $flagIdByKey['beta_dashboard'], ['rule_type' => 'group', 'group' => 'staff', 'percentage' => '100', 'value' => '1', 'priority' => '1'], $adminId);
    addRule($d, $flagIdByKey['live_chat'], ['rule_type' => 'percentage', 'percentage' => '0', 'value' => '1', 'priority' => '1'], $adminId);
    publishVersion($d, 'Phát hành cấu hình mẫu', $adminId);
}

// Môi trường Thử nghiệm: v3 khác v2 ở nhiều điểm để thử so sánh và khôi phục
$envs = array_column(getEnvironments($pid), null, 'env_key');
$test = $envs['testing'];
$ruleIdOf = function (int $versionId, string $flagKey, string $type) use ($flagIdByKey): int {
    foreach (getRuleRows($versionId, (int)$flagIdByKey[$flagKey]) as $r) {
        if ($r['rule_type'] === $type) return (int)$r['id'];
    }
    throw new RuntimeException("Không thấy quy tắc $type của $flagKey");
};
$d = createDraft((int)$test['id'], (int)$test['active_version_id'], $adminId, 'Mở rộng thử nghiệm: thanh toán mới 30%, ưu tiên VIP');
deleteRule($d, $ruleIdOf($d, 'new_checkout', 'percentage'), $adminId);                     // 10% → 30%
addRule($d, $flagIdByKey['new_checkout'], ['rule_type' => 'percentage', 'percentage' => '30', 'value' => '1', 'priority' => '3', 'reason' => 'Tăng tỷ lệ thanh toán mới lên 30%'], $adminId);
deleteRule($d, $ruleIdOf($d, 'live_chat', 'percentage'), $adminId);                        // 0% → 25%
addRule($d, $flagIdByKey['live_chat'], ['rule_type' => 'percentage', 'percentage' => '25', 'value' => '1', 'priority' => '1', 'reason' => 'Mở chat cho 25% người dùng'], $adminId);
addRule($d, $flagIdByKey['one_click_buy'], ['rule_type' => 'group', 'group' => 'staff', 'percentage' => '100', 'value' => '1', 'priority' => '1', 'reason' => 'Nhân viên dùng thử trước'], $adminId);
addRule($d, $flagIdByKey['one_click_buy'], ['rule_type' => 'group', 'group' => 'vip', 'percentage' => '100', 'value' => '1', 'priority' => '2', 'reason' => 'Khách VIP được mua một chạm'], $adminId);
// Thứ tự ưu tiên: user0007 là VIP nhưng bị quy tắc ưu tiên 1 tắt; user0014 là VIP nên vẫn được bật
addRule($d, $flagIdByKey['recommendations'], ['rule_type' => 'user_list', 'users' => 'user0007', 'value' => '0', 'priority' => '1', 'reason' => 'Loại user0007 khỏi gợi ý'], $adminId);
addRule($d, $flagIdByKey['recommendations'], ['rule_type' => 'group', 'group' => 'vip', 'percentage' => '100', 'value' => '1', 'priority' => '2', 'reason' => 'VIP nhận gợi ý'], $adminId);
addRule($d, $flagIdByKey['recommendations'], ['rule_type' => 'percentage', 'percentage' => '20', 'value' => '1', 'priority' => '3', 'reason' => 'Thêm 20% người còn lại'], $adminId);
setFlagDefault($d, $flagIdByKey['wishlist'], true, 'Bật danh sách yêu thích cho mọi người', $adminId);
setFlagDefault($d, $flagIdByKey['promo_banner'], true, 'Bật biểu ngữ khuyến mãi', $adminId);
publishVersion($d, 'Phát hành v3: cấu hình thử nghiệm mở rộng', $adminId);

echo "Đã nạp dữ liệu mẫu.\n";
foreach (getEnvironments($pid) as $env) {
    echo "  Môi trường {$env['env_key']}: API key = {$env['api_key']}\n";
}
echo "Thử nghiệm đang phát hành v3 (v2 là cấu hình đầu tiên). Tệp nhập thử: database/samples/import_valid.json, import_invalid.json\n";
echo "Tài khoản: admin/admin123 (quản trị: sửa + quản lý thành viên), dev/dev123 (được sửa cấu hình), viewer/viewer123 (chỉ xem)\n";
