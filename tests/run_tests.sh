#!/usr/bin/env bash
# Chạy toàn bộ kiểm thử. Cần CSDL tên kết thúc bằng _test (xem README).
set -e
cd "$(dirname "$0")/.."
php tests/unit_tests.php
FFM_DB_NAME="${FFM_DB_NAME:-ffm_test}" php tests/integration_tests.php
