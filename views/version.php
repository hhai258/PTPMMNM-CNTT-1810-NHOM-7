<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../functions/directory_functions.php';
requireUser();
$vid = (int)($_GET['id'] ?? 0);
$v = getVersion($vid);
if (!$v) { abortWith(404, 'Không tìm thấy phiên bản.'); }
[$project, $role] = loadProjectOrDie((int)$v['project_id']);
$isDraft = $v['status'] === 'draft';
$canEdit = roleAtLeast($role, 'editor') && $isDraft;            // bản đã phát hành luôn chỉ đọc
$env = getEnvironment((int)$v['environment_id']);
$isActive = (int)$env['active_version_id'] === $vid;
$flags = getVersionFlagRows($vid);
$groups = getGroups((int)$project['id']);
$sim = simulateVersion($vid);
$total = $flags ? (int)($sim[$flags[0]['flag_key']]['total'] ?? 0) : 0;
$stats = ffmVersionStats($vid);
$prev = dbOne('SELECT id, version_no FROM versions WHERE environment_id = ? AND version_no = ?', 'ii', [(int)$v['environment_id'], (int)$v['version_no'] - 1]);
$changeCount = $prev ? count(compareVersions((int)$prev['id'], $vid)) : null;

$sel = null;
foreach ($flags as $f) { if ((int)$f['id'] === (int)($_GET['flag'] ?? 0)) { $sel = $f; } }
$sel = $sel ?? ($flags[0] ?? null);
$rules = $sel ? getRuleRows($vid, (int)$sel['id']) : [];
$bd = $sel ? ffmFlagBreakdown($vid, $sel['flag_key']) : null;
$cfgRules = $sel ? (loadVersionConfig($vid)['flags'][$sel['flag_key']]['rules'] ?? []) : [];   // cùng thứ tự với $rules (priority, id)
[$simUsers, $simGroupMap] = $sel ? ffmSimUsers((int)$project['id']) : [[], []];
$byReason = [];
if ($bd) { foreach ($bd['segments'] as $s) { $byReason[$s['reason']] = $s; } }

renderHeader('Phiên bản v' . (int)$v['version_no'], ['project' => $project, 'active' => 'env:' . $env['id']]);

$actions = '<a class="btn btn-ghost" href="../handle/export.php?version_id=' . $vid . '">' . ico('download') . 'Tải JSON</a>'
    . '<a class="btn btn-ghost" href="history.php?env=' . (int)$v['environment_id'] . '&amp;version=' . $vid . '">Lịch sử bản này</a>';
if ($canEdit) { $actions .= '<button type="button" class="btn btn-primary" data-open="publish-dialog">Phát hành…</button>'; }
$statusPill = $isDraft ? pill('Bản nháp', 'draft') : ($isActive ? pill('Đang phát hành', 'live') : pill('Đã phát hành · chỉ đọc', 'neutral'));
pageHead('v' . (int)$v['version_no'] . ' ' . $statusPill,
    [['Dự án', 'projects.php'], [$project['name'], 'project.php?id=' . (int)$project['id']], [$env['name'], 'environment.php?id=' . (int)$env['id']]],
    ($v['note'] !== '' ? e($v['note']) : 'Không có ghi chú') . ' · tạo ' . e(ago($v['created_at'])), $actions);

if (!$isDraft && roleAtLeast($role, 'editor')): ?>
<div class="notice notice-warn"><?= ico('lock') ?><span>Bản đã phát hành không sửa được. Muốn thay đổi, hãy tạo bản nháp mới từ bản này (mục “Thao tác” ở trang môi trường).</span></div>
<?php endif; ?>

<div class="kpis" style="--cols:4">
  <div class="kpi"><div class="kpi-label">Tính năng</div><div class="kpi-value num"><?= nf($stats['flags']) ?></div></div>
  <div class="kpi"><div class="kpi-label">Bật mặc định</div><div class="kpi-value num"><?= nf($stats['defaults_on']) ?><small>/ <?= nf($stats['flags']) ?></small></div></div>
  <div class="kpi"><div class="kpi-label">Quy tắc</div><div class="kpi-value num"><?= nf($stats['rules']) ?></div></div>
  <div class="kpi"><div class="kpi-label">Thay đổi so với v<?= $prev ? (int)$prev['version_no'] : '—' ?></div>
    <div class="kpi-value num"><?= $changeCount === null ? dash() : nf($changeCount) ?></div>
    <?php if ($prev && $changeCount): ?><div class="kpi-note"><a class="link" href="compare.php?a=<?= (int)$prev['id'] ?>&amp;b=<?= $vid ?>">Xem chi tiết</a></div><?php endif; ?></div>
</div>

<?php if (!$sel): ?>
  <div class="empty section">Bản này chưa có tính năng nào. Khai báo tính năng ở trang dự án rồi tạo bản nháp mới.</div>
<?php else: ?>
<div class="split section">
  <nav class="panel flaglist" aria-label="Tính năng trong bản này">
    <?php foreach ($flags as $f): $c = $sim[$f['flag_key']] ?? ['on' => 0, 'total' => 0]; $pc = $c['total'] ? 100 * $c['on'] / $c['total'] : 0; ?>
      <a href="version.php?id=<?= $vid ?>&amp;flag=<?= (int)$f['id'] ?>"<?= (int)$f['id'] === (int)$sel['id'] ? ' class="active" aria-current="true"' : '' ?>>
        <span class="k"><?= e($f['flag_key']) ?></span><span class="n"><?= e($f['name']) ?></span>
        <span class="p num"><?= nf($pc, 1) ?>%<small><?= $f['default_enabled'] ? 'mặc định bật' : 'mặc định tắt' ?></small></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <article class="panel">
    <div class="detail-head">
      <div style="min-width:0"><h2><?= e($sel['name']) ?> <code class="muted"><?= e($sel['flag_key']) ?></code></h2>
        <p class="sub">Mặc định khi không quy tắc nào khớp: <?= badgeBool((bool)$sel['default_enabled']) ?></p></div>
      <div class="right"><div class="big-figure num"><?= nf($bd ? ($bd['total'] ? 100 * $bd['on'] / $bd['total'] : 0) : 0, 1) ?>%</div>
        <div class="muted"><?= nf($bd['on'] ?? 0) ?> / <?= nf($bd['total'] ?? 0) ?> người dùng giả được bật</div></div>
    </div>

    <div class="block">
      <h3>Ai bị quy tắc nào quyết định</h3>
      <?php if ($bd && $bd['total']): ?>
      <div class="breakdown" role="img" aria-label="Phân rã quyết định theo quy tắc">
        <?php foreach ($bd['segments'] as $s): ?><span class="<?= $s['count'] ? ($s['enabled'] ? 'seg-on' : 'seg-off') : 'seg-zero' ?>" style="flex:<?= (int)$s['count'] ?> 1 0" title="<?= e($s['label']) ?>: <?= nf($s['count']) ?> người"></span><?php endforeach; ?>
      </div>
      <p class="lead-note"><span class="legend-dot on"></span>Được bật <span class="legend-dot off" style="margin-left:14px"></span>Bị tắt · mỗi người chỉ bị quy tắc khớp đầu tiên quyết định, không khớp thì dùng mặc định.</p>
      <?php else: ?><div class="empty">Dự án chưa có người dùng giả để mô phỏng.</div><?php endif; ?>
    </div>

    <div class="block" style="padding:0">
      <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th scope="col">Ưu tiên</th><th scope="col">Điều kiện</th><th scope="col">Kết quả</th><th scope="col" class="num">Quyết định cho</th><?php if ($canEdit): ?><th scope="col" class="act"></th><?php endif; ?></tr></thead>
        <tbody>
        <?php $seen = []; foreach ($rules as $ri => $r):
            $reason = 'rule:' . $r['rule_type'] . '#p' . (int)$r['priority'];
            $cnt = isset($seen[$reason]) ? null : ($byReason[$reason]['count'] ?? 0); $seen[$reason] = true; ?>
          <tr>
            <td class="num-l">p<?= (int)$r['priority'] ?></td>
            <td><?php if ($r['rule_type'] === 'user_list'): $us = array_filter(array_map('trim', explode(',', $r['target']))); ?>
                  Danh sách <?= nf(count($us)) ?> người <span class="mono muted"><?= e(implode(', ', array_slice($us, 0, 3))) ?><?= count($us) > 3 ? '…' : '' ?></span>
                  <?php $sh = ffmShadowedBy(array_slice($cfgRules, 0, $ri), $us, $sel['flag_key'], $env['env_key'], $simGroupMap);
                  if ($sh['taken'] > 0): $byTxt = implode(', ', array_map(fn($k, $n) => $k . ' (' . nf($n) . ')', array_keys($sh['by']), $sh['by'])); ?>
                  <p class="rule-warn"><?= $sh['taken'] === $sh['listed'] ? 'Không ai tới lượt quy tắc này: cả ' . nf($sh['listed']) . ' người đã bị' : nf($sh['taken']) . '/' . nf($sh['listed']) . ' người đã bị' ?> quy tắc ưu tiên cao hơn quyết định trước: <?= e($byTxt) ?>. Thêm lại quy tắc này với số ưu tiên nhỏ hơn để được xét trước.</p>
                  <?php endif; ?>
                <?php elseif ($r['rule_type'] === 'group'): ?>Nhóm <code><?= e($r['target']) ?></code> · <span class="num"><?= (int)$r['percentage'] ?>%</span>
                <?php else: ?>Tỷ lệ <span class="num"><?= (int)$r['percentage'] ?>%</span> mọi người dùng<?php endif; ?></td>
            <td><?= badgeBool((bool)$r['result_enabled']) ?></td>
            <td class="num"><?= $cnt === null ? dash() : nf($cnt) ?></td>
            <?php if ($canEdit): ?><td class="act"><form method="POST" action="../handle/version_process.php">
              <?= csrfField() ?><input type="hidden" name="version_id" value="<?= $vid ?>"><input type="hidden" name="flag_id" value="<?= (int)$sel['id'] ?>"><input type="hidden" name="rule_id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-ghost btn-sm" name="action" value="delete_rule" data-confirm="Xóa quy tắc p<?= (int)$r['priority'] ?> khỏi bản nháp?">Xóa</button></form></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
          <tr><td class="muted">mặc định</td><td class="muted">Không quy tắc nào khớp</td><td><?= badgeBool((bool)$sel['default_enabled']) ?></td>
            <td class="num"><?= nf($byReason['default']['count'] ?? 0) ?></td><?php if ($canEdit): ?><td></td><?php endif; ?></tr>
        </tbody></table></div>
    </div>

    <?php if ($canEdit): ?>
    <div class="block"><h3>Trạng thái mặc định</h3>
      <form method="POST" action="../handle/version_process.php" class="form-row">
        <?= csrfField() ?><input type="hidden" name="version_id" value="<?= $vid ?>"><input type="hidden" name="flag_id" value="<?= (int)$sel['id'] ?>">
        <div class="seg" role="radiogroup" aria-label="Mặc định">
          <label><input type="radio" name="enabled" value="1"<?= $sel['default_enabled'] ? ' checked' : '' ?>><span>Bật</span></label>
          <label><input type="radio" name="enabled" value="0"<?= $sel['default_enabled'] ? '' : ' checked' ?>><span>Tắt</span></label>
        </div>
        <input class="grow" name="reason" placeholder="Lý do đổi (bắt buộc)" required maxlength="255">
        <button class="btn btn-ghost" name="action" value="set_default">Lưu mặc định</button>
      </form></div>
    <div class="block">
      <details class="drawer"><summary><?= ico('plus') ?>Thêm quy tắc cho <?= e($sel['flag_key']) ?></summary><div class="drawer-body">
        <form method="POST" action="../handle/version_process.php" class="rule-fields" data-type="percentage" style="display:grid;gap:14px">
          <?= csrfField() ?><input type="hidden" name="version_id" value="<?= $vid ?>"><input type="hidden" name="flag_id" value="<?= (int)$sel['id'] ?>">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end">
            <label class="field">Ưu tiên<input name="priority" type="number" min="1" value="<?= count($rules) + 1 ?>" required aria-describedby="prio-note"></label>
            <label class="field">Loại quy tắc<select name="rule_type"><option value="percentage">Tỷ lệ %</option><option value="group">Nhóm</option><option value="user_list">Danh sách người dùng</option></select></label>
            <label class="field" data-for="group"><span>Nhóm</span><select name="group"><?php foreach ($groups as $g): ?><option value="<?= e($g['group_key']) ?>"><?= e($g['group_key']) ?> · <?= e($g['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field" data-for="pct">Tỷ lệ áp dụng (%)<input name="percentage" type="number" min="0" max="100" value="100"></label>
            <label class="field">Kết quả<select name="value"><option value="1">Bật</option><option value="0">Tắt</option></select></label>
          </div>
          <p class="lead-note" id="prio-note" style="margin:-4px 0 0">Ưu tiên: số nhỏ được xét trước; người dùng đầu tiên khớp một quy tắc sẽ nhận kết quả của quy tắc đó. Nếu số này đã có, các quy tắc từ số đó trở xuống được dồn xuống một bậc.</p>
          <p class="lead-note" data-for="user_list" style="margin:-4px 0 0">Danh sách người dùng cụ thể thường nên đặt ưu tiên <strong>1</strong> để thắng quy tắc theo nhóm và tỷ lệ.</p>
          <div class="users-tools" data-for="user_list">
            <button type="button" class="btn btn-ghost" data-rule-picker><?= ico('users') ?>Chọn nhiều người</button>
            <span class="muted" data-users-count aria-live="polite">Chưa chọn ai</span>
          </div>
          <label class="field" data-for="user_list">Mã người dùng <span class="hint">Tick trong danh sách bằng nút trên, hoặc gõ/dán mã cách nhau bằng dấu cách, phẩy hoặc xuống dòng</span><textarea name="users" rows="3" class="mono" placeholder="user0001 user0002"></textarea></label>
          <div class="form-row"><input class="grow" name="reason" placeholder="Lý do thêm quy tắc" maxlength="255"><button class="btn btn-primary" name="action" value="add_rule">Thêm quy tắc</button></div>
        </form></div></details>
    </div>
    <?php endif; ?>
  </article>
</div>
<?php endif; ?>

<?php if ($canEdit): ?>
<dialog id="publish-dialog" class="dialog">
  <form method="POST" action="../handle/version_process.php">
    <?= csrfField() ?><input type="hidden" name="version_id" value="<?= $vid ?>">
    <h2 style="margin-bottom:6px">Phát hành v<?= (int)$v['version_no'] ?></h2>
    <p class="muted" style="margin-bottom:14px">Bản này sẽ thành cấu hình đang chạy của <?= e($env['name']) ?> và không sửa được nữa. <?= $changeCount ? nf($changeCount) . ' thay đổi so với v' . (int)$prev['version_no'] . '.' : '' ?></p>
    <label class="field" style="margin-bottom:20px">Lý do phát hành<input name="reason" required maxlength="255" placeholder="Ví dụ: mở thanh toán mới cho nhóm beta"></label>
    <div class="dialog-actions" style="justify-content:space-between">
      <button class="btn btn-text" name="action" value="discard_draft" formnovalidate data-confirm="Hủy bản nháp này? Các thay đổi chưa phát hành sẽ mất.">Hủy bản nháp</button>
      <span style="display:flex;gap:8px"><button type="button" class="btn btn-ghost" data-close>Đóng</button><button class="btn btn-primary" name="action" value="publish">Phát hành</button></span>
    </div>
  </form>
</dialog>
<?php endif; ?>
<?php if ($canEdit && $sel): ?>
<dialog id="rule-picker" class="dialog dialog-wide" aria-labelledby="rule-picker-title">
  <h2 id="rule-picker-title" style="margin:0 0 4px">Chọn người dùng cho quy tắc</h2>
  <p class="lead-note" style="margin:0 0 14px">Tick những người sẽ nhận kết quả của quy tắc này. Mã gõ tay chưa có trong danh sách vẫn được giữ nguyên.</p>
  <div class="form-row" style="margin-bottom:10px">
    <label class="field grow">Tìm<input type="search" data-picker-search placeholder="Gõ một phần mã, ví dụ user01" autocomplete="off"></label>
    <label class="field">Lọc theo nhóm<select data-picker-filter><option value="">Mọi người</option><option value="_none">Chưa thuộc nhóm nào</option>
      <?php foreach ($groups as $g): ?><option value="<?= e($g['group_key']) ?>"><?= e($g['group_key']) ?></option><?php endforeach; ?></select></label>
  </div>
  <div class="picker-bar"><span data-picker-count class="muted num"></span>
    <span><button type="button" class="btn btn-text btn-sm" data-picker-all>Chọn tất cả đang hiển thị</button>
    <button type="button" class="btn btn-text btn-sm" data-picker-none>Bỏ chọn đang hiển thị</button></span></div>
  <div class="picker-list" data-picker-list role="group" aria-label="Danh sách người dùng giả"></div>
  <div class="dialog-actions" style="margin-top:16px"><button type="button" class="btn btn-ghost" data-close>Hủy</button><button type="button" class="btn btn-primary" data-rule-picker-apply>Dùng danh sách này</button></div>
</dialog>
<script type="application/json" id="rule-picker-data"><?= json_encode(ffmPickerData((int)$project['id']), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php renderFooter();
