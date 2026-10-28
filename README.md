# Feature Flag Manager

**Hệ thống quản lý cấu hình bật, tắt tính năng của ứng dụng Web**

[![CI](https://github.com/hhai258/PTPMMNM-CNTT-1810-NHOM-7/actions/workflows/ci.yml/badge.svg)](https://github.com/hhai258/PTPMMNM-CNTT-1810-NHOM-7/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Bài tập lớn học phần **Phát triển phần mềm mã nguồn mở**, **Đề tài 1**. Viết bằng PHP thuần và MySQL/MariaDB, không dùng framework.

## Thông tin bài tập lớn

| Mục | Nội dung |
|---|---|
| Học phần | Phát triển phần mềm mã nguồn mở |
| Trường / lớp | Đại học Đại Nam, lớp CNTT-1810 |
| Nhóm | Nhóm 7 (repo `PTPMMNM-CNTT-1810-NHOM-7`) |
| Đề tài | Đề tài 1. Xây dựng hệ thống quản lý cấu hình bật, tắt tính năng của ứng dụng Web |
| Giảng viên | Nguyễn Thế Huy Hoàng ||Lê Tuấn Anh|

### Thành viên

| STT | Họ và tên | Mã sinh viên | GitHub | Phần phụ trách |
|||---|---|---|
| 1 | Nguyễn Hoàng Hải | 1871020211 | [@hhai258](https://github.com/hhai258) | `<điền phần phụ trách>` |
| 2 | Nguyễn Thành Đạt | 1871020135 | @th4nhdat2611 | 

---

## 1. Giới thiệu

Khi bổ sung tính năng mới, nhóm phát triển có thể cho một số người dùng thử trước. Hệ thống này cho phép:

- chọn nhóm người dùng được dùng tính năng và đặt **tỷ lệ áp dụng**;
- lưu từng bản cấu hình, so sánh, phát hành;
- khi có lỗi, **khôi phục** về bản cấu hình trước.

Ứng dụng khác lấy kết quả bật/tắt qua một **API PHP**. Kho này gồm cả hệ thống quản lý và một **ứng dụng thử nghiệm** (`demo_app/`) gọi API, có giá trị dự phòng khi API lỗi.

**Người sử dụng:** người quản lý cấu hình, lập trình viên và ứng dụng thử nghiệm được cấp tài khoản.

## 2. Phạm vi chức năng

### Năm nhóm chức năng của đề tài

| # | Nhóm chức năng | Đã làm | Vị trí chính trong mã |
|---|---|---|---|
| 1 | Dự án, thành viên, cấu hình riêng cho 2 môi trường (**Thử nghiệm**, **Vận hành mô phỏng**); phân biệt quyền xem và sửa | Ba mức quyền: `viewer` (chỉ xem), `editor` (sửa cấu hình, tính năng, nhóm), `admin` (thêm hoặc đổi quyền thành viên). Người tạo dự án là `admin`. Dự án luôn còn ít nhất một `admin`. | `functions/auth.php`, `functions/project_functions.php`, `handle/project_process.php` |
| 2 | Khai báo tính năng, nhóm người dùng, tỷ lệ; thứ tự ưu tiên khi nhiều điều kiện cùng đúng | Ba loại quy tắc: danh sách người dùng, nhóm (kèm tỷ lệ), tỷ lệ %. Số ưu tiên nhỏ xét trước, **quy tắc đầu tiên khớp quyết định**. Cảnh báo quy tắc bị quy tắc trước lấn hết người. | `functions/evaluator.php`, `functions/version_functions.php`, `views/version.php` |
| 3 | API PHP trả bật/tắt; ứng dụng nhỏ gọi API và xử lý khi không lấy được cấu hình | `api/evaluate.php`; `demo_app/` dùng giá trị dự phòng, ghi lỗi vào `demo_app/logs/error.log` và vẫn chạy | `api/evaluate.php`, `functions/decision_functions.php`, `demo_app/` |
| 4 | Lưu, so sánh, phát hành, khôi phục bản cấu hình; sửa bản đã phát hành phải tạo phiên bản mới | Chỉ bản nháp được sửa. Mỗi môi trường tối đa một bản nháp. Khôi phục **sao bản cũ thành bản phát hành mới**, không xóa gì. | `functions/version_functions.php`, `views/environment.php`, `views/compare.php` |
| 5 | Lịch sử thay đổi và lý do bật/tắt theo phiên bản; nhập, xuất cấu hình, kiểm tra dữ liệu nhập | Nhật ký thay đổi kèm lý do; xuất/nhập JSON định dạng `ffm-config/1`; dữ liệu sai bị từ chối kèm danh sách lỗi và không ghi gì | `functions/config_tools.php`, `handle/export.php`, `views/history.php` |

### Chức năng bổ sung

- Hộp **chọn nhiều người** khi sửa quy tắc danh sách người dùng và khi thêm, sửa thành viên nhóm (tìm kiếm, lọc theo nhóm, chọn tất cả đang hiển thị, Shift+bấm chọn cả dải).
- Trang **Người dùng giả**: bảng tổng hợp 1.000 người, mỗi người thuộc nhóm nào, được bật hay tắt một tính năng và quy tắc nào đã quyết định.
- Trang chi tiết phiên bản hiển thị mức phủ của từng tính năng trên 1.000 người dùng giả, tính bằng đúng hàm `ffmEvaluate` mà API dùng.
- So sánh phiên bản kèm tác động lên số người được bật trước và sau thay đổi.

### Ngoài phạm vi

Theo đề bài, nhóm **không** làm: đồng bộ trên nhiều máy chủ, phân tích thống kê thử nghiệm A/B, thư viện tích hợp cho nhiều ngôn ngữ lập trình. Ứng dụng thử nghiệm chỉ có một (PHP).

## 3. Hai yêu cầu kỹ thuật và cách giải quyết

**Yêu cầu 1. Kết quả ổn định, xử lý đúng tỷ lệ 0% và 100%, đúng thứ tự ưu tiên.**
Không dùng số ngẫu nhiên. Mỗi người dùng được gán vào một xô cố định:

```
bucket = crc32(flag_key : env_key : user_id) % 100
quy tắc tỷ lệ P% khớp khi bucket < P
```

Cùng người dùng, tính năng, môi trường và phiên bản luôn cho cùng kết quả. 0% không ai khớp, 100% ai cũng khớp. Danh sách người dùng được xét theo số ưu tiên chung với quy tắc nhóm và quy tắc tỷ lệ.

**Yêu cầu 2. Khôi phục đúng cách bật/tắt trước đó; có giá trị dự phòng khi API hoặc cấu hình lỗi.**
Khôi phục tạo một phiên bản phát hành mới có cấu hình giống hệt bản cũ, lịch sử phát hành được giữ nguyên. Ứng dụng thử nghiệm có bảng giá trị dự phòng (`FFM_FALLBACK` trong `demo_app/client.php`: tính năng mới tắt, tính năng ổn định bật) dùng khi không kết nối được API, hết thời gian chờ, sai key hoặc JSON hỏng.

Chi tiết thiết kế: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## 4. Công nghệ, phần tự phát triển và tài nguyên sử dụng lại

| Thành phần | Nội dung |
|---|---|
| Ngôn ngữ | PHP ≥ 8.1 (extension `mysqli`, `mbstring`) |
| Cơ sở dữ liệu | MySQL 8 hoặc MariaDB 10.4+ |
| Máy chủ | Máy chủ tích hợp của PHP (`php -S`) hoặc Apache (XAMPP) |
| Kiểm thử | Bộ kiểm thử PHP tự viết (`tests/`), chạy tự động bằng GitHub Actions |
| Giao diện | HTML, CSS, JavaScript thuần |

**Phần nhóm tự phát triển (toàn bộ mã nguồn trong kho, trừ phông chữ):** bộ đánh giá bật/tắt, API, quản lý phiên bản, so sánh, khôi phục, phân quyền, nhập/xuất và kiểm tra dữ liệu, ứng dụng thử nghiệm, giao diện, schema, dữ liệu mẫu và bộ kiểm thử.

**Thư viện và tài nguyên sử dụng lại:**

- Phông **Geist** và **Geist Mono** (Vercel), giấy phép SIL Open Font License 1.1, nằm trong `css/fonts/` và `demo_app/fonts/` kèm `OFL-Geist.txt`.
- Không dùng thư viện PHP, JavaScript hay CSS bên ngoài; không cần Composer hay npm.

## 5. Cấu trúc thư mục

```
index.php              Đăng nhập
views/                 Giao diện: projects, project, environment, version, compare, history, directory
handle/                Nhận form POST: kiểm tra đăng nhập, CSRF, quyền, rồi gọi functions/
functions/             Nghiệp vụ: auth, project_*, version_*, decision_*, config_tools, evaluator, ...
api/evaluate.php       API bật/tắt cho ứng dụng khác
demo_app/              Ứng dụng thử nghiệm gọi API, có giá trị dự phòng
database/              schema.sql, seed.php, demo_full.sql, samples/ (JSON để thử nhập)
tests/                 Kiểm thử đơn vị và tích hợp
docs/                  ARCHITECTURE.md, TEST_CASES.md, DEMO_SCENARIOS.md
config/                config.sample.php (sao thành config.local.php, không đưa lên Git)
css/ js/               Giao diện
.github/               CI (GitHub Actions) và mẫu báo lỗi
```

## 6. Cài đặt

Yêu cầu: PHP ≥ 8.1 (`mysqli`, `mbstring`), MySQL 8 hoặc MariaDB 10.4+, Git.

### Cách A. Ubuntu / WSL

```bash
sudo apt install php-cli php-mysql php-mbstring mariadb-server git
git clone https://github.com/hhai258/PTPMMNM-CNTT-1810-NHOM-7.git feature-flag-manager
cd feature-flag-manager

# 1. Tạo CSDL và tài khoản
sudo mysql -e "CREATE DATABASE ffm CHARACTER SET utf8mb4;
  CREATE USER 'ffm'@'localhost' IDENTIFIED BY 'DOI_MAT_KHAU';
  GRANT ALL ON ffm.* TO 'ffm'@'localhost';"

# 2. Cấu hình (tệp này không được đưa lên Git)
cp config/config.sample.php config/config.local.php    # sửa mật khẩu

# 3. Tạo bảng và nạp dữ liệu mẫu
php database/seed.php

# 4. Chạy hệ thống
php -S 127.0.0.1:8080
```

Mở http://127.0.0.1:8080 và đăng nhập bằng tài khoản mẫu ở mục 7.

### Cách B. Windows với XAMPP

1. Mở XAMPP Control Panel, bấm **Start** cho **MySQL**. Cần PHP 8.1 trở lên (`C:\xampp\php\php.exe -v`).
2. Tạo hai CSDL (XAMPP mặc định dùng `root` **không có mật khẩu**):
   ```bat
   C:\xampp\mysql\bin\mysql.exe -uroot -e "CREATE DATABASE ffm CHARACTER SET utf8mb4; CREATE DATABASE ffm_test CHARACTER SET utf8mb4;"
   ```
3. Sao chép `config\config.sample.php` thành `config\config.local.php`, sửa `'user' => 'root'`, `'pass' => ''`.
4. Nạp dữ liệu và chạy (mở CMD tại thư mục dự án):
   ```bat
   C:\xampp\php\php.exe database\seed.php
   C:\xampp\php\php.exe -S 127.0.0.1:8080
   ```
5. Nếu báo thiếu `mysqli` hoặc `mbstring`, bỏ dấu `;` trước `extension=mysqli` và `extension=mbstring` trong `C:\xampp\php\php.ini`.

**Chạy bằng Apache của XAMPP (tùy chọn):** chép thư mục dự án vào `C:\xampp\htdocs\<tên-thư-mục>` rồi mở `http://localhost/<tên-thư-mục>/`. Khi đó đặt `api_base` của ứng dụng thử nghiệm là `http://localhost/<tên-thư-mục>/api/evaluate.php`.

### Cách C. Chỉ dùng phpMyAdmin (không cần dòng lệnh)

Có hai tệp SQL, đừng nhầm:

| Tệp | Có gì | Dùng khi |
|---|---|---|
| `database/schema.sql` | Chỉ **bảng rỗng** | Chỉ cần khung CSDL; sau đó vẫn phải chạy `seed.php` |
| `database/demo_full.sql` | Bảng **và dữ liệu mẫu** (3 tài khoản, 1 dự án, 10 tính năng, 3 nhóm, 1.000 người dùng giả, phiên bản v1 đến v3) | Muốn có ngay dữ liệu |

Các bước: `http://localhost/phpmyadmin` → **New** → tên `ffm`, kiểu so sánh `utf8mb4_general_ci` → **Create** → chọn `ffm` → tab **Import** → chọn `database/demo_full.sql` → **Import**. Tệp này xóa bảng cũ rồi tạo lại nên chạy lại bao nhiêu lần cũng đưa dữ liệu về ban đầu.

Sau đó vẫn cần bước 3 của cách B (`config.local.php`) để hệ thống kết nối CSDL.

## 7. Sử dụng

### Tài khoản mẫu (dữ liệu giả, chỉ dùng cục bộ)

| Tài khoản | Mật khẩu | Quyền |
|---|---|---|
| `admin` | `admin123` | Quản trị: sửa cấu hình và quản lý thành viên |
| `dev` | `dev123` | Được sửa cấu hình, không quản lý được thành viên |
| `viewer` | `viewer123` | Chỉ xem, không thấy API key |

### Ứng dụng thử nghiệm

```bash
cp demo_app/config.sample.php demo_app/config.local.php    # điền api_key của môi trường cần thử
php -S 127.0.0.1:8081 -t demo_app                           # cửa sổ terminal khác
```

Mở http://127.0.0.1:8081/?user=user0001. Hai máy chủ chạy riêng để ứng dụng gọi được API. API key của từng môi trường được `seed.php` in ra, người có quyền sửa cũng xem được ở trang dự án.

### API

```bash
curl "http://127.0.0.1:8080/api/evaluate.php?key=<API_KEY>&user=user0001&feature=new_checkout"
# {"feature":"new_checkout","enabled":true,"reason":"rule:user_list#p1","version":2,"environment":"testing"}
```

- Bỏ `feature` để lấy mọi tính năng. API key gửi qua tham số `key` hoặc header `X-Api-Key`.
- Chỉ hỗ trợ `GET`.
- Mã lỗi: `400` thiếu hoặc sai `user`, `401` sai key, `404` tính năng không tồn tại, `405` sai phương thức, `503` môi trường chưa phát hành bản nào.

### Định dạng nhập, xuất cấu hình

```json
{
  "format": "ffm-config/1",
  "flags": {
    "new_checkout": {
      "default": false,
      "rules": [
        { "priority": 1, "type": "user_list", "value": true, "users": ["user0001"] },
        { "priority": 2, "type": "group", "value": true, "group": "beta", "percentage": 80 },
        { "priority": 3, "type": "percentage", "value": true, "percentage": 50 }
      ]
    }
  }
}
```

Mẫu hợp lệ: `database/samples/import_valid.json`. Mẫu sai (để thấy dữ liệu bị từ chối): `database/samples/import_invalid.json`.

### Hai tình huống sử dụng của đề tài

Các bước đầy đủ nằm ở [docs/DEMO_SCENARIOS.md](docs/DEMO_SCENARIOS.md).

1. **Tạo cấu hình → kiểm tra → phát hành → gọi API từ ứng dụng thử nghiệm.** Tạo bản nháp, thêm quy tắc cho `new_checkout`, xem mức phủ trên 1.000 người, phát hành, mở ứng dụng thử nghiệm để thấy giao diện đổi theo kết quả API.
2. **Đổi tỷ lệ áp dụng → so sánh phiên bản → khôi phục bản cũ → kiểm tra ứng dụng.** Phát hành bản mới đổi tỷ lệ, so sánh hai bản, khôi phục bản trước, tải lại ứng dụng thử nghiệm để thấy kết quả như cũ; trang Lịch sử vẫn đủ các lần phát hành.

## 8. Dữ liệu mẫu

`php database/seed.php` (hoặc `database/demo_full.sql`) tạo toàn bộ dữ liệu **giả**:

| Yêu cầu của đề tài | Dữ liệu mẫu |
|---|---|
| Ít nhất 2 môi trường | 2: Thử nghiệm, Vận hành mô phỏng |
| Ít nhất 10 tính năng | 10 |
| Ít nhất 3 nhóm | 3: `beta` (200 người), `vip` (142 người), `staff` (20 người); có người thuộc nhiều nhóm |
| Ít nhất 1.000 mã người dùng giả | 1.000 (`user0001` đến `user1000`) |

Môi trường Thử nghiệm có sẵn ba phiên bản đã phát hành để thử so sánh và khôi phục:

- **v1** rỗng (mọi tính năng tắt), **v2** cấu hình đầu tiên, **v3** (đang phát hành) khác v2 ở nhiều điểm: `new_checkout` 10% → 30%, `live_chat` 0% → 25%, `one_click_buy` thêm nhóm `staff` và `vip`, `recommendations` thêm quy tắc ưu tiên.
- **Thử thứ tự ưu tiên:** ở trang Người dùng giả chọn tính năng `recommendations`: `user0007` thuộc nhóm `vip` nhưng bị quy tắc ưu tiên 1 tắt, còn `user0014` (cũng `vip`) được bật bởi quy tắc ưu tiên 2.

Các bảng chính: `projects`, `environments`, `flags`, `user_groups`, `group_members`, `end_users`, `versions`, `version_flags`, `rules`, `memberships`, `users`, `change_logs`, `decision_logs`. Xem `database/schema.sql`.

## 9. Kiểm thử

```bash
# Tạo CSDL kiểm thử (tên phải kết thúc bằng _test)
mysql -uroot -e "CREATE DATABASE ffm_test CHARACTER SET utf8mb4; GRANT ALL ON ffm_test.* TO 'ffm'@'localhost';"

# Chạy toàn bộ: kiểm thử đơn vị + tích hợp
FFM_DB_PASS=DOI_MAT_KHAU bash tests/run_tests.sh
```

Trên Windows (không cần Bash):

```bat
php tests\unit_tests.php
set FFM_DB_NAME=ffm_test&& set FFM_DB_USER=root&& php tests\integration_tests.php
```

Tệp tích hợp **từ chối chạy** nếu tên CSDL không kết thúc bằng `_test`, vì nó tạo lại toàn bộ bảng.

### Năm phép thử bắt buộc

| # | Phép thử | Kiểm tra tự động |
|---|---|---|
| 1 | Gọi nhiều lần với cùng phiên bản và người dùng cho cùng kết quả; riêng 0% và 100% | `tests/unit_tests.php` mục 1 |
| 2 | Người thuộc danh sách ưu tiên nhận kết quả theo đúng thứ tự quy tắc | `tests/unit_tests.php` mục 2 |
| 3 | Tài khoản chỉ xem không sửa được cấu hình, kể cả khi gửi yêu cầu trực tiếp | `tests/integration_tests.php` mục E (HTTP 403, dữ liệu không đổi) |
| 4 | Khôi phục bản cũ cho kết quả như trước; lịch sử phát hành được giữ nguyên | `tests/integration_tests.php` mục C |
| 5 | Ngắt API: ứng dụng dùng giá trị dự phòng, ghi lỗi và tiếp tục hoạt động | `tests/integration_tests.php` mục F |

Kiểm thử bổ sung: sửa bản đã phát hành bị từ chối, mỗi môi trường một bản nháp, phát hành hai lần bị chặn, nhập JSON sai không ghi gì, xuất rồi nhập lại giống hệt, thiếu CSRF bị 403, thành viên dự án khác bị 403, phân quyền ba mức. Danh sách đầy đủ và cách kiểm tra thủ công: [docs/TEST_CASES.md](docs/TEST_CASES.md).

**CI:** mỗi lần `push` hoặc `pull request`, GitHub Actions kiểm tra cú pháp mọi tệp PHP rồi chạy toàn bộ kiểm thử với MySQL 8 (`.github/workflows/ci.yml`).

## 10. An toàn và dữ liệu

- **Chỉ dùng dữ liệu giả.** Mật khẩu và API key trong kho (tài khoản mẫu, `demo_full.sql`) chỉ để chạy thử cục bộ.
- **Không đưa lên Git:** `config/config.local.php`, `demo_app/config.local.php`, `*.local`, `*.log`, `demo_app/logs/` (đã nằm trong `.gitignore`).
- Mọi truy vấn dùng **prepared statement**; mật khẩu lưu bằng `password_hash`; mọi thao tác ghi kiểm tra **token CSRF** và **quyền ở phía máy chủ** (`requireProjectRole`), quyền lấy từ CSDL chứ không tin `project_id` do trình duyệt gửi.
- Người chỉ xem không thấy API key.

## 11. Đóng góp, báo lỗi, giấy phép

- Quy trình đóng góp: [CONTRIBUTING.md](CONTRIBUTING.md). Báo lỗi hoặc đề xuất qua [Issues](https://github.com/hhai258/PTPMMNM-CNTT-1810-NHOM-7/issues) (có mẫu sẵn).
- Mỗi thay đổi đi qua nhánh riêng và Pull Request có người xem lại trước khi gộp vào `main`.
- Giấy phép: [MIT](LICENSE).

## 12. Bản phát hành

Bản nộp cuối kỳ nằm ở mục [Releases](https://github.com/hhai258/PTPMMNM-CNTT-1810-NHOM-7/releases) (ví dụ `btl-nop-cuoi-ky`), gói mã nguồn `BTL_Nhom07.zip`. Gói này có thể cài và chạy lại theo mục 6 và mục 7 của tài liệu này.

## 13. Nâng cấp CSDL đã có

Bản có vai trò quản trị thêm giá trị `admin` cho `memberships.role`. Chạy lại `php database/seed.php` (tạo lại bảng), hoặc nếu muốn giữ dữ liệu thì chạy `database/migrate_add_admin_role.sql`.
