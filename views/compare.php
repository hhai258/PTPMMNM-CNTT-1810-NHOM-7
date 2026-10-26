<?php
require_once __DIR__ . '/layout.php';
requireUser();
$a = getVersion((int)($_GET['a'] ?? 0));
$b = getVersion((int)($_GET['b'] ?? 0));
if (!$a || !$b || (int)$a['environment_id'] !== (int)$b['environment_id']) { abortWith(404, 'Hai phiên bản phải cùng một môi trường.'); }
[$project] = loadProjectOrDie((int)$a['project_id']);
$env = getEnvironment((int)$a['environment_id']);
$diff = compareVersions((int)$a['id'], (int)$b['id']);
$labels = ['added' => 'Thêm tính năng', 'removed' => 'Bỏ tính năng', 'default' => 'Mặc định', 'rule_added' => 'Thêm quy tắc', 'rule_removed' => 'Xóa quy tắc', 'rule_changed' => 'Sửa quy tắc'];
$byFlag = [];
foreach ($diff as $d) { $byFlag[$d['flag']][] = $d; }
$kinds = array_count_values(array_column($diff, 'kind'));
$simA = simulateVersion((int)$a['id']);
$simB = simulateVersion((int)$b['id']);
$impact = [];
foreach ($byFlag as $flag => $_) {
    $x = $simA[$flag]['on'] ?? null; $y = $simB[$flag]['on'] ?? null;
    $impact[$flag] = ['a' => $x, 'b' => $y, 'total' => $simB[$flag]['total'] ?? ($simA[$flag]['total'] ?? 0)];
}
$unchanged = array_values(array_diff(array_keys($simB), array_keys($byFlag)));
renderHeader('So sánh', ['project' => $project, 'active' => 'env:' . $env['id']]);
pageHead('So sánh v' . (int)$a['version_no'] . ' → v' . (int)$b['version_no'],
    [['Dự án', 'projects.php'], [$project['name'], 'project.php?id=' . (int)$project['id']], [$env['name'], 'environment.php?id=' . (int)$env['id']]],
    'Thay đổi cần chú ý khi chuyển từ bản cũ sang bản mới của ' . e($env['name']) . '.',
    '<a class="btn btn-ghost" href="environment.php?id=' . (int)$env['id'] . '">Về danh sách phiên bản</a>');
?>
<div class="kpis" style="--cols:4">
  <div class="kpi"><div class="kpi-label">Thay đổi</div><div class="kpi-value num"><?= nf(count($diff)) ?></div><div class="kpi-note">trên <?= nf(count($byFlag)) ?> tính năng</div></div>
  <div class="kpi"><div class="kpi-label">Tính năng thêm / bỏ</div><div class="kpi-value num"><?= nf(($kinds['added'] ?? 0) + ($kinds['removed'] ?? 0)) ?></div></div>
  <div class="kpi"><div class="kpi-label">Đổi mặc định</div><div class="kpi-value num"><?= nf($kinds['default'] ?? 0) ?></div></div>
  <div class="kpi"><div class="kpi-label">Quy tắc thêm / xóa / sửa</div><div class="kpi-value num"><?= nf(($kinds['rule_added'] ?? 0) + ($kinds['rule_removed'] ?? 0) + ($kinds['rule_changed'] ?? 0)) ?></div></div>
</div>

<?php if (!$diff): ?>
  <div class="empty section">Hai phiên bản giống nhau, không có gì thay đổi.</div>
<?php else: ?>
<section class="section">
  <div class="section-head"><h2>Ảnh hưởng lên người dùng giả</h2><span class="meta">Số người được bật trước và sau, chỉ các tính năng có thay đổi</span></div>
  <div class="panel tbl-wrap"><table class="tbl">
    <thead><tr><th scope="col">Tính năng</th><th scope="col" class="num">v<?= (int)$a['version_no'] ?></th><th scope="col" class="num">v<?= (int)$b['version_no'] ?></th><th scope="col" class="num">Chênh lệch</th><th scope="col" class="num">Mức phủ sau thay đổi</th></tr></thead><tbody>
    <?php foreach ($impact as $flag => $m):
        $delta = ($m['a'] === null || $m['b'] === null) ? null : $m['b'] - $m['a']; ?>
      <tr><td><code><?= e($flag) ?></code></td>
        <td class="num"><?= $m['a'] === null ? dash() : nf($m['a']) ?></td><td class="num"><?= $m['b'] === null ? dash() : nf($m['b']) ?></td>
        <td class="num"><?= $delta === null ? dash() : ($delta === 0 ? '<span class="muted">0</span>' : ($delta > 0 ? '+' : '−') . nf(abs($delta))) ?></td>
        <td class="num"><?= $m['b'] === null ? dash() : coverBar((int)$m['b'], (int)$m['total']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</section>

<section class="section">
  <div class="section-head"><h2>Từng thay đổi</h2><span class="meta"><?= nf(count($diff)) ?> thay đổi</span></div>
  <div class="panel">
  <?php foreach ($byFlag as $flag => $items): ?>
    <div class="diff-flag"><div><code><?= e($flag) ?></code></div>
      <ul><?php foreach ($items as $d): ?><li><?= pill($labels[$d['kind']], $d['kind'] === 'default' ? 'warn' : 'neutral') ?><span><?= e(preg_replace('/^(Sửa|Xóa|Thêm) quy tắc: /u', '', $d['text'])) ?></span></li><?php endforeach; ?></ul></div>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($unchanged): ?>
<section class="section">
  <div class="section-head"><h2>Không thay đổi</h2><span class="meta"><?= nf(count($unchanged)) ?> tính năng giữ nguyên cấu hình</span></div>
  <div class="panel tbl-wrap"><table class="tbl">
    <thead><tr><th scope="col">Tính năng</th><th scope="col" class="num">Mức phủ ở v<?= (int)$b['version_no'] ?></th></tr></thead><tbody>
    <?php foreach ($unchanged as $flag): $c = $simB[$flag]; ?>
      <tr><td><code><?= e($flag) ?></code></td><td class="num"><?= coverBar((int)$c['on'], (int)$c['total']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
</section>
<?php endif; ?>
<?php renderFooter();
