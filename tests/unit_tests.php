<?php
/** Kiểm thử đơn vị (không cần CSDL): php tests/unit_tests.php */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/../functions/evaluator.php';
require_once __DIR__ . '/../functions/config_tools.php';
require_once __DIR__ . '/../functions/overview_functions.php';
require_once __DIR__ . '/../functions/directory_functions.php';
require_once __DIR__ . '/../functions/project_functions.php';
require_once __DIR__ . '/../functions/error_page.php';

$flag = fn(array $rules, bool $default = false) => ['default' => $default, 'rules' => $rules];
$pct = fn(int $p, bool $v = true, int $prio = 1) => ['priority' => $prio, 'type' => 'percentage', 'percentage' => $p, 'value' => $v];

section('1. Kết quả ổn định và tỷ lệ 0% / 100%');
$cfg = $flag([$pct(30)]);
$first = ffmEvaluate($cfg, 'new_checkout', 'testing', 'user0042');
$stable = true;
for ($i = 0; $i < 200; $i++) {
    $stable = $stable && ffmEvaluate($cfg, 'new_checkout', 'testing', 'user0042') === $first;
}
check($stable, 'Gọi 200 lần cùng người dùng/tính năng/môi trường cho cùng kết quả');
$all0 = $all100 = true;
for ($i = 1; $i <= 1000; $i++) {
    $u = sprintf('user%04d', $i);
    $all0 = $all0 && ffmEvaluate($flag([$pct(0)], false), 'f', 'testing', $u)['enabled'] === false;
    $all100 = $all100 && ffmEvaluate($flag([$pct(100)], false), 'f', 'testing', $u)['enabled'] === true;
}
check($all0, 'Tỷ lệ 0%: không ai được bật (1.000 người)');
check($all100, 'Tỷ lệ 100%: ai cũng được bật (1.000 người)');
$on = 0;
for ($i = 1; $i <= 1000; $i++) {
    $on += ffmEvaluate($flag([$pct(30)]), 'new_checkout', 'testing', sprintf('user%04d', $i))['enabled'] ? 1 : 0;
}
check($on >= 240 && $on <= 360, "Tỷ lệ 30% phân bố gần đúng trên 1.000 người (được $on)");
$diffEnv = 0;
for ($i = 1; $i <= 1000; $i++) {
    $u = sprintf('user%04d', $i);
    if (ffmBucket('f', 'testing', $u) !== ffmBucket('f', 'production_sim', $u)) $diffEnv++;
}
check($diffEnv > 500, 'Mỗi môi trường chia xô độc lập');

section('2. Thứ tự ưu tiên giữa danh sách người dùng, nhóm và tỷ lệ');
$userList = ['priority' => 1, 'type' => 'user_list', 'users' => ['vip1'], 'value' => false];
$group = ['priority' => 2, 'type' => 'group', 'group' => 'beta', 'percentage' => 100, 'value' => true];
$cfg = $flag([$group, $userList, $pct(100, true, 3)]);   // khai báo sai thứ tự, vẫn phải xét theo priority
$r = ffmEvaluate($cfg, 'f', 'testing', 'vip1', ['beta']);
same(false, $r['enabled'], 'Người trong danh sách (p1) thắng nhóm (p2) và tỷ lệ 100% (p3)');
same('rule:user_list#p1', $r['reason'], 'Lý do ghi đúng quy tắc đã khớp');
$r = ffmEvaluate($cfg, 'f', 'testing', 'u2', ['beta']);
same('rule:group#p2', $r['reason'], 'Người thuộc nhóm nhưng không trong danh sách khớp quy tắc nhóm');
$r = ffmEvaluate($cfg, 'f', 'testing', 'u3', []);
same('rule:percentage#p3', $r['reason'], 'Người ngoài nhóm rơi xuống quy tắc tỷ lệ');
$cfg2 = $flag([$pct(0, true, 1), $pct(100, false, 2)], true);
same('rule:percentage#p2', ffmEvaluate($cfg2, 'f', 'testing', 'u')['reason'], 'Quy tắc không khớp thì xét tiếp quy tắc sau');
same('default', ffmEvaluate($flag([], true), 'f', 'testing', 'u')['reason'], 'Không có quy tắc: dùng giá trị mặc định');
$g50 = $flag([['priority' => 1, 'type' => 'group', 'group' => 'beta', 'percentage' => 0, 'value' => true]], false);
same(false, ffmEvaluate($g50, 'f', 'testing', 'u', ['beta'])['enabled'], 'Nhóm với tỷ lệ 0% không bật cho ai');

section('Kiểm tra dữ liệu nhập');
$good = ['format' => FFM_FORMAT, 'flags' => ['a' => ['default' => true, 'rules' => [
    ['priority' => 1, 'type' => 'percentage', 'percentage' => 50, 'value' => true],
    ['priority' => 2, 'type' => 'group', 'group' => 'beta', 'percentage' => 100, 'value' => false],
    ['priority' => 3, 'type' => 'user_list', 'users' => ['u1'], 'value' => true]]]]];
same([], ffmValidateConfig($good, ['a'], ['beta']), 'Cấu hình hợp lệ không có lỗi');
$bad = $good;
$bad['flags']['a']['rules'][0]['percentage'] = 150;
check(count(ffmValidateConfig($bad, ['a'], ['beta'])) === 1, 'Tỷ lệ 150% bị từ chối');
$bad = $good; $bad['flags']['zzz'] = ['default' => true];
check(count(ffmValidateConfig($bad, ['a'], ['beta'])) === 1, 'Tính năng chưa khai báo bị từ chối');
$bad = $good; $bad['flags']['a']['rules'][1]['group'] = 'ghost';
check(count(ffmValidateConfig($bad, ['a'], ['beta'])) === 1, 'Nhóm chưa khai báo bị từ chối');
$bad = $good; $bad['flags']['a']['default'] = 'yes';
check(count(ffmValidateConfig($bad, ['a'], ['beta'])) === 1, 'default không phải boolean bị từ chối');
$bad = $good; $bad['format'] = 'v9';
check(count(ffmValidateConfig($bad, ['a'], ['beta'])) === 1, 'Sai format bị từ chối');
check(ffmValidateConfig('rác', [], []) !== [], 'Dữ liệu không phải đối tượng bị từ chối');

section('So sánh phiên bản');
$old = ['flags' => ['a' => ['default' => false, 'rules' => [['priority' => 1, 'type' => 'percentage', 'percentage' => 10, 'value' => true]]], 'b' => ['default' => true, 'rules' => []]]];
$new = ['flags' => ['a' => ['default' => true, 'rules' => [['priority' => 1, 'type' => 'percentage', 'percentage' => 50, 'value' => true]]], 'b' => ['default' => true, 'rules' => []]]];
$d = ffmDiffConfigs($old, $new);
same(['default', 'rule_changed'], array_column($d, 'kind'), 'Phát hiện đổi mặc định và đổi tỷ lệ');
same([], ffmDiffConfigs($old, $old), 'Hai bản giống nhau không có khác biệt');

section('Phân rã quyết định theo quy tắc (giao diện chi tiết phiên bản)');
$us = array_map(fn($i) => sprintf('user%04d', $i), range(1, 1000));
$gm = [];
foreach (range(1, 100) as $i) { $gm[sprintf('user%04d', $i)] = ['beta']; }
$cfgB = ['default' => false, 'rules' => [
    ['priority' => 1, 'type' => 'user_list', 'users' => ['user0500', 'user0501', 'khong_ton_tai'], 'value' => false],
    ['priority' => 2, 'type' => 'group', 'group' => 'beta', 'percentage' => 100, 'value' => true],
    ['priority' => 3, 'type' => 'percentage', 'percentage' => 25, 'value' => true],
]];
$bd = ffmBreakdownFromConfig($cfgB, 'f', 'testing', $us, $gm);
same(1000, array_sum(array_column($bd['segments'], 'count')), 'Mỗi người dùng được tính đúng một lần: tổng các đoạn bằng tổng người dùng');
same(['rule:user_list#p1', 'rule:group#p2', 'rule:percentage#p3', 'default'], array_column($bd['segments'], 'reason'), 'Các đoạn theo thứ tự xét: quy tắc theo ưu tiên rồi đến mặc định');
same(2, $bd['segments'][0]['count'], 'Danh sách người dùng chỉ quyết định cho người có trong dữ liệu (bỏ mã không tồn tại)');
same(100, $bd['segments'][1]['count'], 'Nhóm beta 100% quyết định cho đủ 100 thành viên');
$onSum = 0;
foreach ($bd['segments'] as $sg) { $onSum += $sg['enabled'] ? $sg['count'] : 0; }
same($bd['on'], $onSum, 'Số người được bật khớp tổng các đoạn có kết quả BẬT');
$direct = 0;
foreach ($us as $u) { $direct += ffmEvaluate($cfgB, 'f', 'testing', $u, $gm[$u] ?? [])['enabled'] ? 1 : 0; }
same($direct, $bd['on'], 'Phân rã khớp từng kết quả của evaluator (cùng thuật toán với API)');
same(0, ffmBreakdownFromConfig(['default' => true, 'rules' => []], 'f', 'testing', [], [])['total'], 'Không có người dùng giả: tổng bằng 0, không lỗi');

section('Danh sách tổng hợp người dùng giả: nhóm, kết quả, quy tắc ưu tiên');
$us3 = ['user0001', 'user0007', 'user0014', 'user0020', 'user0021'];
$gm3 = ['user0007' => ['vip'], 'user0014' => ['beta', 'vip'], 'user0020' => ['beta']];
$cfgD = ['default' => false, 'rules' => [
    ['priority' => 1, 'type' => 'user_list', 'users' => ['user0007'], 'value' => false],
    ['priority' => 2, 'type' => 'group', 'group' => 'vip', 'percentage' => 100, 'value' => true],
    ['priority' => 3, 'type' => 'group', 'group' => 'beta', 'percentage' => 100, 'value' => true],
]];
$dir = array_column(ffmBuildDirectory($us3, $gm3, $cfgD, 'f', 'testing'), null, 'user');
same(false, $dir['user0007']['enabled'], 'Người trong nhóm VIP nhưng nằm trong danh sách ưu tiên 1 (tắt): bị tắt');
same(1, $dir['user0007']['priority'], 'Số ưu tiên của quy tắc quyết định được hiển thị (p1)');
same(2, $dir['user0014']['priority'], 'Người thuộc cả VIP (p2) và beta (p3): quy tắc ưu tiên nhỏ hơn quyết định');
same(null, $dir['user0001']['priority'], 'Không khớp quy tắc nào: dùng mặc định, không có số ưu tiên');
same('default', $dir['user0021']['reason'], 'Người không thuộc nhóm nào dùng mặc định');
foreach ($us3 as $u) {
    $dir[$u] === ffmBuildDirectory([$u], $gm3, $cfgD, 'f', 'testing')[0] || check(false, 'Dòng của ' . $u . ' khớp ffmEvaluate');
}
same(['user0014', 'user0020'], array_column(ffmFilterDirectory(array_values($dir), '', 'beta'), 'user'), 'Lọc theo nhóm beta');
same(['user0001', 'user0021'], array_column(ffmFilterDirectory(array_values($dir), '', '_none'), 'user'), 'Lọc người không thuộc nhóm nào');
same(['user0007'], array_column(ffmFilterDirectory(array_values($dir), '', '', 'off', 'rule:user_list#p1'), 'user'), 'Lọc theo kết quả + quy tắc quyết định');
same(['user0020'], array_column(ffmFilterDirectory(array_values($dir), 'USER0020'), 'user'), 'Tìm mã người dùng không phân biệt hoa thường');
[$slice, $pages, $pg] = ffmPaginate(range(1, 120), 99, 50);
same([3, 20, 3], [$pages, count($slice), $pg], 'Phân trang: trang vượt giới hạn được kẹp về trang cuối (20 dòng)');
same([1, 1, 1], [ffmPaginate([], 0)[2], ffmPaginate([], 0)[1], ffmPaginate([], -3)[2]], 'Danh sách rỗng: một trang, không lỗi');
$cnt = ffmGroupCounts($us3, $gm3);
same(['_none' => 2, 'vip' => 2, 'beta' => 2], $cnt, 'Đếm người theo nhóm, tách người chưa có nhóm');
same('p1 danh sách 1 người → TẮT', ffmReasonLabels($cfgD)['rule:user_list#p1'], 'Nhãn quy tắc dễ đọc');
$pt = ffmReasonParts($cfgD);
same(['priority' => 2, 'rule' => 'nhóm vip 100%', 'value' => true], $pt['rule:group#p2'], 'Tách số ưu tiên, mô tả quy tắc và giá trị để hiển thị từng cột');
same(['priority' => null, 'rule' => 'Mặc định của tính năng', 'value' => false], $pt['default'], 'Lý do mặc định không có số ưu tiên');
same('nhóm vip 100%', $dir['user0014']['rule'], 'Dòng danh sách mang mô tả quy tắc quyết định');

section('Mã người dùng nhập tay / chọn nhiều người');
same(['user1', 'user2', 'user3'], parseEndUserIds("user1, user2\nuser3 user1"), 'Tách theo dấu cách, phẩy, xuống dòng; bỏ trùng');
same(['a', 'b'], parseEndUserIds(['a', ' b ', 'a', '']), 'Nhận cả mảng từ ô chọn nhiều người');
throwsA(InvalidArgumentException::class, fn() => parseEndUserIds('ok <script>'), 'Ký tự lạ bị từ chối');
throwsA(InvalidArgumentException::class, fn() => parseEndUserIds(str_repeat('a', 61)), 'Mã dài quá 60 ký tự bị từ chối');
throwsA(InvalidArgumentException::class, fn() => parseEndUserIds(array_map(fn($i) => "u$i", range(1, 5001))), 'Quá 5.000 mã một lần bị từ chối');

section('Tệp JSON mẫu để thử nhập cấu hình');
$seedFlags = ['new_checkout', 'dark_mode', 'product_reviews', 'fast_search', 'wishlist', 'live_chat', 'promo_banner', 'one_click_buy', 'recommendations', 'beta_dashboard'];
$okJson = json_decode((string)file_get_contents(__DIR__ . '/../database/samples/import_valid.json'), true);
same([], ffmValidateConfig($okJson, $seedFlags, ['beta', 'vip', 'staff']), 'import_valid.json qua bộ kiểm tra dữ liệu nhập');
$badJson = json_decode((string)file_get_contents(__DIR__ . '/../database/samples/import_invalid.json'), true);
check(count(ffmValidateConfig($badJson, $seedFlags, ['beta', 'vip', 'staff'])) >= 6, 'import_invalid.json bị từ chối với nhiều lỗi (ưu tiên, tỷ lệ, nhóm, loại, tính năng lạ)');

section('Quyền theo thứ bậc: quản trị ⊃ sửa ⊃ xem');
check(roleAtLeast('admin', 'admin') && roleAtLeast('admin', 'editor') && roleAtLeast('admin', 'viewer'), 'Quản trị có đủ quyền quản trị, sửa và xem');
check(!roleAtLeast('editor', 'admin') && roleAtLeast('editor', 'editor') && roleAtLeast('editor', 'viewer'), 'Được sửa: sửa và xem được nhưng không có quyền quản trị');
check(!roleAtLeast('viewer', 'editor') && !roleAtLeast('viewer', 'admin') && roleAtLeast('viewer', 'viewer'), 'Chỉ xem: không sửa được, không quản trị được');
check(!roleAtLeast(null, 'viewer') && !roleAtLeast('khac', 'viewer') && !roleAtLeast('admin', 'khong_co'), 'Không phải thành viên, quyền lạ hoặc mức yêu cầu lạ: bị từ chối');

section('Trang lỗi dùng chung có khung, h1 và lối đi tiếp');
$html = errorPageHtml(404, 'Không tìm thấy dự án.', '/ffm');
check(str_contains($html, '<h1>Không tìm thấy</h1>') && str_contains($html, 'lang="vi"'), 'Trang 404 có lang="vi" và đúng một h1 nêu rõ chuyện gì xảy ra');
check(str_contains($html, 'href="/ffm/css/style.css"') && str_contains($html, 'href="/ffm/views/projects.php"'), 'Trang 404 dùng chung CSS và có lối về danh sách dự án (kể cả khi triển khai trong thư mục con)');
check(str_contains(errorPageHtml(401, 'x', ''), 'href="/index.php"'), 'Trang 401 dẫn tới trang đăng nhập');
check(!str_contains(errorPageHtml(403, '<script>alert(1)</script>', ''), '<script>'), 'Thông điệp lỗi được thoát HTML');
same('/ffm', appBasePath('/ffm/views/project.php'), 'Thư mục gốc suy ra từ views/');
same('/ffm', appBasePath('/ffm/handle/export.php'), 'Thư mục gốc suy ra từ handle/');
same('', appBasePath('/index.php'), 'Thư mục gốc khi chạy ở gốc máy chủ');

section('Quy tắc bị quy tắc ưu tiên cao hơn lấn mất');
$staffRule = ['priority' => 1, 'type' => 'group', 'group' => 'staff', 'percentage' => 100, 'value' => true];
$gm = ['u1' => ['staff'], 'u2' => ['staff'], 'u3' => [], 'u4' => ['beta']];
$sh = ffmShadowedBy([$staffRule], ['u1', 'u2'], 'f', 'testing', $gm);
same(['listed' => 2, 'taken' => 2, 'by' => ['p1' => 2]], $sh, 'Cả danh sách thuộc nhóm staff 100%: không ai tới lượt quy tắc danh sách');
$sh = ffmShadowedBy([$staffRule], ['u1', 'u3', 'u4'], 'f', 'testing', $gm);
same([3, 1], [$sh['listed'], $sh['taken']], 'Chỉ người thuộc staff bị lấn, người ngoài nhóm vẫn tới lượt');
$sh = ffmShadowedBy([], ['u1', 'u2'], 'f', 'testing', $gm);
same(0, $sh['taken'], 'Quy tắc xét đầu tiên thì không ai bị lấn');
same(2, ffmShadowedBy([$staffRule], ['u1', 'u1', 'u2'], 'f', 'testing', $gm)['listed'], 'Mã lặp trong danh sách chỉ tính một lần');

finish();
