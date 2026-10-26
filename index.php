<?php
require_once __DIR__ . '/functions/auth.php';
startSessionOnce();
if (isLoggedIn()) {
    header('Location: views/projects.php');
    exit();
}
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập · Feature Flag Manager</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 28 28'%3E%3Crect width='28' height='28' rx='7' fill='%23141414'/%3E%3Crect x='6' y='9.5' width='16' height='9' rx='4.5' fill='none' stroke='%23fff' stroke-width='1.6'/%3E%3Ccircle cx='17.5' cy='14' r='2.6' fill='%23fff'/%3E%3C/svg%3E">
<link rel="stylesheet" href="css/style.css"></head>
<body>
<main class="login"><div class="login-shell">
  <section class="login-form">
    <svg class="mark" width="36" height="36" viewBox="0 0 28 28" aria-hidden="true"><rect width="28" height="28" rx="7" fill="#141414"/><rect x="6" y="9.5" width="16" height="9" rx="4.5" fill="none" stroke="#fff" stroke-width="1.6"/><circle cx="17.5" cy="14" r="2.6" fill="#fff"/></svg>
    <div>
      <h1>Đăng nhập</h1>
      <p class="sub">Feature Flag Manager: bật, tắt và phát hành dần tính năng của ứng dụng Web mà không cần triển khai lại.</p>
    </div>
    <?php if ($error): ?><div class="notice notice-error" role="alert" style="margin:0"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="POST" action="handle/login_process.php">
      <?= csrfField() ?>
      <label class="field">Tên đăng nhập<input type="text" name="username" required autofocus autocomplete="username"></label>
      <label class="field">Mật khẩu<input type="password" name="password" required autocomplete="current-password"></label>
      <button type="submit" class="btn btn-primary">Đăng nhập</button>
    </form>
  </section>
  <section class="login-aside" aria-label="Cách hệ thống quyết định">
    <div>
      <h2>Một người dùng, một câu trả lời, luôn như nhau</h2>
      <p class="sub" style="max-width:560px">Mỗi người dùng được gán vào một trong 100 xô bằng mã băm của tính năng, môi trường và mã người dùng. Với tỷ lệ 30%, các xô 0 đến 29 được bật; tăng lên 60% thì những người đã bật vẫn giữ nguyên.</p>
    </div>
    <div class="buckets" role="img" aria-label="100 xô, 30 xô đầu được bật">
      <?php for ($i = 0; $i < 100; $i++): ?><i class="<?= $i < 30 ? 'on' : '' ?>"></i><?php endfor; ?>
    </div>
    <ol class="steps">
      <li class="step"><b aria-hidden="true">1</b><div><strong>Quy tắc theo thứ tự ưu tiên</strong><p>Danh sách người dùng, nhóm hoặc tỷ lệ phần trăm.</p></div></li>
      <li class="step"><b aria-hidden="true">2</b><div><strong>Quy tắc khớp đầu tiên quyết định</strong><p>Các quy tắc sau đó không được xét nữa.</p></div></li>
      <li class="step"><b aria-hidden="true">3</b><div><strong>Không khớp thì dùng giá trị mặc định</strong><p>Và mọi thay đổi đều đi qua bản nháp, phát hành, khôi phục.</p></div></li>
    </ol>
  </section>
</div></main>
</body></html>
