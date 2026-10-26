<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../functions/directory_functions.php';
requireUser();
$projectId = (int)($_GET['id'] ?? 0);
[$project, $role] = loadProjectOrDie($projectId);
$envs = getEnvironments($projectId);

/* Phiên bản đang xem: mặc định là bản đang phát hành của môi trường đầu tiên (Thử nghiệm). */
$ver = null;
$versionId = (int)($_GET['version'] ?? 0);
if ($versionId) {
    $cand = getVersion($versionId);
    $ver = $cand && (int)$cand['project_id'] === $projectId ? $cand : null;
}
foreach ($envs as $env) {
    if (!$ver && !empty($env['active_version_id'])) { $ver = getVersion((int)$env['active_version_id']); }
}

$crumbs = [['Dự án', 'projects.php'], [$project['name'], 'project.php?id=' . $projectId]];
if (!$ver) {
    renderHeader('Người dùng giả', ['project' => $project, 'active' => 'directory']);
    pageHead('Người dùng giả', $crumbs);
    echo '<div class="empty">Dự án chưa có phiên bản nào để mô phỏng. Hãy tạo và phát hành một phiên bản ở môi trường trước.</div>';
    renderFooter();
    exit;
}

$cfg = loadVersionConfig((int)$ver['id']);
$flagKeys = array_keys($cfg['flags']);
sort($flagKeys);
$flagKey = (string)($_GET['flag'] ?? '');
if (!isset($cfg['flags'][$flagKey])) { $flagKey = $flagKeys[0] ?? ''; }
$groups = getGroups($projectId);
$gSel = (string)($_GET['group'] ?? '');
if ($gSel !== '_none' && !in_array($gSel, array_column($groups, 'group_key'), true)) { $gSel = ''; }
$rSel = in_array($_GET['result'] ?? '', ['on', 'off'], true) ? $_GET['result'] : '';
$reasonSel = (string)($_GET['reason'] ?? '');
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);

[$users, $groupMap] = ffmSimUsers($projectId);
$flagCfg = $cfg['flags'][$flagKey] ?? ['default' => false, 'rules' => []];
$all = ffmBuildDirectory($users, $groupMap, $flagCfg, $flagKey, $ver['env_key']);
$parts = ffmReasonParts($flagCfg);
$reasonCounts = array_count_values(array_column($all, 'reason'));
if (!isset($parts[$reasonSel])) { $reasonSel = ''; }
$onTotal = count(array_filter($all, fn($r) => $r['enabled']));
$groupCounts = ffmGroupCounts($users, $groupMap);
$rows = ffmFilterDirectory($all, $q, $gSel, $rSel, $reasonSel);
[$pageRows, $pages, $page] = ffmPaginate($rows, (int)($_GET['page'] ?? 1), 50);

$params = ['id' => $projectId, 'version' => (int)$ver['id'], 'flag' => $flagKey, 'group' => $gSel, 'result' => $rSel, 'reason' => $reasonSel, 'q' => $q];
$url = fn(array $over = []) => 'directory.php?' . http_build_query(array_filter(array_merge($params, $over), fn($v) => $v !== '' && $v !== null && $v !== 0));
$filtered = $q !== '' || $gSel !== '' || $rSel !== '' || $reasonSel !== '';

/* Quy tắc: sắp theo số ưu tiên, mặc định ở cuối; thanh mỏng theo số người */
uasort($parts, fn($a, $b) => ($a['priority'] ?? PHP_INT_MAX) <=> ($b['priority'] ?? PHP_INT_MAX));
$maxCount = max(1, ...array_values($reasonCounts ?: [1]));
$topReason = $reasonCounts ? array_search(max($reasonCounts), $reasonCounts, true) : 'default';

$userSel = (string)($_GET['user'] ?? '');
$detail = $userSel !== '' && in_array($userSel, $users, true) ? ffmUserDecisions($cfg, $ver['env_key'], $userSel, $groupMap[$userSel] ?? []) : null;

$total = count($users);
$sub = $total
    ? nf($onTotal) . ' / ' . nf($total) . ' người được bật ' . $flagKey . ' ở v' . (int)$ver['version_no'] . ' (' . $ver['env_key'] . '). Nhiều người nhất được quyết định bởi: '
        . ($parts[$topReason]['rule'] ?? 'mặc định') . ' (' . nf($reasonCounts[$topReason] ?? 0) . ').'
    : 'Dự án chưa có người dùng giả.';

renderHeader('Người dùng giả', ['project' => $project, 'active' => 'directory']);
pageHead('Người dùng giả', $crumbs, $sub);
?>
<form method="GET" action="directory.php" class="scopebar" data-autosubmit>
  <input type="hidden" name="id" value="<?= $projectId ?>">
  <?php foreach (['group' => $gSel, 'result' => $rSel, 'q' => $q] as $k => $v): if ($v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
  <label>Phiên bản
    <select name="version">
      <?php foreach ($envs as $env): ?><optgroup label="<?= e($env['name']) ?>">
        <?php foreach (getVersions((int)$env['id']) as $v): ?>
          <option value="<?= (int)$v['id'] ?>"<?= (int)$v['id'] === (int)$ver['id'] ? ' selected' : '' ?>>v<?= (int)$v['version_no'] ?><?= (int)$env['active_version_id'] === (int)$v['id'] ? ' · đang phát hành' : ($v['status'] === 'draft' ? ' · bản nháp' : '') ?><?= $v['note'] !== '' ? ' · ' . e(mb_strlen($v['note']) > 34 ? mb_substr($v['note'], 0, 33) . '…' : $v['note']) : '' ?></option>
        <?php endforeach; ?></optgroup>
      <?php endforeach; ?>
    </select></label>
  <label>Tính năng
    <select name="flag"><?php foreach ($flagKeys as $k): ?><option value="<?= e($k) ?>"<?= $k === $flagKey ? ' selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select></label>
  <noscript><button class="btn btn-primary btn-sm">Xem</button></noscript>
</form>

<div class="dir">
  <aside class="dir-side">
    <section class="panel">
      <div class="side-head"><h2>Quyết định bởi</h2><span class="meta">ưu tiên nhỏ xét trước</span></div>
      <ul class="rulelist">
        <?php foreach ($parts as $reason => $pt): $n = $reasonCounts[$reason] ?? 0; $active = $reasonSel === $reason; ?>
        <li><a href="<?= e($url(['reason' => $active ? '' : $reason, 'page' => 1, 'user' => ''])) ?>" class="<?= $active ? 'is-active' : '' ?>"<?= $active ? ' aria-current="true"' : '' ?>>
          <span class="rule-main"><?php if ($pt['priority'] !== null): ?><span class="rule-p mono">p<?= (int)$pt['priority'] ?></span><?php endif; ?><span class="rule-text"><?= e($pt['rule']) ?></span><span class="rule-val"><?= $pt['value'] ? 'bật' : 'tắt' ?></span></span>
          <span class="rule-n num"><?= nf($n) ?></span>
          <span class="rule-bar" aria-hidden="true"><i style="width:<?= round(100 * $n / $maxCount, 1) ?>%"></i></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </section>

    <?php if ($detail !== null): ?>
    <section class="panel">
      <div class="side-head"><h2><code><?= e($userSel) ?></code></h2><a class="link meta" href="<?= e($url(['user' => ''])) ?>">Đóng</a></div>
      <p class="side-note"><?= ($groupMap[$userSel] ?? []) ? 'Nhóm ' . e(implode(', ', $groupMap[$userSel])) : 'Không thuộc nhóm nào' ?></p>
      <table class="mini">
        <thead><tr><th scope="col">Tính năng</th><th scope="col">Kết quả</th><th scope="col" class="num">Ưu tiên</th></tr></thead>
        <tbody><?php foreach ($detail as $k => $d): ?>
          <tr<?= $k === $flagKey ? ' class="is-current"' : '' ?>><td><code><?= e($k) ?></code></td><td><span class="state <?= $d['enabled'] ? 'on' : 'off' ?>"><?= $d['enabled'] ? 'Bật' : 'Tắt' ?></span></td>
            <td class="num"><?= $d['priority'] !== null ? 'p' . (int)$d['priority'] : dash() ?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
    </section>
    <?php endif; ?>
  </aside>

  <section class="panel dir-main">
    <form method="GET" action="directory.php" class="toolbar" data-autosubmit>
      <?php foreach (['id' => $projectId, 'version' => (int)$ver['id'], 'flag' => $flagKey, 'reason' => $reasonSel] as $k => $v): if ($v !== '' && $v !== 0): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string)$v) ?>"><?php endif; endforeach; ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tìm mã người dùng" maxlength="60" aria-label="Tìm mã người dùng">
      <select name="group" aria-label="Nhóm">
        <option value="">Mọi nhóm</option>
        <option value="_none"<?= $gSel === '_none' ? ' selected' : '' ?>>Chưa thuộc nhóm (<?= nf($groupCounts['_none'] ?? 0) ?>)</option>
        <?php foreach ($groups as $g): ?><option value="<?= e($g['group_key']) ?>"<?= $gSel === $g['group_key'] ? ' selected' : '' ?>><?= e($g['group_key']) ?> (<?= nf($groupCounts[$g['group_key']] ?? 0) ?>)</option><?php endforeach; ?>
      </select>
      <select name="result" aria-label="Kết quả">
        <option value="">Bật và tắt</option><option value="on"<?= $rSel === 'on' ? ' selected' : '' ?>>Chỉ bật</option><option value="off"<?= $rSel === 'off' ? ' selected' : '' ?>>Chỉ tắt</option>
      </select>
      <?php if ($filtered): ?><a class="link" href="<?= e($url(['group' => '', 'result' => '', 'reason' => '', 'q' => '', 'page' => 1])) ?>">Xóa bộ lọc</a><?php endif; ?>
      <span class="toolbar-total num"><?= nf(count($rows)) ?> người</span>
    </form>

    <?php if (!$rows): ?>
      <div class="empty-in"><?php if (strncmp($reasonSel, 'rule:', 5) === 0 && !empty($reasonCounts) && empty($reasonCounts[$reasonSel])): ?>Quy tắc này không quyết định ai ở bản này. Những người khớp điều kiện đã bị quy tắc có ưu tiên cao hơn quyết định trước (mỗi người chỉ thuộc quy tắc khớp đầu tiên). <a class="link" href="version.php?id=<?= (int)$ver['id'] ?>&amp;flag=<?= (int)(dbOne('SELECT id FROM flags WHERE project_id = ? AND flag_key = ?', 'is', [$projectId, $flagKey])['id'] ?? 0) ?>">Xem thứ tự quy tắc</a>.<?php else: ?>Không có người dùng nào khớp bộ lọc.<?php endif; ?></div>
    <?php else: ?>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th scope="col">Người dùng</th><th scope="col" class="hide-sm">Nhóm</th><th scope="col">Kết quả</th><th scope="col" class="num">Ưu tiên</th><th scope="col">Quy tắc quyết định</th></tr></thead>
      <tbody>
      <?php foreach ($pageRows as $r): ?>
        <tr<?= $r['user'] === $userSel ? ' class="is-selected"' : '' ?>>
          <td><a class="id-link" href="<?= e($url(['user' => $r['user']])) ?>"><code><?= e($r['user']) ?></code></a></td>
          <td class="muted hide-sm"><?= $r['groups'] ? e(implode(' · ', $r['groups'])) : dash() ?></td>
          <td><span class="state <?= $r['enabled'] ? 'on' : 'off' ?>"><?= $r['enabled'] ? 'Bật' : 'Tắt' ?></span></td>
          <td class="num"><?= $r['priority'] !== null ? 'p' . (int)$r['priority'] : dash() ?></td>
          <td class="muted"><?= e($r['rule']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <nav class="pager" aria-label="Phân trang">
      <span class="num muted"><?= nf(($page - 1) * 50 + 1) ?>–<?= nf(min($page * 50, count($rows))) ?> / <?= nf(count($rows)) ?></span>
      <span class="pager-btns">
        <?php if ($page > 1): ?><a class="btn btn-ghost btn-sm" href="<?= e($url(['page' => $page - 1])) ?>">Trước</a><?php endif; ?>
        <?php if ($page < $pages): ?><a class="btn btn-ghost btn-sm" href="<?= e($url(['page' => $page + 1])) ?>">Sau</a><?php endif; ?>
      </span>
    </nav>
    <?php endif; ?>
  </section>
</div>
<?php renderFooter();
