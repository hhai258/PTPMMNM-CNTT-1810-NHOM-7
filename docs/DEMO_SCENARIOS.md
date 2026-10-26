# Hai tình huống trình bày

Chuẩn bị: `php database/seed.php`, chạy `php -S 127.0.0.1:8080` (hệ thống) và `php -S 127.0.0.1:8081 -t demo_app` (ứng dụng thử nghiệm). Điền API key môi trường `testing` vào `demo_app/config.local.php`.

## Tình huống 1: phát hành dần "Thanh toán mới"
1. Đăng nhập `admin` → dự án "Cửa hàng demo" → môi trường Thử nghiệm → "Tạo bản nháp".
2. Mở bản nháp, tính năng `new_checkout`: thêm quy tắc nhóm `beta` 50%, tỷ lệ 10%; xem cột mô phỏng trên 1.000 người.
3. Phát hành (nhập lý do). Mở `http://127.0.0.1:8081/?user=user0001` và các người dùng khác: giao diện thanh toán đổi theo kết quả API.

## Tình huống 2: sự cố và khôi phục
1. Tạo bản nháp mới, nâng `new_checkout` lên 100% và tắt `fast_search` (nhập lý do), phát hành.
2. Trang môi trường → So sánh hai bản vừa rồi để thấy thay đổi.
3. Ứng dụng thử nghiệm có lỗi → "Khôi phục bản này" cho bản trước, nhập lý do. Tải lại ứng dụng: kết quả như trước. Trang Lịch sử vẫn đủ các lần phát hành.
4. Tắt máy chủ cổng 8080, tải lại ứng dụng: hiện thông báo dùng giá trị dự phòng, vẫn hoạt động; lỗi nằm trong `demo_app/logs/error.log`.

## Tình huống bổ sung: nhóm người dùng và nhập JSON
1. Dự án "Cửa hàng demo" → mục Nhóm người dùng → nhóm `vip` → **Sửa thành viên**: tìm `user03`, bấm "Chọn tất cả đang hiển thị", Lưu. Bấm **Thêm thành viên** vào `staff`: chọn vài người, gõ thêm mã mới `user1001`, Lưu (thông báo nêu số người đã thêm và số mã tạo mới).
2. Trang **Người dùng giả**: chọn tính năng `recommendations`, nhóm `vip`: cột Ưu tiên cho thấy `user0007` bị p1 tắt dù thuộc vip, người khác được p2 bật. Bấm `user0007` để xem kết quả mọi tính năng của người đó.
3. Môi trường Thử nghiệm → So sánh v2 và v3 (đã nạp sẵn) → Khôi phục v2 → mở ứng dụng thử nghiệm để thấy kết quả đổi.
4. Nhập `database/samples/import_valid.json` (thành bản nháp v4) rồi thử `import_invalid.json` để thấy dữ liệu sai bị từ chối.
