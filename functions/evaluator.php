<?php
/**
 * Bộ đánh giá bật/tắt tính năng. Không dùng CSDL, không dùng số ngẫu nhiên
 * nên cùng đầu vào luôn cho cùng kết quả.
 *
 * Cấu hình một tính năng (mảng chuẩn):
 *   ['default' => bool, 'rules' => [
 *       ['priority'=>1, 'type'=>'user_list',  'users'=>['user0001'], 'value'=>true],
 *       ['priority'=>2, 'type'=>'group',      'group'=>'beta', 'percentage'=>50, 'value'=>true],
 *       ['priority'=>3, 'type'=>'percentage', 'percentage'=>10, 'value'=>true],
 *   ]]
 * Quy tắc có priority nhỏ được xét trước; quy tắc đầu tiên khớp quyết định kết quả.
 * Không quy tắc nào khớp thì dùng 'default'.
 */

/** Xô 0..99 của một người dùng, ổn định theo (tính năng, môi trường, người dùng). */
function ffmBucket(string $flagKey, string $envKey, string $userId): int
{
    return crc32($flagKey . ':' . $envKey . ':' . $userId) % 100;
}

/** Quy tắc tỷ lệ: 0% không ai được bật, 100% ai cũng được bật. */
function ffmInRollout(int $bucket, int $percentage): bool
{
    return $bucket < $percentage;
}

/**
 * @param array  $flagCfg    cấu hình một tính năng (xem mô tả ở đầu tệp)
 * @param array  $userGroups danh sách group_key mà người dùng thuộc về
 * @return array ['enabled' => bool, 'reason' => string]
 */
function ffmEvaluate(array $flagCfg, string $flagKey, string $envKey, string $userId, array $userGroups = []): array
{
    $rules = $flagCfg['rules'] ?? [];
    // Sắp xếp ổn định theo priority (cùng priority giữ nguyên thứ tự khai báo)
    $indexed = [];
    foreach (array_values($rules) as $i => $r) {
        $indexed[] = [$r, $i];
    }
    usort($indexed, function ($a, $b) {
        return [(int)$a[0]['priority'], $a[1]] <=> [(int)$b[0]['priority'], $b[1]];
    });

    $bucket = ffmBucket($flagKey, $envKey, $userId);
    foreach ($indexed as [$rule]) {
        $matched = false;
        switch ($rule['type']) {
            case 'user_list':
                $matched = in_array($userId, $rule['users'] ?? [], true);
                break;
            case 'group':
                $matched = in_array($rule['group'] ?? '', $userGroups, true)
                    && ffmInRollout($bucket, (int)($rule['percentage'] ?? 100));
                break;
            case 'percentage':
                $matched = ffmInRollout($bucket, (int)($rule['percentage'] ?? 0));
                break;
        }
        if ($matched) {
            return ['enabled' => (bool)$rule['value'], 'reason' => 'rule:' . $rule['type'] . '#p' . (int)$rule['priority']];
        }
    }
    return ['enabled' => (bool)($flagCfg['default'] ?? false), 'reason' => 'default'];
}
