<?php
require_once __DIR__ . '/layout.php';
requireUser();
$env = getEnvironment((int)($_GET['env'] ?? 0));
if (!$env) { abortWith(404, 'Không tìm thấy môi trường.'); }
[$project] = loadProjectOrDie((int)$env['project_id']);
$vf = isset($_GET['version']) ? (int)$_GET['version'] : null;
$all = getChangeLogs((int)$env['id'], $vf ?: null);
$actions = changeActionLabels();
$kindOf = changeActionKinds();
$counts = array_count_values(array_column($all, 'action'));
$only = $_GET['action'] ?? '';
$logs = isset($actions[$only]) ? array_values(array_filter($all, fn($l) => $l['action'] === $only)) : $all;
$base = 'history.php?env=' . (int)$env['id'] . ($vf ? '&version=' . $vf : '');
$verNo = $vf ? (getVersion($vf)['version_no'] ?? null) : null;

/** Chi tiết JSON thành chuỗi "khóa: giá trị" dễ đọc. */
function detailText(?string $raw): string
{
    if ($raw === null || $raw === '') return '';
    $j = json_decode($raw, true);
    if (!is_array($j)) return $raw;
    $parts = [];
    foreach ($j as $k => $val) { $parts[] = $k . ': ' . (is_scalar($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE)); }
    return implode(' · ', $parts);
}

renderHeader('Lịch sử thay đổi', ['project' => $project, 'active' => 'env:' . $env['id']]);
pageHead('Lịch sử thay đổi', [['Dự án', 'projects.php'], [$project['name'], 'project.php?id=' . (int)$project['id']], [$env['name'], 'environment.php?id=' . (int)$env['id']]],
    'Mọi lần tạo, sửa, phát hành và khôi phục của ' . e($env['name']) . ', kèm lý do' . ($verNo ? ' · chỉ bản v' . (int)$verNo : '') . '.');
echo envTabs($env, 'history');
?>
<div class="section-head" style="flex-wrap:wrap;margin-top:0">
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <a class="btn btn-sm <?= $only === '' ? 'btn-secondary' : 'btn-ghost' ?>" href="<?= e($base) ?>">Tất cả <span class="num muted"><?= nf(count($all)) ?></span></a>
    <?php foreach ($actions as $k => $label): if (empty($counts[$k])) continue; ?>
      <a class="btn btn-sm <?= $only === $k ? 'btn-secondary' : 'btn-ghost' ?>" href="<?= e($base . '&action=' . $k) ?>"><?= e($label) ?> <span class="num muted"><?= nf($counts[$k]) ?></span></a>
    <?php endforeach; ?>
  </div>
  <span class="meta"><?= nf(count($logs)) ?> mục</span>
</div>
<?php if ($vf): ?><p style="margin:-4px 0 16px"><a class="link" href="history.php?env=<?= (int)$env['id'] ?>">Bỏ lọc theo phiên bản</a></p><?php endif; ?>

<?php if (!$logs): ?><div class="empty">Chưa có thay đổi nào khớp bộ lọc. Bỏ bộ lọc, hoặc tạo bản nháp ở trang môi trường để bắt đầu ghi lịch sử.</div><?php else: ?>
<div class="panel tbl-wrap"><table class="tbl">
  <thead><tr><th scope="col">Giờ</th><th scope="col">Bản</th><th scope="col">Hành động</th><th scope="col">Người làm</th><th scope="col">Lý do</th><th scope="col" class="hide-sm">Chi tiết</th></tr></thead><tbody>
  <?php $day = null; foreach ($logs as $l):
      $d = date('Y-m-d', strtotime($l['created_at']));
      if ($d !== $day): $day = $d; ?>
        <tr class="day-row"><td colspan="6"><?= e(date('d/m/Y', strtotime($l['created_at']))) ?> · <?= $d === date('Y-m-d') ? 'Hôm nay' : e(ago($l['created_at'])) ?></td></tr>
      <?php endif; ?>
    <tr><td class="num muted nowrap"><?= e(date('H:i:s', strtotime($l['created_at']))) ?></td>
      <td class="num-l"><?= $l['version_no'] ? '<a class="link" href="version.php?id=' . (int)$l['version_id'] . '">v' . (int)$l['version_no'] . '</a>' : dash() ?></td>
      <td><?= pill($actions[$l['action']] ?? $l['action'], $kindOf[$l['action']] ?? 'neutral') ?></td>
      <td><?= e($l['username']) ?></td>
      <td><?= $l['reason'] !== '' ? e($l['reason']) : dash() ?></td>
      <td class="hide-sm mono muted" style="max-width:420px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= e(detailText($l['detail'])) ?>"><?= e(detailText($l['detail'])) ?: dash() ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<?php renderFooter();
