---
name: Feature Flag Manager
description: Giao diện "achromatic ledger": khung không màu, một màu xanh cho dữ liệu
colors:
  ground: "#f2f4f7"
  surface: "#ffffff"
  text: "#0a0a0a"
  muted: "#5f6672"
  field-border: "#7f8692"
  data: "#1d5fbf"
  positive: "#1f8a4c"
typography:
  body: { fontFamily: "Geist", fontSize: "14px", lineHeight: "20px" }
  mono: { fontFamily: "Geist Mono", fontSize: "13px" }
rounded: { card: "8px", control: "6px" }
spacing: { gap: "16px", pad: "24px", row: "48px", control: "36px" }
---

# Hệ thống thiết kế

## Tổng quan
Sidebar trên nền trang, canvas bên phải với nội dung tối đa 1200px. Bề mặt phẳng: viền 1px, không đổ bóng khi nghỉ (chỉ menu nổi có bóng). Không gradient, không glass, không emoji.

## Màu
Chrome hoàn toàn không màu. `--data` (#1d5fbf) chỉ dùng cho mức phủ và phân rã quyết định. Trạng thái dùng chấm xanh lá kèm chữ (không bao giờ chỉ dựa vào màu). Viền ô nhập dùng `--field` (≥3:1 trên mọi nền ô); viền trang trí dùng `--control`/`--border`. Mọi màu đều là token trong `:root`.

## Chữ
Geist cho giao diện, Geist Mono chỉ cho mã, khóa API và số phiên bản. Chữ nhỏ nhất 13px. Số dùng `tabular-nums`. Tiêu đề dùng `text-wrap:balance`.

## Thành phần
- **Nút**: chính (nền đen), phụ, ghost; có hover, focus-visible và active; cao 36px (44px trên mobile).
- **Bảng**: hàng 48px, `<th scope="col">`, cột phụ ẩn bằng `.hide-sm` trên mobile.
- **Pill trạng thái**: Đang phát hành, Đã phát hành, Bản nháp.
- **Dải KPI**: một dải liền, không phải thẻ rời.
- **Trang lỗi**: `functions/error_page.php`, dùng chung CSS, một `h1`, một lối đi tiếp.

## Nên và không nên
- Nên: mỗi trang trả lời một câu hỏi trước; đặt từ ngữ đúng nghề; giữ số liệu khớp nhau.
- Không: đặt số đếm kho lên trước thông tin quyết định; dùng `exit('404: ...')` trần; thêm màu cho khung.
