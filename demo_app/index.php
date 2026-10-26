<?php
require_once __DIR__ . '/client.php';
$user = trim($_GET['user'] ?? 'user0001');
if ($user === '' || strlen($user) > 60 || !preg_match('/^[A-Za-z0-9_.@-]+$/', $user)) {
    $user = 'user0001';
}
$r = ffmFetchFlags($user);
$f = $r['flags'];
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$names = ['new_checkout' => 'Thanh toán mới', 'dark_mode' => 'Giao diện tối', 'product_reviews' => 'Đánh giá sản phẩm', 'fast_search' => 'Tìm kiếm nhanh',
    'wishlist' => 'Danh sách yêu thích', 'live_chat' => 'Chat trực tuyến', 'promo_banner' => 'Biểu ngữ khuyến mãi', 'one_click_buy' => 'Mua một chạm',
    'recommendations' => 'Gợi ý cho bạn', 'beta_dashboard' => 'Bảng điều khiển beta'];
$products = [['Ấm đun nước thủy tinh', 349000, 4.6], ['Bộ dao bếp thép không gỉ', 790000, 4.8], ['Máy xay cà phê cầm tay', 560000, 4.4]];
?>
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cửa hàng demo (ứng dụng thử nghiệm)</title>
<style>
@font-face{font-family:"Geist";src:url(fonts/Geist-Variable.woff2) format("woff2");font-weight:100 900;font-display:swap}
@font-face{font-family:"Geist Mono";src:url(fonts/GeistMono-Variable.woff2) format("woff2");font-weight:100 900;font-display:swap}
:root{--bg:#f2f4f7;--surface:#fff;--text:#0a0a0a;--muted:#5f6672;--border:#e2e5ea;--ink:#141414;--on-ink:#fff;--data:#1d5fbf}
<?php if ($f['dark_mode']): ?>:root{--bg:#101215;--surface:#181b20;--text:#f3f4f6;--muted:#9aa1ad;--border:#2a2f37;--ink:#f3f4f6;--on-ink:#101215;--data:#6aa2f0}<?php endif; ?>
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:14px/20px "Geist",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:1120px;margin:0 auto;padding:24px 20px 64px}
header.bar{display:flex;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap}
header.bar h1{font-size:20px;margin:0;font-weight:600;letter-spacing:-.01em;flex:1}
.search{display:flex;align-items:center;gap:8px;height:36px;padding:0 12px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--muted);min-width:240px}
.btn{display:inline-flex;align-items:center;height:36px;padding:0 14px;border-radius:6px;border:1px solid transparent;font-family:inherit;font-weight:500;font-size:14px;background:var(--ink);color:var(--on-ink);cursor:pointer}
.btn.ghost{background:transparent;color:var(--text);border-color:var(--border)}
.layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;align-items:start}
.main{display:flex;flex-direction:column;gap:16px}
.side{padding:4px 0}
.panel{background:var(--surface);border:1px solid var(--border);border-radius:8px}
.pad{padding:16px 20px}
.note{padding:10px 14px;border-radius:6px;margin-bottom:16px;border:1px solid var(--border);background:var(--surface)}
.note.warn{background:#fffbeb;color:#92400e;border-color:#fde68a}.note.ok{color:var(--text)}
.promo{padding:12px 16px;border-radius:6px;margin-bottom:16px;background:var(--ink);color:var(--on-ink);font-weight:500}
.products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.product{padding:16px;display:flex;flex-direction:column;gap:8px}
.thumb{height:96px;border-radius:6px;background:var(--bg);border:1px solid var(--border);display:grid;place-items:center;font-size:32px;font-weight:500;color:var(--muted)}
.price{font-weight:600;font-variant-numeric:tabular-nums}.muted{color:var(--muted)}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
h2{font-size:14px;margin:0 0 12px;font-weight:600}
dl{margin:0}.flag{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-top:1px solid var(--border)}.flag:first-of-type{border-top:0}
.flag code{font:12px "Geist Mono",ui-monospace,Consolas,monospace;color:var(--muted)}
.pill{display:inline-flex;height:20px;align-items:center;padding:0 8px;border-radius:4px;font-size:12px;font-weight:600}
.pill.on{background:#dcfae5;color:#04250e}.pill.off{background:#eceef2;color:#3d424b}
<?php if ($f['dark_mode']): ?>.pill.on{background:#12351f;color:#9be7b2}.pill.off{background:#262a31;color:#b8bec9}<?php endif; ?>
.chat{position:fixed;right:20px;bottom:20px;height:44px;padding:0 18px;border-radius:22px;background:var(--ink);color:var(--on-ink);display:flex;align-items:center;font-weight:500;box-shadow:0 6px 20px rgba(0,0,0,.2)}
form.user{display:flex;gap:8px}form.user input{flex:1;height:36px;padding:0 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text);font:inherit;min-width:0}
@media(max-width:860px){.layout{grid-template-columns:1fr}.products{grid-template-columns:1fr}}
</style></head><body><div class="wrap">
<header class="bar"><h1>Cửa hàng demo</h1>
  <div class="search"><?= $f['fast_search'] ? 'Tìm kiếm nhanh đang bật' : 'Tìm kiếm cơ bản' ?></div>
  <?php if ($f['beta_dashboard']): ?><a class="btn ghost" href="#">Bảng điều khiển beta</a><?php endif; ?></header>

<?php if ($r['source'] === 'api'): ?>
  <p class="note ok">Cấu hình lấy từ API (phiên bản v<?= h($r['version']) ?>).</p>
<?php else: ?>
  <p class="note warn">Không lấy được cấu hình (<?= h($r['error']) ?>). Đang dùng giá trị dự phòng; ứng dụng vẫn hoạt động.</p>
<?php endif; ?>
<?php if ($f['promo_banner']): ?><div class="promo">Khuyến mãi cuối tuần: giảm 20%!</div><?php endif; ?>

<div class="layout"><div class="main">
  <div class="products">
  <?php foreach ($products as [$n, $p, $star]): ?>
    <div class="panel product"><div class="thumb" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($n, 0, 1))) ?></div><strong><?= h($n) ?></strong>
      <span class="price"><?= number_format($p, 0, ',', '.') ?> ₫</span>
      <?php if ($f['product_reviews']): ?><span class="muted"><?= number_format($star, 1, ',', '') ?> / 5 (số liệu minh họa)</span><?php endif; ?>
      <div class="row"><button class="btn" type="button"><?= $f['new_checkout'] ? 'Thanh toán 1 bước' : 'Thêm vào giỏ' ?></button>
        <?php if ($f['wishlist']): ?><button class="btn ghost" type="button">Yêu thích</button><?php endif; ?></div></div>
  <?php endforeach; ?>
  </div>
  <div class="panel pad"><h2>Thanh toán</h2>
    <div class="row"><?= $f['new_checkout'] ? '<button class="btn" type="button">Thanh toán mới (1 bước)</button>' : '<button class="btn" type="button">Thanh toán thường (3 bước)</button>' ?>
    <?= $f['one_click_buy'] ? '<button class="btn ghost" type="button">Mua một chạm</button>' : '' ?></div></div>
  <?php if ($f['recommendations']): ?><div class="panel pad"><h2>Gợi ý cho bạn</h2>Sản phẩm A · Sản phẩm B</div><?php endif; ?>
</div>
<aside class="side"><h2>Người dùng đang xem</h2>
  <form class="user" method="GET"><input name="user" value="<?= h($user) ?>" aria-label="Mã người dùng"><button class="btn">Xem</button></form>
  <h2 style="margin-top:20px">Trạng thái các tính năng</h2>
  <dl><?php foreach ($f as $k => $on): ?><div class="flag"><div><?= h($names[$k] ?? $k) ?><br><code><?= h($k) ?></code></div><span class="pill <?= $on ? 'on' : 'off' ?>"><?= $on ? 'BẬT' : 'TẮT' ?></span></div><?php endforeach; ?></dl>
</aside></div>
<p class="muted" style="margin-top:16px">Sản phẩm, giá và đánh giá trong ứng dụng này là dữ liệu minh họa; chỉ trạng thái tính năng lấy từ API.</p>
<?php if ($f['live_chat']): ?><div class="chat">Chat trực tuyến</div><?php endif; ?>
</div></body></html>
