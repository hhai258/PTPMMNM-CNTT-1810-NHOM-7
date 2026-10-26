# Hướng dẫn đóng góp

1. Fork và tạo nhánh từ `main`: `git checkout -b feature/ten-thay-doi`.
2. Cài môi trường theo [README](README.md), tạo CSDL `ffm_test` để chạy kiểm thử.
3. Viết mã theo cấu trúc hiện có: logic nghiệp vụ trong `functions/`, xử lý form trong `handle/`, giao diện trong `views/`.
4. Mọi truy vấn dùng prepared statement; mọi thao tác ghi phải kiểm tra quyền ở máy chủ (`requireProjectRole`) và CSRF.
5. Thêm hoặc cập nhật kiểm thử trong `tests/`, chạy `bash tests/run_tests.sh` và đảm bảo không có lỗi.
6. Commit rõ ràng (ví dụ `feat: so sánh phiên bản theo nhóm`), mở Pull Request mô tả thay đổi và kết quả kiểm thử.
7. Không đưa mật khẩu, API key, `config/config.local.php` hay `demo_app/config.local.php` lên Git.

Báo lỗi hoặc đề xuất qua Issues (có mẫu sẵn).
