<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../functions/directory_functions.php';
requireUser();
$projectId = (int)($_GET['id'] ?? 0);
[$project, $role] = loadProjectOrDie($projectId);
$canEdit = roleAtLeast($role, 'editor');        // sửa cấu hình, tính năng, nhóm
$isAdmin = roleAtLeast($role, 'admin');         // thêm / đổi quyền thành viên dự án
$envs = getEnvironments($projectId);
$flags = getFlags($projectId);
$groups = getGroups($projectId);
$members = getMembers($projectId);
$coverage = ffmCoverageByEnvironment($envs);
$userCount = (int)(dbOne('SELECT COUNT(*) AS n FROM end_users WHERE project_id = ?', 'i', [$projectId])['n'] ?? 0);
$drafts = [];
foreach ($envs as $env) { if ($d = getDraft((int)$env['id'])) { $drafts[$env['id']] = $d; } }
renderHeader($project['name'], ['project' => $project, 'active' => 'overview']);

$actions = '';
if ($canEdit) {
    if ($drafts) {
        $eid = array_key_first($drafts);
        $actions = '<a class="btn btn-primary" href="version.php?id=' . (int)$drafts[$eid]['id'] . '">Mở bản nháp v' . (int)$drafts[$eid]['version_no'] . '</a>';
    } elseif ($envs) {
        $e0 = $envs[0];
        $actions = '<form method="POST" action="../handle/version_process.php">' . csrfField()
            . '<input type="hidden" name="environment_id" value="' . (int)$e0['id'] . '"><input type="hidden" name="source_version_id" value="' . (int)$e0['active_version_id'] . '">'
            . '<button class="btn btn-primary" name="action" value="create_draft">' . ico('plus') . 'Tạo bản nháp · ' . e($e0['name']) . '</button></form>';
    }
}
pageHead(e($project['name']) . ' ' . rolePill($role), [['Dự án', 'projects.php']], '', $actions);
?>
<section class="section">
  <div class="section-head"><h2>Môi trường</h2><span class="meta">Mỗi môi trường có phiên bản cấu hình và API key riêng</span></div>
  <div class="grid2">
  <?php foreach ($envs as $env):
      $st = $env['active_version_id'] ? ffmVersionStats((int)$env['active_version_id']) : null;
      $av = $env['active_version_id'] ? getVersion((int)$env['active_version_id']) : null; ?>
    <article class="panel">
      <div class="panel-head">
        <h2><a href="environment.php?id=<?= (int)$env['id'] ?>"><?= e($env['name']) ?></a> <code class="muted"><?= e($env['env_key']) ?></code></h2>
        <?= isset($drafts[$env['id']]) ? pill('Có bản nháp v' . (int)$drafts[$env['id']]['version_no'], 'draft') : ($av ? pill('Đang phát hành', 'live') : pill('Chưa phát hành', 'neutral')) ?>
      </div>
      <dl class="facts" style="--cols:3;margin:0">
        <div class="fact"><dt>Bản phát hành</dt><dd class="num"><?= $av ? 'v' . (int)$av['version_no'] : dash() ?></dd></div>
        <div class="fact"><dt>Bật mặc định</dt><dd class="num"><?= $st ? nf($st['defaults_on']) . ' / ' . nf($st['flags']) : dash() ?></dd></div>
        <div class="fact"><dt>Quy tắc</dt><dd class="num"><?= $st ? nf($st['rules']) : dash() ?></dd></div>
      </dl>
      <?php if ($av): ?><div class="block release-note"><?= e($av['note'] !== '' ? $av['note'] : 'Không có ghi chú') ?> · <?= e(ago($av['published_at'])) ?></div><?php endif; ?>
      <?php if ($canEdit): ?>
      <div class="keyline"><?= ico('key') ?><code><?= e($env['api_key']) ?></code>
        <button type="button" class="icon-btn" data-copy="<?= e($env['api_key']) ?>" aria-label="Sao chép API key"><?= ico('copy') ?></button></div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<div class="kpis" style="--cols:4">
  <div class="kpi"><div class="kpi-label">Tính năng</div><div class="kpi-value num"><?= nf(count($flags)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Nhóm người dùng</div><div class="kpi-value num"><?= nf(count($groups)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Người dùng giả để mô phỏng</div><div class="kpi-value num"><?= nf($userCount) ?></div></div>
  <div class="kpi"><div class="kpi-label">Thành viên dự án</div><div class="kpi-value num"><?= nf(count($members)) ?></div></div>
</div>

<section class="section">
  <div class="section-head"><h2>Tính năng</h2><span class="meta">Mức phủ trên <?= nf($userCount) ?> người dùng giả, theo bản đang phát hành</span></div>
  <?php if (!$flags): ?><div class="empty">Chưa có tính năng nào. Khai báo tính năng đầu tiên ở khung bên dưới để thêm quy tắc cho nó.</div><?php else: ?>
  <div class="panel tbl-wrap"><table class="tbl">
    <thead><tr><th scope="col">Tính năng</th><?php foreach ($envs as $env): ?><th scope="col" class="num"><?= e($env['name']) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($flags as $f): ?>
      <tr><td><div class="cell-id"><code><?= e($f['flag_key']) ?></code><small><?= e($f['name']) ?></small></div></td>
      <?php foreach ($envs as $env): $c = $coverage[$env['id']][$f['flag_key']] ?? null; ?>
        <td class="num"><?= $c ? coverBar((int)$c['on'], (int)$c['total']) : dash() ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <?php if ($canEdit): ?>
  <details class="drawer" style="margin-top:12px"><summary><?= ico('plus') ?>Khai báo tính năng</summary><div class="drawer-body">
    <form method="POST" action="../handle/project_process.php" class="form-row">
      <?= csrfField() ?><input type="hidden" name="action" value="create_flag"><input type="hidden" name="project_id" value="<?= $projectId ?>">
      <label class="field grow">Mã tính năng<input name="flag_key" placeholder="ma_tinh_nang" required pattern="[a-z][a-z0-9_]{1,59}" title="Chữ thường, số, gạch dưới; bắt đầu bằng chữ"></label>
      <label class="field grow">Tên hiển thị<input name="name" required></label>
      <button class="btn btn-primary">Khai báo</button>
    </form></div></details>
  <?php endif; ?>
</section>

<div class="grid2 section">
  <section>
    <div class="section-head"><h2>Nhóm người dùng</h2><span class="meta"><?= nf(count($groups)) ?> nhóm</span></div>
    <?php if (!$groups): ?><div class="empty">Chưa có nhóm nào. Nhóm cho phép quy tắc nhắm vào một tập người dùng, ví dụ nhóm thử nghiệm.</div><?php else: ?>
    <div class="panel tbl-wrap"><table class="tbl">
      <thead><tr><th scope="col">Nhóm</th><th scope="col" class="num">Số người</th><th scope="col"></th></tr></thead><tbody>
      <?php foreach ($groups as $g): ?>
        <tr><td><div class="cell-id"><strong><?= e($g['name']) ?></strong><small class="mono"><?= e($g['group_key']) ?></small></div></td>
        <td class="num"><?= nf($g['member_count']) ?></td>
        <td class="act"><div class="act-links">
          <a href="directory.php?id=<?= $projectId ?>&amp;group=<?= e($g['group_key']) ?>" title="Xem danh sách người trong nhóm <?= e($g['name']) ?>">Xem</a>
          <?php if ($canEdit): ?>
          <button type="button" data-picker="add" data-group="<?= (int)$g['id'] ?>" title="Thêm thành viên vào nhóm <?= e($g['name']) ?>">Thêm người</button>
          <button type="button" data-picker="set" data-group="<?= (int)$g['id'] ?>" title="Sửa danh sách thành viên nhóm <?= e($g['name']) ?>">Sửa</button>
          <?php endif; ?></div></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <details class="drawer" style="margin-top:12px"><summary><?= ico('plus') ?>Tạo nhóm</summary><div class="drawer-body">
      <form method="POST" action="../handle/project_process.php" class="form-row">
        <?= csrfField() ?><input type="hidden" name="action" value="create_group"><input type="hidden" name="project_id" value="<?= $projectId ?>">
        <label class="field grow">Mã nhóm<input name="group_key" placeholder="ma_nhom" required></label>
        <label class="field grow">Tên nhóm<input name="name" required></label>
        <button class="btn btn-primary">Tạo nhóm</button></form></div></details>
    <?php endif; ?>
  </section>

  <section>
    <div class="section-head"><h2>Thành viên</h2><span class="meta"><?= nf(count($members)) ?> người</span></div>
    <div class="panel tbl-wrap"><table class="tbl">
      <thead><tr><th scope="col">Tài khoản</th><th scope="col">Quyền</th></tr></thead><tbody>
      <?php foreach ($members as $m): ?>
        <tr><td><div class="cell-id"><strong><?= e($m['full_name']) ?></strong><small class="mono"><?= e($m['username']) ?></small></div></td><td><?= rolePill($m['role']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php if ($isAdmin): $roleOf = array_column($members, 'role', 'username'); ?>
    <details class="drawer" style="margin-top:12px"><summary><?= ico('plus') ?>Thêm hoặc đổi quyền thành viên</summary><div class="drawer-body">
      <form method="POST" action="../handle/project_process.php" class="form-row">
        <?= csrfField() ?><input type="hidden" name="action" value="add_member"><input type="hidden" name="project_id" value="<?= $projectId ?>">
        <label class="field grow">Tài khoản
          <select name="username" required><?php foreach (ffmAllAccounts() as $a): $cur = $roleOf[$a['username']] ?? null; ?>
            <option value="<?= e($a['username']) ?>"><?= e($a['username']) ?> · <?= e($a['full_name']) ?><?= $cur ? ' (hiện: ' . e(roleLabel($cur)) . ')' : '' ?></option>
          <?php endforeach; ?></select></label>
        <label class="field">Quyền<select name="role"><option value="viewer">Chỉ xem</option><option value="editor">Được sửa cấu hình</option><option value="admin">Quản trị (cả quản lý thành viên)</option></select></label>
        <button class="btn btn-primary">Lưu</button></form></div></details>
    <?php elseif ($canEdit): ?>
    <p class="lead-note"><?= ico('lock', 14) ?> Chỉ quản trị viên của dự án mới thêm hoặc đổi quyền thành viên.</p>
    <?php endif; ?>
  </section>
</div>
<?php if ($canEdit && $groups): ?>
<dialog id="picker" class="dialog dialog-wide">
  <form method="POST" action="../handle/project_process.php" data-picker-form>
    <?= csrfField() ?><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="group_id" value="">
    <input type="hidden" name="action" value=""><input type="hidden" name="members" value="">
    <h2 data-picker-title style="margin:0 0 4px"></h2>
    <p class="lead-note" data-picker-note style="margin:0 0 14px"></p>
    <div class="form-row" style="margin-bottom:10px">
      <label class="field grow">Tìm<input type="search" data-picker-search placeholder="Gõ một phần mã, ví dụ user01" autocomplete="off"></label>
      <label class="field">Lọc theo nhóm<select data-picker-filter><option value="">Mọi người</option><option value="_none">Chưa thuộc nhóm nào</option>
        <?php foreach ($groups as $g): ?><option value="<?= e($g['group_key']) ?>"><?= e($g['group_key']) ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="picker-bar"><span data-picker-count class="muted num"></span>
      <span><button type="button" class="btn btn-text btn-sm" data-picker-all>Chọn tất cả đang hiển thị</button>
      <button type="button" class="btn btn-text btn-sm" data-picker-none>Bỏ chọn đang hiển thị</button></span></div>
    <div class="picker-list" data-picker-list role="group" aria-label="Danh sách người dùng giả"></div>
    <label class="field" data-picker-new style="margin-top:12px">Thêm mã mới <span class="hint">Mã chưa có sẽ được tạo thành người dùng giả. Cách nhau bằng dấu cách, phẩy hoặc xuống dòng.</span>
      <textarea name="new_ids" rows="2" class="mono" placeholder="user1001 user1002"></textarea></label>
    <div class="dialog-actions" style="margin-top:16px"><button type="button" class="btn btn-ghost" data-close>Hủy</button><button class="btn btn-primary" data-picker-submit>Lưu</button></div>
  </form>
</dialog>
<script type="application/json" id="picker-data"><?= json_encode(ffmPickerData($projectId), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php renderFooter();
