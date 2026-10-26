<?php
/** Đăng nhập, phiên làm việc, CSRF và phân quyền theo dự án. */
require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/error_page.php';

function startSessionOnce(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    startSessionOnce();
    if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
        return false;
    }
    // Phiên cũ còn sót sau khi CSDL bị xóa hoặc nạp lại: tài khoản không còn thì coi như chưa đăng nhập.
    static $valid = null;
    if ($valid === null) {
        try {
            $u = dbOne('SELECT id FROM users WHERE id = ? AND username = ?', 'is', [(int)$_SESSION['user_id'], (string)$_SESSION['username']]);
        } catch (Throwable $e) {
            return false;   // chưa kiểm tra được (CSDL chưa sẵn sàng): không xóa phiên, nhưng cũng không cho vào
        }
        $valid = (bool)$u;
        if (!$valid) {
            $_SESSION = [];
            $_SESSION['error'] = 'Phiên đăng nhập không còn hợp lệ (tài khoản không tồn tại). Hãy đăng nhập lại.';
        }
    }
    return $valid;
}

function checkLogin(string $redirectPath = '../index.php'): void
{
    if (!isLoggedIn()) {
        $_SESSION['error'] = $_SESSION['error'] ?? 'Bạn cần đăng nhập để truy cập trang này!';   // giữ lý do cụ thể nếu đã có
        header('Location: ' . $redirectPath);
        exit();
    }
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    return ['id' => (int)$_SESSION['user_id'], 'username' => $_SESSION['username'], 'full_name' => $_SESSION['full_name'] ?? ''];
}

/** Trả về thông tin người dùng nếu đúng mật khẩu, ngược lại false. */
function authenticateUser(string $username, string $password)
{
    $u = dbOne('SELECT id, username, password_hash, full_name FROM users WHERE username = ? LIMIT 1', 's', [$username]);
    if ($u && password_verify($password, $u['password_hash'])) {
        return $u;
    }
    return false;
}

function csrfToken(): string
{
    startSessionOnce();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

/** Dừng với mã 403 nếu token CSRF sai. */
function requireCsrf(): void
{
    startSessionOnce();
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        abortWith(403, 'Phiên làm việc đã hết hạn hoặc biểu mẫu không hợp lệ. Hãy tải lại trang rồi thử lại.');
    }
}

/** Thứ bậc quyền trong dự án: quản trị ⊃ sửa ⊃ xem. */
const FFM_ROLE_RANK = ['viewer' => 1, 'editor' => 2, 'admin' => 3];

/** Vai trò $role có đạt mức $need ('viewer' | 'editor' | 'admin') không. null/lạ: không đạt. */
function roleAtLeast(?string $role, string $need): bool
{
    return (FFM_ROLE_RANK[$role ?? ''] ?? 0) >= (FFM_ROLE_RANK[$need] ?? PHP_INT_MAX);
}

/** Vai trò của người dùng trong dự án: 'admin', 'editor', 'viewer' hoặc null. */
function getProjectRole(int $projectId, int $userId): ?string
{
    $m = dbOne('SELECT role FROM memberships WHERE project_id = ? AND user_id = ?', 'ii', [$projectId, $userId]);
    return $m['role'] ?? null;
}

/**
 * Kiểm tra quyền ở phía máy chủ. $need = 'viewer' (xem), 'editor' (sửa cấu hình) hoặc 'admin' (quản lý thành viên).
 * Không đủ quyền: trả 403 và dừng, kể cả khi người dùng gửi yêu cầu trực tiếp.
 */
function requireProjectRole(int $projectId, string $need): string
{
    $user = getCurrentUser();
    $role = $user ? getProjectRole($projectId, $user['id']) : null;
    if (!roleAtLeast($role, $need)) {
        abortWith(403, 'Bạn không có quyền ' . ['viewer' => 'xem', 'editor' => 'sửa', 'admin' => 'quản trị'][$need] . ' dự án này.');
    }
    return $role;
}
