<?php
require_once __DIR__ . '/bootstrap.php';
$user = guardPost();
$action = $_POST['action'] ?? '';

if ($action === 'create_project') {
    $pid = 0;
    runAction(function () use ($user, &$pid) {
        $pid = createProject($_POST['name'] ?? '', $_POST['description'] ?? '', $user['id']);
    }, '../views/projects.php', 'Đã tạo dự án.');
}

// Các thao tác còn lại thuộc về một dự án: kiểm tra quyền sửa ở máy chủ
$projectId = postInt('project_id');
// Quản lý thành viên dự án chỉ dành cho quản trị viên; các thao tác khác cần quyền sửa
requireProjectRole($projectId, $action === 'add_member' ? 'admin' : 'editor');
$back = '../views/project.php?id=' . $projectId;

switch ($action) {
    case 'add_member':
        runAction(fn() => setMember($projectId, $_POST['username'] ?? '', $_POST['role'] ?? ''), $back, 'Đã cập nhật thành viên.');
        // no break needed: runAction luôn redirect
    case 'create_flag':
        runAction(fn() => createFlag($projectId, $_POST['flag_key'] ?? '', $_POST['name'] ?? '', $_POST['description'] ?? ''), $back, 'Đã khai báo tính năng.');
    case 'create_group':
        runAction(fn() => createGroup($projectId, $_POST['group_key'] ?? '', $_POST['name'] ?? ''), $back, 'Đã tạo nhóm.');
    case 'set_group_members':
        runAction(fn() => 'Đã lưu nhóm: ' . setGroupMembers($projectId, postInt('group_id'), $_POST['members'] ?? '') . ' thành viên.', $back, '');
    case 'add_group_members':
        // gộp người chọn trong danh sách và mã nhập thêm (mã mới sẽ được tạo thành người dùng giả)
        $picked = $_POST['members'] ?? [];
        $typed = $_POST['new_ids'] ?? '';
        runAction(function () use ($projectId, $picked, $typed) {
            $ids = array_merge(is_array($picked) ? $picked : parseEndUserIds($picked), parseEndUserIds($typed));
            $r = addGroupMembers($projectId, postInt('group_id'), $ids);
            return 'Đã thêm ' . $r['added'] . ' người vào nhóm'
                . ($r['already'] ? ', ' . $r['already'] . ' người đã có sẵn' : '')
                . ($r['created'] ? ', tạo mới ' . $r['created'] . ' mã người dùng giả' : '') . '.';
        }, $back, '');
    default:
        abortWith(400, 'Hành động không hợp lệ.');
}
