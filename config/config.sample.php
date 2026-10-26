<?php
// Sao chép tệp này thành config/config.local.php rồi sửa thông số.
// config.local.php nằm trong .gitignore, không được đưa lên Git.
return [
    'db' => [
        'host' => '127.0.0.1',
        'user' => 'ffm',
        'pass' => 'doi_mat_khau_o_day',
        'name' => 'ffm',
        'port' => 3306,
    ],
    'log_decisions' => true, // ghi decision_logs cho mỗi lần API trả kết quả
];
