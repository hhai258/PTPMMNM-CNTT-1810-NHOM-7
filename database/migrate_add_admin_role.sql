-- Nâng cấp CSDL đã có để dùng vai trò "admin" (quản trị) của dự án.
-- Không cần chạy nếu bạn chạy lại `php database/seed.php` (seed tạo lại toàn bộ bảng).
ALTER TABLE memberships MODIFY role ENUM('viewer','editor','admin') NOT NULL;
-- Trước đây người tạo dự án là "editor": nâng tài khoản quản trị lên "admin" (đổi 'admin' nếu tên đăng nhập khác).
UPDATE memberships m JOIN users u ON u.id = m.user_id SET m.role = 'admin' WHERE u.username = 'admin' AND m.role = 'editor';
