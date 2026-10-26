<!-- Tài liệu sản phẩm: dùng cho các công cụ thiết kế (Impeccable) và cho người mới vào dự án. -->
# Feature Flag Manager

## Register
product

## Mục đích
Công cụ nội bộ để quản lý cấu hình bật/tắt tính năng cho ứng dụng web: khai báo tính năng và nhóm người dùng, đặt quy tắc theo người dùng, nhóm hoặc tỷ lệ %, lưu nháp, so sánh, phát hành và khôi phục phiên bản. Đề tài 1 của Bài tập lớn Phát triển phần mềm nguồn mở.

## Người dùng và tình huống
- **Người được sửa / quản trị**: ngồi trước màn hình rộng, làm việc theo phiên, cần biết nhanh môi trường nào đang chạy bản nào và một thay đổi sẽ ảnh hưởng bao nhiêu người trước khi phát hành.
- **Người chỉ xem**: đọc cấu hình và kết quả mô phỏng, không thấy API key.
- Tiếng Việt là ngôn ngữ giao diện duy nhất; số theo định dạng Việt Nam (1.000, 2,0%).

## Tính cách giao diện
Điềm tĩnh, chính xác, giống một sổ cái: ít màu, nhiều thông tin, không trang trí. Một màu xanh duy nhất dành cho dữ liệu chính.

## Nguyên tắc
1. Mỗi màn hình trả lời một câu hỏi trước (môi trường nào đang chạy gì; ai được bật tính năng này và vì quy tắc nào).
2. Màu dành cho dữ liệu, không dành cho khung.
3. Dùng từ của nghề: bản nháp, phát hành, khôi phục, quy tắc, mức phủ.
4. Mọi thay đổi đi qua bản nháp; bản đã phát hành chỉ đọc.
5. Quyền kiểm tra ở phía máy chủ, giao diện chỉ phản ánh.

## Tránh
Giao diện dashboard chung chung (thẻ KPI to, gradient, icon trong ô màu), dữ liệu mẫu không khớp nhau về số học, chữ trơn không khung cho trang lỗi.
