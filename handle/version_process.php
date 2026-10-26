<?php
require_once __DIR__ . '/bootstrap.php';
$user = guardPost();
$action = $_POST['action'] ?? '';
$uid = $user['id'];

/**
 * Xác định môi trường/phiên bản từ CSDL (không tin project_id do client gửi),
 * rồi kiểm tra quyền sửa của người dùng trên dự án chứa nó.
 */
$versionId = postInt('version_id');
$envId = postInt('environment_id');
if ($versionId) {
    $v = getVersion($versionId);
    if (!$v) {
        abortWith(404, 'Không tìm thấy phiên bản.');
    }
    $envId = (int)$v['environment_id'];
    $projectId = (int)$v['project_id'];
} else {
    $env = getEnvironment($envId);
    if (!$env) {
        abortWith(404, 'Không tìm thấy môi trường.');
    }
    $projectId = (int)$env['project_id'];
}
requireProjectRole($projectId, 'editor');

$envPage = '../views/environment.php?id=' . $envId;
$verPage = '../views/version.php?id=' . $versionId . (postInt('flag_id') ? '&flag=' . postInt('flag_id') : '');   // giữ tính năng đang xem

switch ($action) {
    case 'create_draft':
        $src = postInt('source_version_id') ?: null;
        $newId = 0;
        runAction(function () use ($envId, $src, $uid, &$newId) {
            $newId = createDraft($envId, $src, $uid, trim($_POST['note'] ?? ''));
        }, $envPage, 'Đã tạo bản nháp.');
    case 'discard_draft':
        runAction(fn() => discardDraft($versionId, $uid), $envPage, 'Đã hủy bản nháp.');
    case 'set_default':
        runAction(fn() => setFlagDefault($versionId, postInt('flag_id'), ($_POST['enabled'] ?? '') === '1', $_POST['reason'] ?? '', $uid),
            $verPage, 'Đã cập nhật trạng thái tính năng.');
    case 'add_rule':
        runAction(fn() => addRule($versionId, postInt('flag_id'), $_POST, $uid), $verPage, 'Đã thêm quy tắc.');
    case 'delete_rule':
        runAction(fn() => deleteRule($versionId, postInt('rule_id'), $uid), $verPage, 'Đã xóa quy tắc.');
    case 'publish':
        runAction(fn() => publishVersion($versionId, $_POST['reason'] ?? '', $uid), $envPage, 'Đã phát hành phiên bản.');
    case 'restore':
        runAction(fn() => restoreVersion($versionId, $_POST['reason'] ?? '', $uid), $envPage, 'Đã khôi phục: tạo bản phát hành mới từ bản cũ.');
    case 'import':
        $json = '';
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $json = (string)file_get_contents($_FILES['file']['tmp_name']);
        } else {
            $json = (string)($_POST['json'] ?? '');
        }
        runAction(fn() => importConfigAsDraft($envId, $json, $uid, trim($_POST['reason'] ?? '')), $envPage, 'Đã nhập cấu hình vào bản nháp mới.');
    default:
        abortWith(400, 'Hành động không hợp lệ.');
}
