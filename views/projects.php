<?php
require_once __DIR__ . '/layout.php';
$user = requireUser();
$rows = ffmProjectSummaries($user['id']);
$totalFlags = array_sum(array_column($rows, 'flag_count'));
$totalDrafts = array_sum(array_column($rows, 'draft_count'));
$last = array_filter(array_column($rows, 'last_release'));
$lastRelease = $last ? max($last) : null;
$activity = ffmRecentActivity($user['id'], 8);
$labels = changeActionLabels(); $kinds = changeActionKinds();
$user = renderHeader('Dự án', ['active' => 'projects']);

pageHead('Dự án', [], 'Mỗi dự án có hai môi trường, các tính năng, nhóm người dùng và lịch sử phát hành riêng.',
    '<a class="btn btn-primary" href="#new-project" data-open-drawer="new-project">' . ico('plus') . 'Tạo dự án</a>');
?>
<div class="kpis" style="--cols:3">
  <div class="kpi"><div class="kpi-label">Dự án của bạn</div><div class="kpi-value num"><?= nf(count($rows)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Tính năng đã khai báo</div><div class="kpi-value num"><?= nf($totalFlags) ?></div></div>
  <div class="kpi"><div class="kpi-label">Bản nháp đang mở</div><div class="kpi-value num"><?= nf($totalDrafts) ?></div>
    <div class="kpi-note"><?= $lastRelease ? 'Phát hành gần nhất ' . e(ago($lastRelease)) : 'Chưa có bản phát hành' ?></div></div>
</div>

<section class="section">
  <div class="section-head"><h2>Tất cả dự án</h2><span class="meta"><?= nf(count($rows)) ?> dự án</span></div>
  <?php if (!$rows): ?>
    <div class="empty">Bạn chưa tham gia dự án nào. Tạo dự án đầu tiên bằng nút “Tạo dự án”: dự án có sẵn hai môi trường để bắt đầu khai báo tính năng.</div>
  <?php else: ?>
  <div class="panel tbl-wrap"><table class="tbl">
    <thead><tr><th scope="col">Dự án</th><th scope="col">Môi trường đang phát hành</th><th scope="col" class="num">Tính năng</th><th scope="col" class="num hide-sm">Thành viên</th><th scope="col" class="hide-sm">Hoạt động cuối</th><th scope="col">Quyền</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p):
        $envs = getEnvironments((int)$p['id']); ?>
      <tr>
        <td><div class="cell-id"><a href="project.php?id=<?= (int)$p['id'] ?>"><strong><?= e($p['name']) ?></strong></a><small><?= $p['description'] !== '' ? e($p['description']) : 'Chưa có mô tả' ?></small></div></td>
        <td class="nowrap">
          <?php foreach ($envs as $env): ?>
            <span><code><?= e($env['env_key']) ?></code> <?= $env['active_no'] ? 'v' . (int)$env['active_no'] : dash() ?></span><?= $env === end($envs) ? '' : '<span class="dash"> · </span>' ?>
          <?php endforeach; ?>
          <?php if ((int)$p['draft_count'] > 0): ?> <?= pill(nf($p['draft_count']) . ' bản nháp', 'draft') ?><?php endif; ?>
        </td>
        <td class="num"><?= nf($p['flag_count']) ?></td>
        <td class="num hide-sm"><?= nf($p['member_count']) ?></td>
        <td class="hide-sm muted nowrap"><?= e(ago($p['last_release'])) ?></td>
        <td><?= rolePill($p['role']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <details class="drawer" id="new-project" style="margin-top:12px"><summary><?= ico('plus') ?>Tạo dự án mới</summary>
  <div class="drawer-body"><form method="POST" action="../handle/project_process.php">
    <?= csrfField() ?><input type="hidden" name="action" value="create_project">
    <div class="form-row">
      <label class="field grow">Tên dự án<input name="name" required maxlength="100" placeholder="Ví dụ: Cửa hàng trực tuyến"></label>
      <label class="field grow">Mô tả <span class="hint">Không bắt buộc</span><input name="description" maxlength="255"></label>
      <button class="btn btn-primary">Tạo dự án</button>
    </div>
    <p class="lead-note">Dự án mới có sẵn môi trường Thử nghiệm và Vận hành mô phỏng, mỗi môi trường bắt đầu từ bản v1 rỗng.</p>
  </form></div></details>
</section>

<section class="section">
  <div class="section-head"><h2>Hoạt động gần đây</h2><span class="meta">Mọi dự án của bạn</span></div>
  <?php if (!$activity): ?><div class="empty">Chưa có hoạt động nào. Mọi lần tạo bản nháp, sửa và phát hành sẽ xuất hiện ở đây.</div><?php else: ?>
  <div class="panel tbl-wrap"><table class="tbl">
    <thead><tr><th scope="col">Thời điểm</th><th scope="col">Hành động</th><th scope="col">Dự án · môi trường</th><th scope="col" class="num">Bản</th><th scope="col">Người làm</th><th scope="col" class="hide-sm">Lý do</th></tr></thead><tbody>
    <?php foreach ($activity as $a): ?>
      <tr><td class="muted nowrap" title="<?= e(fmtTime($a['created_at'])) ?>"><?= e(ago($a['created_at'])) ?></td>
        <td><?= pill($labels[$a['action']] ?? $a['action'], $kinds[$a['action']] ?? 'neutral') ?></td>
        <td><a class="link" href="environment.php?id=<?= (int)$a['env_id'] ?>"><?= e($a['project_name']) ?> · <?= e($a['env_name']) ?></a></td>
        <td class="num"><?= $a['version_no'] ? '<a class="link" href="version.php?id=' . (int)$a['version_id'] . '">v' . (int)$a['version_no'] . '</a>' : dash() ?></td>
        <td><?= e($a['username']) ?></td><td class="hide-sm"><?= $a['reason'] !== '' ? e($a['reason']) : dash() ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</section>


<?php renderFooter();
