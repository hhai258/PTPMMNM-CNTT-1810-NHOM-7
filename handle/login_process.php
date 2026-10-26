<?php
require_once __DIR__ . '/../functions/auth.php';
startSessionOnce();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}
requireCsrf();
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!';
    header('Location: ../index.php');
    exit();
}
$user = authenticateUser($username, $password);
if ($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    header('Location: ../views/projects.php');
} else {
    $_SESSION['error'] = 'Tên đăng nhập hoặc mật khẩu không đúng!';
    header('Location: ../index.php');
}
exit();
