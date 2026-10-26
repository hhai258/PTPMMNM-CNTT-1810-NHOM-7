<?php
require_once __DIR__ . '/../functions/auth.php';
require_once __DIR__ . '/../functions/project_functions.php';
require_once __DIR__ . '/../functions/version_functions.php';
require_once __DIR__ . '/../functions/decision_functions.php';
require_once __DIR__ . '/../functions/overview_functions.php';

/* ---------- Tiện ích hiển thị ---------- */

/** Bộ biểu tượng một nét 1,5px (outline), 16px. */
function ico(string $name, int $size = 16): string
{
    static $p = [
        'projects' => '<rect x="2.5" y="2.5" width="4.5" height="4.5" rx="1"/><rect x="9" y="2.5" width="4.5" height="4.5" rx="1"/><rect x="2.5" y="9" width="4.5" height="4.5" rx="1"/><rect x="9" y="9" width="4.5" height="4.5" rx="1"/>',
        'overview' => '<path d="M2.5 8 8 3l5.5 5"/><path d="M4 7v6h8V7"/>',
        'layers'   => '<path d="m8 2.5 5.5 3L8 8.5l-5.5-3z"/><path d="m2.5 8 5.5 3 5.5-3"/><path d="m2.5 10.5 5.5 3 5.5-3"/>',
        'compare'  => '<circle cx="4.5" cy="4" r="1.7"/><circle cx="11.5" cy="12" r="1.7"/><path d="M4.5 5.7v4.8a1.5 1.5 0 0 0 1.5 1.5h2.3M11.5 10.3V5.5A1.5 1.5 0 0 0 10 4H7.7"/>',
        'copy'     => '<rect x="5.5" y="5.5" width="8" height="8" rx="1.5"/><path d="M10.5 5.5v-1A1.5 1.5 0 0 0 9 3H4.5A1.5 1.5 0 0 0 3 4.5V9a1.5 1.5 0 0 0 1.5 1.5h1"/>',
        'plus'     => '<path d="M8 3.5v9M3.5 8h9"/>',
        'chevron'  => '<path d="m4 6 4 4 4-4"/>',
        'arrow'    => '<path d="M3 8h10M9 4l4 4-4 4"/>',
        'download' => '<path d="M8 2.5v8M4.5 7.5 8 11l3.5-3.5M3 13.5h10"/>',
        'upload'   => '<path d="M8 11V3M4.5 6 8 2.5 11.5 6M3 13.5h10"/>',
        'check'    => '<path d="m3.5 8.5 3 3 6-7"/>',
        'more'     => '<circle cx="3.5" cy="8" r=".9"/><circle cx="8" cy="8" r=".9"/><circle cx="12.5" cy="8" r=".9"/>',
        'menu'     => '<path d="M2.5 4.5h11M2.5 8h11M2.5 11.5h11"/>',
        'logout'   => '<path d="M6 2.5H4A1.5 1.5 0 0 0 2.5 4v8A1.5 1.5 0 0 0 4 13.5h2M10 5l3 3-3 3M13 8H6"/>',
        'key'      => '<circle cx="5.5" cy="10.5" r="2.5"/><path d="m7.4 8.6 5.1-5.1M10.5 5.5l1.5 1.5"/>',
        'users'    => '<circle cx="6" cy="5.5" r="2.2"/><path d="M2 13c0-2.2 1.8-3.8 4-3.8s4 1.6 4 3.8"/><path d="M10.5 3.5a2.2 2.2 0 0 1 0 4.2M12 9.4c1.2.5 2 1.6 2 3.1"/>',
        'edit'     => '<path d="M3 13h2.5L13 5.5 10.5 3 3 10.5z"/><path d="m9 4.5 2.5 2.5"/>',
        'lock'     => '<rect x="3" y="7" width="10" height="6.5" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/>',
    ];
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

/** Dấu hiệu sản phẩm: công tắc bật (thân rỗng, núm đặc) trên nền mực. */
function productMark(int $size = 28): string
{
    return '<svg class="mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 28 28" aria-hidden="true">'
        . '<rect width="28" height="28" rx="7" fill="#141414"/>'
        . '<rect x="6" y="9.5" width="16" height="9" rx="4.5" fill="none" stroke="#fff" stroke-width="1.6"/>'
        . '<circle cx="17.5" cy="14" r="2.6" fill="#fff"/></svg>';
}

/** Viên trạng thái: $kind = on | off | draft | live | neutral | warn | bad. */
function pill(string $text, string $kind = 'neutral'): string
{
    return '<span class="pill pill-' . e($kind) . '">' . e($text) . '</span>';
}

function badgeBool(bool $on): string
{
    return $on ? pill('Bật', 'on') : pill('Tắt', 'off');
}

function roleLabel(string $role): string
{
    return ['admin' => 'Quản trị', 'editor' => 'Được sửa'][$role] ?? 'Chỉ xem';
}

function rolePill(string $role): string
{
    return pill(roleLabel($role), ['admin' => 'ink', 'editor' => 'live'][$role] ?? 'neutral');
}

/** Số theo kiểu Việt Nam: 1.000 ; thập phân dùng dấu phẩy. */
function nf($n, int $dec = 0): string
{
    return number_format((float)$n, $dec, ',', '.');
}

function fmtTime(?string $ts): string
{
    return $ts ? date('d/m/Y H:i', strtotime($ts)) : '—';
}

/** Thời gian tương đối: "5 phút trước", "hôm qua"... */
function ago(?string $ts): string
{
    if (!$ts) {
        return '—';
    }
    $d = max(0, time() - strtotime($ts));
    if ($d < 60) return 'vừa xong';
    if ($d < 3600) return floor($d / 60) . ' phút trước';
    if ($d < 86400) return floor($d / 3600) . ' giờ trước';
    if ($d < 172800) return 'hôm qua';
    if ($d < 86400 * 30) return floor($d / 86400) . ' ngày trước';
    return fmtTime($ts);
}

/** Dấu "—" xám cho giá trị thiếu. */
function dash(): string
{
    return '<span class="dash">–</span>';
}

/* ---------- Khung trang ---------- */

/** Bắt buộc đăng nhập (chuyển về trang đăng nhập) và trả về người dùng hiện tại. */
function requireUser(): array
{
    checkLogin('../index.php');   // địa chỉ web tương đối với views/, không phải đường dẫn trên ổ đĩa
    return getCurrentUser();
}

/**
 * In phần đầu trang gồm thanh bên và vùng nội dung.
 * $ctx: 'project' => dòng dự án (hiện nhóm điều hướng của dự án), 'active' => 'projects' | 'overview' | 'env:<id>'.
 */
function renderHeader(string $title, array $ctx = []): array
{
    $user = requireUser();
    $error = $_SESSION['error'] ?? null;
    $success = $_SESSION['success'] ?? null;
    unset($_SESSION['error'], $_SESSION['success']);
    $project = $ctx['project'] ?? null;
    $active = $ctx['active'] ?? '';
    $projects = getProjectsForUser($user['id']);
    $envs = $project ? getEnvironments((int)$project['id']) : [];
    $initials = mb_strtoupper(mb_substr($user['full_name'] ?: $user['username'], 0, 1));
    $link = fn(string $href, string $icon, string $label, bool $on, string $extra = '') =>
        '<a href="' . e($href) . '"' . ($on ? ' class="active" aria-current="page"' : '') . '>' . ico($icon) . '<span>' . e($label) . '</span>' . $extra . '</a>';
    ?>
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Feature Flag Manager</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 28 28'%3E%3Crect width='28' height='28' rx='7' fill='%23141414'/%3E%3Crect x='6' y='9.5' width='16' height='9' rx='4.5' fill='none' stroke='%23fff' stroke-width='1.6'/%3E%3Ccircle cx='17.5' cy='14' r='2.6' fill='%23fff'/%3E%3C/svg%3E">
<link rel="preload" href="../css/fonts/Geist-Variable.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="../css/style.css">
<!-- impeccable-disable cramped-padding: bảng nằm trong khung và chữ cách viền 20px nhờ đệm của từng ô; bộ dò chỉ thấy bảng sát viền -->
</head>
<body>
<div class="app">
  <header class="topbar">
    <button type="button" class="icon-btn" data-nav-toggle aria-expanded="false" aria-controls="sidebar" aria-label="Mở menu"><?= ico('menu', 18) ?></button>
    <?= productMark(24) ?><strong class="topbar-title">Feature Flag Manager</strong>
  </header>
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="projects.php"><?= productMark() ?><span>Feature Flag Manager</span></a>

    <details class="scope menu">
      <summary><span class="scope-label">Phạm vi</span><span class="scope-name"><?= $project ? e($project['name']) : 'Tất cả dự án' ?></span><?= ico('chevron', 14) ?></summary>
      <div class="menu-panel">
        <?php foreach ($projects as $p): ?>
          <a href="project.php?id=<?= (int)$p['id'] ?>"<?= $project && (int)$project['id'] === (int)$p['id'] ? ' aria-current="true"' : '' ?>><?= e($p['name']) ?><small><?= e(roleLabel($p['role'])) ?></small></a>
        <?php endforeach; ?>
        <a href="projects.php" class="menu-foot">Tất cả dự án</a>
      </div>
    </details>

    <nav class="nav" aria-label="Điều hướng chính">
      <?= $link('projects.php', 'projects', 'Dự án', $active === 'projects') ?>
      <?php if ($project): ?>
        <p class="nav-label"><?= e($project['name']) ?></p>
        <?= $link('project.php?id=' . (int)$project['id'], 'overview', 'Tổng quan', $active === 'overview') ?>
        <?= $link('directory.php?id=' . (int)$project['id'], 'users', 'Người dùng giả', $active === 'directory') ?>
        <?php foreach ($envs as $env):
            $hasDraft = (bool)getDraft((int)$env['id']); ?>
          <?= $link('environment.php?id=' . (int)$env['id'], 'layers', $env['name'], $active === 'env:' . $env['id'],
              '<span class="nav-meta">' . ($hasDraft ? '<i class="dot" title="Có bản nháp" aria-label="Có bản nháp"></i>' : '') . ($env['active_no'] ? 'v' . (int)$env['active_no'] : '—') . '</span>') ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>

    <div class="side-foot">
      <div class="user">
        <span class="avatar" aria-hidden="true"><?= e($initials) ?></span>
        <span class="user-text"><strong><?= e($user['full_name'] ?: $user['username']) ?></strong><small><?= e($user['username']) ?></small></span>
        <a class="icon-btn" href="../handle/logout_process.php" title="Đăng xuất" aria-label="Đăng xuất"><?= ico('logout') ?></a>
      </div>
    </div>
  </aside>
  <div class="scrim" data-nav-toggle></div>
  <main class="canvas">
<?php if ($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="notice notice-ok" role="status"><?= ico('check') ?><?= e($success) ?></div><?php endif; ?>
<?php
    return $user;
}

function renderFooter(): void
{
    echo '  </main>
</div>
<dialog id="confirm-dialog" class="dialog"><form method="dialog"><p id="confirm-text"></p><div class="dialog-actions"><button class="btn btn-ghost" value="no">Hủy</button><button class="btn btn-primary" value="yes">Xác nhận</button></div></form></dialog>
<script src="../js/app.js"></script>
</body></html>';
}

/** Đầu trang: breadcrumb, tiêu đề h1, dòng phụ và vùng hành động bên phải. $title và $sub là HTML đã escape. */
function pageHead(string $title, array $crumbs = [], string $sub = '', string $actions = ''): void
{
    echo '<header class="page-head"><div class="page-head-main">';
    if ($crumbs) {
        echo '<nav class="crumbs" aria-label="Vị trí">';
        foreach ($crumbs as $i => [$label, $href]) {
            echo ($i ? '<span aria-hidden="true">/</span>' : '') . ($href ? '<a href="' . e($href) . '">' . e($label) . '</a>' : '<span>' . e($label) . '</span>');
        }
        echo '</nav>';
    }
    echo '<h1>' . $title . '</h1>' . ($sub !== '' ? '<p class="sub">' . $sub . '</p>' : '') . '</div>';
    echo $actions !== '' ? '<div class="page-actions">' . $actions . '</div>' : '';
    echo '</header>';
}

/** Tab dạng gạch chân của một môi trường. */
function envTabs(array $env, string $active): string
{
    $tabs = ['versions' => ['Phiên bản', 'environment.php?id=' . (int)$env['id']],
             'history' => ['Lịch sử thay đổi', 'history.php?env=' . (int)$env['id']]];
    $h = '<nav class="tabs" aria-label="Mục của môi trường">';
    foreach ($tabs as $k => [$label, $href]) {
        $h .= '<a href="' . e($href) . '"' . ($k === $active ? ' class="active" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    return $h . '</nav>';
}

/** Tải dự án + kiểm tra quyền xem; trả [project, role]. */
function loadProjectOrDie(int $projectId): array
{
    $role = requireProjectRole($projectId, 'viewer');
    $p = getProject($projectId);
    if (!$p) {
        abortWith(404, 'Không tìm thấy dự án.');
    }
    return [$p, $role];
}

/** Thanh phủ: phần được bật trên tổng số người dùng giả. */
function coverBar(int $on, int $total): string
{
    $pct = $total ? 100 * $on / $total : 0;
    return '<span class="cover" title="' . nf($on) . ' / ' . nf($total) . ' người dùng giả"><span class="cover-track"><i style="width:' . round($pct, 2) . '%"></i></span>'
        . '<span class="cover-num num">' . ($total ? nf($pct, 1) . '%' : '—') . '</span></span>';
}

/** Nhãn và màu viên trạng thái cho các hành động trong nhật ký thay đổi. */
function changeActionLabels(): array
{
    return ['create_draft' => 'Tạo bản nháp', 'discard_draft' => 'Hủy bản nháp', 'flag_on' => 'Bật tính năng', 'flag_off' => 'Tắt tính năng',
        'rule_add' => 'Thêm quy tắc', 'rule_delete' => 'Xóa quy tắc', 'publish' => 'Phát hành', 'restore' => 'Khôi phục', 'import' => 'Nhập JSON'];
}

function changeActionKinds(): array
{
    return ['publish' => 'live', 'restore' => 'live', 'create_draft' => 'draft', 'discard_draft' => 'neutral', 'flag_on' => 'on', 'flag_off' => 'off'];
}
