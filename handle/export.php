<?php
/** Tải cấu hình một phiên bản dưới dạng JSON (cần quyền xem). */
require_once __DIR__ . '/bootstrap.php';
if (!isLoggedIn()) {
    abortWith(401, 'Chưa đăng nhập.');
}
$v = getVersion((int)($_GET['version_id'] ?? 0));
if (!$v) {
    abortWith(404, 'Không tìm thấy phiên bản.');
}
requireProjectRole((int)$v['project_id'], 'viewer');
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="config_' . $v['env_key'] . '_v' . $v['version_no'] . '.json"');
echo exportVersionJson((int)$v['id']);
