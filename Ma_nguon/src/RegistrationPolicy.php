<?php
declare(strict_types=1);
final class RegistrationPolicy {
    public function decide(int $capacity, int $registered,
        bool $open, bool $waitingEnabled): string {
        // 1. Dữ liệu không hợp lệ -> rejected
        if ($capacity <= 0 || $registered < 0 || $registered > $capacity) {
            return 'rejected';
        }
        // 2. Sự kiện đóng -> rejected (kể cả còn chỗ)
        if (!$open) {
            return 'rejected';
        }
        // 3. Còn chỗ -> confirmed
        if ($registered < $capacity) {
            return 'confirmed';
        }
        // 4. Hết chỗ: có danh sách chờ -> waiting, không có -> rejected
        return $waitingEnabled ? 'waiting' : 'rejected';
    }
}
