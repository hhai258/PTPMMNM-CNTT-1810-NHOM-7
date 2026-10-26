<?php
require_once __DIR__ . '/layout.php';
requireUser();
$envId = (int)($_GET['id'] ?? 0);
$env = getEnvironment($envId);
if (!$env) { abortWith(404, 'Không tìm thấy môi trường.'); }
[$project, $role] = loadProjectOrDie((int)$env['project_id']);
$canEdit = roleAtLeast($role, 'editor');
$versions = getVersions($envId);
$draft = getDraft($envId);
$activeId = (int)$env['active_version_id'];
$active = $activeId ? getVersion($activeId) : null;
$stats = $activeId ? ffmVersionStats($activeId) : null;
$changes = ffmChangeCounts($versions);
$maxChg = max(array_map('intval', array_filter($changes, fn($x) => $x !== null)) ?: [0]);
$decisions = getRecentDecisions($envId, 10);
renderHeader($env['name'], ['project' => $project, 'active' => 'env:' . $env['id']]);

$actions = '';
if ($canEdit) {
    $actions = $draft
        ? '<a class="btn btn-primary" href="version.php?id=' . (int)$draft['id'] . '">Mở bản nháp v' . (int)$draft['version_no'] . '</a>'
        : '<form method="POST" action="../handle/version_process.php" class="form-row" style="align-items:center">' . csrfField()
          . '<input type="hidden" name="environment_id" value="' . $envId . '"><input type="hidden" name="source_version_id" value="' . $activeId . '">'
          . '<input name="note" placeholder="Ghi chú bản nháp" maxlength="255" style="width:200px">'
          . '<button class="btn btn-primary" name="action" value="create_draft">' . ico('plus') . 'Tạo bản nháp</button></form>';
}
pageHead(e($env['name']), [['Dự án', 'projects.php'], [$project['name'], 'project.php?id=' . (int)$project['id']]],
    '<code>' . e($env['env_key']) . '</code> · ' . ($active ? 'đang phát hành v' . (int)$active['version_no'] . ', ' . e(ago($active['published_at'])) : 'chưa có bản nào được phát hành'), $actions);
echo envTabs($env, 'versions');
?>
<?php if ($active): ?>
<article class="panel release">
  <div class="release-main">
    <span class="muted">Bản hiện tại</span>
    <div class="big"><span class="ver-big">v<?= (int)$active['version_no'] ?></span><?= pill('Đang phát hành', 'live') ?></div>
    <span><?= e($active['note'] !== '' ? $active['note'] : 'Không có ghi chú') ?></span>
    <span class="muted">Phát hành <?= e(fmtTime($active['published_at'])) ?></span>
    <div style="margin-top:auto;padding-top:12px;display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn btn-ghost btn-sm" href="version.php?id=<?= $activeId ?>">Xem cấu hình</a>
      <a class="btn btn-ghost btn-sm" href="../handle/export.php?version_id=<?= $activeId ?>"><?= ico('download') ?>Tải JSON</a>
    </div>
  </div>
  <div class="release-side">
    <dl class="facts" style="--cols:3;margin:0">
      <div class="fact"><dt>Tính năng</dt><dd class="num"><?= nf($stats['flags']) ?></dd></div>
      <div class="fact"><dt>Bật mặc định</dt><dd class="num"><?= nf($stats['defaults_on']) ?> / <?= nf($stats['flags']) ?></dd></div>
      <div class="fact"><dt>Quy tắc</dt><dd class="num"><?= nf($stats['rules']) ?></dd></div>
    </dl>
    <dl class="facts" style="--cols:3;margin:0">
      <div class="fact"><dt>Số phiên bản</dt><dd class="num"><?= nf(count($versions)) ?></dd></div>
      <div class="fact"><dt>Quyết định đã trả</dt><dd class="num"><?= nf((int)(dbOne('SELECT COUNT(*) AS n FROM decision_logs WHERE environment_id = ?', 'i', [$envId])['n'] ?? 0)) ?></dd></div>
      <div class="fact"><dt>Bản nháp</dt><dd><?= $draft ? 'v' . (int)$draft['version_no'] : dash() ?></dd></div>
    </dl>
    <?php if ($canEdit): ?>
    <div class="keyline"><?= ico('key') ?><code><?= e($env['api_key']) ?></code>
      <button type="button" class="icon-btn" data-copy="<?= e($env['api_key']) ?>" aria-label="Sao chép API key"><?= ico('copy') ?></button></div>
    <?php endif; ?>
  </div>
</article>
<?php else: ?>
<div class="empty">Môi trường này chưa có bản cấu hình nào được phát hành. API sẽ trả mã 503 cho đến khi có bản đầu tiên.</div>
<?php endif; ?>

<section class="section">
  <div class="section-head"><h2>Phiên bản cấu hình</h2><span class="meta"><?= nf(count($versions)) ?> phiên bản · bản đã phát hành chỉ đọc</span></div>
  <div class="panel tbl-wrap"><table class="tbl">
    <thead><tr><th scope="col">Bản</th><th scope="col">Trạng thái</th><th scope="col">Ghi chú</th><th scope="col" class="num">Thay đổi</th><th scope="col" class="hide-sm">Người tạo</th><th scope="col" class="hide-sm">Thời điểm</th><th scope="col"></th></tr></thead>
    <tbody>
    <?php foreach ($versions as $v): $isActive = (int)$v['id'] === $activeId; ?>
      <tr>
        <td class="num-l"><a class="link" href="version.php?id=<?= (int)$v['id'] ?>">v<?= (int)$v['version_no'] ?></a></td>
        <td><?= $v['status'] === 'draft' ? pill('Bản nháp', 'draft') : ($isActive ? pill('Đang phát hành', 'live') : pill('Đã phát hành', 'neutral')) ?></td>
        <td><?= $v['note'] !== '' ? e($v['note']) : dash() ?></td>
        <td class="num"><?php if ($changes[$v['id']] === null) { echo dash(); } else { ?><span class="chg"><span class="cover-track" aria-hidden="true"><i style="width:<?= $maxChg ? round(100 * $changes[$v['id']] / $maxChg) : 0 ?>%"></i></span><span class="chg-num"><?= nf($changes[$v['id']]) ?></span></span><?php } ?></td>
        <td class="hide-sm"><?= e($v['username']) ?></td>
        <td class="hide-sm muted nowrap" title="<?= e(fmtTime($v['published_at'] ?? $v['created_at'])) ?>"><?= e(ago($v['published_at'] ?? $v['created_at'])) ?></td>
        <td class="right nowrap"><div class="row-actions">
          <a class="btn btn-ghost btn-sm" href="version.php?id=<?= (int)$v['id'] ?>"><?= $v['status'] === 'draft' && $canEdit ? 'Sửa' : 'Xem' ?></a>
          <?php if ($canEdit && $v['status'] === 'published'): ?>
          <details class="rowmenu" style="display:inline-block"><summary class="icon-btn" aria-label="Thao tác với v<?= (int)$v['version_no'] ?>"><?= ico('more') ?></summary>
            <div class="menu-panel">
              <form method="POST" action="../handle/version_process.php">
                <?= csrfField() ?><input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                <p>Khôi phục v<?= (int)$v['version_no'] ?></p>
                <input name="reason" placeholder="Lý do khôi phục" required maxlength="255" style="width:100%">
                <p class="lead-note">Tạo một bản phát hành mới là bản sao của v<?= (int)$v['version_no'] ?>; lịch sử giữ nguyên.</p>
                <button class="btn btn-primary btn-sm" style="margin-top:10px" name="action" value="restore" data-confirm="Khôi phục v<?= (int)$v['version_no'] ?> thành bản phát hành mới?">Khôi phục bản này</button>
              </form>
              <?php if (!$draft): ?>
              <form method="POST" action="../handle/version_process.php">
                <?= csrfField() ?><input type="hidden" name="environment_id" value="<?= $envId ?>"><input type="hidden" name="source_version_id" value="<?= (int)$v['id'] ?>">
                <p>Sửa từ bản này</p>
                <input name="note" placeholder="Ghi chú bản nháp" maxlength="255" style="width:100%">
                <button class="btn btn-ghost btn-sm" style="margin-top:10px" name="action" value="create_draft">Tạo bản nháp từ v<?= (int)$v['version_no'] ?></button>
              </form>
              <?php endif; ?>
            </div></details>
          <?php else: ?><span class="slot" aria-hidden="true"></span><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>

<div class="grid2 eq section">
  <section>
    <div class="section-head"><h2>So sánh hai phiên bản</h2></div>
    <div class="panel panel-pad">
      <?php $opts = function ($sel) use ($versions) { foreach ($versions as $v) { echo '<option value="' . (int)$v['id'] . '"' . ($v['id'] == $sel ? ' selected' : '') . '>v' . (int)$v['version_no'] . ($v['status'] === 'draft' ? ' (nháp)' : '') . '</option>'; } }; ?>
      <form method="GET" action="compare.php" class="form-row">
        <label class="field grow">Từ<select name="a"><?php $opts($versions[1]['id'] ?? $versions[0]['id'] ?? 0); ?></select></label>
        <label class="field grow">Đến<select name="b"><?php $opts($versions[0]['id'] ?? 0); ?></select></label>
        <button class="btn btn-ghost"><?= ico('arrow') ?>So sánh</button>
      </form>
      <div class="link-col" style="margin-top:14px">
        <?php if (count($versions) > 1): ?><a class="btn btn-text" href="compare.php?a=<?= (int)$versions[1]['id'] ?>&amp;b=<?= (int)$versions[0]['id'] ?>">Bản mới nhất so với bản liền trước</a><?php endif; ?>
        <?php if ($draft && $activeId): ?><a class="btn btn-text" href="compare.php?a=<?= $activeId ?>&amp;b=<?= (int)$draft['id'] ?>">Bản nháp so với bản đang chạy</a><?php endif; ?>
      </div>
      <p class="lead-note">Xem từng thay đổi về mặc định và quy tắc giữa hai bản, kèm số người bị ảnh hưởng.</p>
    </div>
  </section>
  <?php if ($canEdit): ?>
  <section>
    <div class="section-head"><h2>Nhập cấu hình từ JSON</h2></div>
    <div class="panel panel-pad">
      <form method="POST" action="../handle/version_process.php" enctype="multipart/form-data" style="display:grid;gap:12px">
        <?= csrfField() ?><input type="hidden" name="environment_id" value="<?= $envId ?>">
        <div class="filepick"><input type="file" id="import-file" class="sr-only" name="file" accept=".json,application/json"><label class="btn btn-ghost" for="import-file">Chọn tệp JSON</label><span class="muted" data-file-name>Chưa chọn tệp</span></div>
        <textarea name="json" rows="3" class="mono" placeholder='hoặc dán JSON: {"format":"ffm-config/1","flags":{...}}'></textarea>
        <div class="form-row"><input class="grow" name="reason" placeholder="Lý do nhập" maxlength="255"><button class="btn btn-ghost" name="action" value="import"><?= ico('upload') ?>Nhập vào bản nháp mới</button></div>
      </form>
      <p class="lead-note">Dữ liệu được kiểm tra toàn bộ trước khi ghi. Có lỗi thì không ghi gì.</p>
    </div>
  </section>
  <?php endif; ?>
</div>

<section class="section">
  <div class="section-head"><h2>Quyết định API gần đây</h2><span class="meta">10 lần gọi mới nhất của môi trường này</span></div>
  <?php if (!$decisions): ?><div class="empty">Chưa có lần gọi API nào. Mở ứng dụng thử nghiệm hoặc gọi <code>api/evaluate.php</code> để thấy quyết định ở đây.</div><?php else: ?>
  <div class="panel tbl-wrap"><table class="tbl">
    <thead><tr><th scope="col">Thời điểm</th><th scope="col">Người dùng</th><th scope="col">Tính năng</th><th scope="col">Kết quả</th><th scope="col">Quy tắc quyết định</th><th scope="col" class="num">Bản</th></tr></thead><tbody>
    <?php foreach ($decisions as $d): ?>
      <tr><td class="muted nowrap"><?= e(fmtTime($d['created_at'])) ?></td><td class="mono"><?= e($d['end_user_id']) ?></td><td class="mono"><?= e($d['flag_key']) ?></td>
      <td><?= badgeBool((bool)$d['enabled']) ?></td><td class="mono muted"><?= e($d['reason']) ?></td><td class="num">v<?= (int)$d['version_no'] ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</section>
<?php renderFooter();
