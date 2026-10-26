# Năm phép thử bắt buộc và cách kiểm tra

Chạy tự động: `bash tests/run_tests.sh` (phần tương ứng nằm trong `tests/unit_tests.php` và `tests/integration_tests.php`).

| # | Phép thử | Cách kiểm tra tự động | Kiểm tra thủ công |
|---|----------|----------------------|-------------------|
| 1 | Cùng người dùng/tính năng/môi trường/phiên bản luôn cùng kết quả; 0% không ai bật; 100% ai cũng bật | `unit_tests.php` mục 1 (200 lần gọi, 1.000 người dùng) | Gọi API hai lần với cùng `user`, so sánh JSON |
| 2 | Danh sách ưu tiên, nhóm, tỷ lệ xét theo thứ tự quy tắc | `unit_tests.php` mục 2 | Trang phiên bản: thêm quy tắc, đọc cột "reason" của API |
| 3 | Tài khoản chỉ xem không sửa được dù gửi yêu cầu trực tiếp | `integration_tests.php` mục E (HTTP 403, dữ liệu không đổi) | Đăng nhập `viewer`, dùng curl POST tới `handle/version_process.php` |
| 4 | Khôi phục bản cũ cho kết quả như trước, lịch sử giữ nguyên | `integration_tests.php` mục C | Môi trường → "Khôi phục bản này", xem Lịch sử |
| 5 | Ngắt API: ứng dụng dùng giá trị dự phòng, ghi lỗi, vẫn chạy | `integration_tests.php` mục F | Tắt máy chủ cổng 8080, tải lại ứng dụng thử nghiệm (cổng 8081) |

Kiểm thử bổ sung: sửa bản đã phát hành bị từ chối, một bản nháp mỗi môi trường, phát hành hai lần bị chặn, nhập JSON sai không ghi gì, xuất rồi nhập lại giống hệt, thiếu CSRF bị 403, thành viên dự án khác bị 403.

Kiểm thử cho phần nhóm người dùng: thêm nhiều người không xóa người cũ, mã mới được tạo, mã sai không ghi một phần, nhóm của dự án khác bị từ chối, người chỉ xem nhận 403 khi gửi trực tiếp, gửi 1.000 mã một lần, danh sách tổng hợp khớp `ffmEvaluate` (ưu tiên, lọc, phân trang), và hai tệp `database/samples/import_valid.json` / `import_invalid.json` được nhập hoặc bị từ chối đúng (`unit_tests.php`, `integration_tests.php` mục H).

Phân quyền ba mức: `unit_tests.php` (thứ bậc quản trị ⊃ sửa ⊃ xem) và `integration_tests.php` mục I (dev gửi trực tiếp yêu cầu thêm thành viên bị 403 nhưng vẫn khai báo được tính năng; quản trị viên cuối cùng không bị hạ quyền).

Phiên đăng nhập cũ: `integration_tests.php` mục H (tài khoản đã bị xóa khỏi CSDL thì phiên cũ bị chuyển về trang đăng nhập kèm lý do) và mục E (người chưa đăng nhập được chuyển tới `../index.php`, không lộ đường dẫn tệp trên máy chủ).
