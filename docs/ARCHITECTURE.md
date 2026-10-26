# Kiến trúc

```
index.php               đăng nhập
views/                  giao diện (projects, project, environment, version, compare, history)
handle/                 nhận form POST: kiểm tra đăng nhập, CSRF, quyền sửa, rồi gọi functions/
functions/              nghiệp vụ: db_connection, auth, project_*, version_*, decision_*
  evaluator.php         đánh giá bật/tắt (thuần, không CSDL)
  config_tools.php      kiểm tra dữ liệu nhập/xuất, so sánh phiên bản (thuần)
  directory_functions.php  danh sách người dùng giả: nhóm, kết quả, quy tắc ưu tiên quyết định (thuần + truy vấn đọc)
api/evaluate.php        API cho ứng dụng khác
demo_app/               ứng dụng thử nghiệm gọi API, có giá trị dự phòng
database/               schema.sql, seed.php, samples/ (JSON để thử nhập cấu hình)
tests/                  kiểm thử đơn vị + tích hợp
```

**Cách chia người dùng:** `bucket = crc32(flag_key:env_key:user_id) % 100`; quy tắc tỷ lệ P% khớp khi `bucket < P`. Không dùng số ngẫu nhiên nên kết quả ổn định; 0% không ai khớp, 100% ai cũng khớp.

**Thứ tự quy tắc:** sắp theo `priority` tăng dần, quy tắc đầu tiên khớp quyết định; không khớp thì dùng mặc định của tính năng.

**Phiên bản:** mỗi môi trường có chuỗi `versions` (v1, v2, ...). Chỉ bản `draft` được sửa (kiểm tra ở `requireDraft`). Phát hành đổi `environments.active_version_id` trong giao dịch có khóa. Khôi phục sao bản cũ thành bản phát hành mới, không xóa gì.

**Phân quyền:** ba mức theo thứ bậc `viewer < editor < admin` (`roleAtLeast` trong `functions/auth.php`). Xem cấu hình cần `viewer`; sửa cấu hình, tính năng, nhóm cần `editor`; thêm hoặc đổi quyền thành viên cần `admin`. Dự án luôn còn ít nhất một `admin`.

**Bảo mật:** prepared statement, `password_hash`, token CSRF, kiểm tra quyền ở máy chủ, quyền xác định từ CSDL (không tin `project_id` do client gửi).

**Thành viên nhóm:** `addGroupMembers` chỉ thêm (không xóa người đang có) và tạo `end_users` cho mã mới; `setGroupMembers` thay toàn bộ danh sách. Hộp chọn nhiều người gửi danh sách trong **một** ô ẩn (không dùng `members[]`) để không vướng giới hạn `max_input_vars` của PHP khi chọn hơn 1.000 người. Cả hai thao tác kiểm tra quyền sửa và nhóm thuộc đúng dự án ở máy chủ.
