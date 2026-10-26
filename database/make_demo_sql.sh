#!/bin/bash
# Xuất lại database/demo_full.sql từ CSDL ffm đang có dữ liệu mẫu (chạy sau: php database/seed.php).
# Dùng: bash database/make_demo_sql.sh [tài_khoản_mysql]   (mặc định root, không mật khẩu)
set -e
U="${1:-root}"
OUT="$(dirname "$0")/demo_full.sql"
awk '/^--/{print;next}{exit}' "$OUT" > "$OUT.tmp"
mysqldump -u"$U" --skip-comments --add-drop-table --skip-add-locks --skip-lock-tables --default-character-set=utf8mb4 ffm | grep -v "^/\*M!" >> "$OUT.tmp"
mv "$OUT.tmp" "$OUT"
echo "Đã ghi $OUT"
