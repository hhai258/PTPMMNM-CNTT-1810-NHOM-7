<?php
/** Bộ kiểm thử tối giản (không cần Composer/PHPUnit). */
$GLOBALS['ffm_pass'] = 0;
$GLOBALS['ffm_fail'] = 0;

function check($cond, string $name): void
{
    if ($cond) {
        $GLOBALS['ffm_pass']++;
        echo "  ✓ $name\n";
    } else {
        $GLOBALS['ffm_fail']++;
        echo "  ✗ $name\n";
    }
}

function same($expected, $actual, string $name): void
{
    check($expected === $actual, $name . ($expected === $actual ? '' : ' (mong đợi ' . json_encode($expected) . ', nhận ' . json_encode($actual) . ')'));
}

/** Kỳ vọng $fn ném ngoại lệ thuộc $class. */
function throwsA(string $class, callable $fn, string $name): void
{
    try {
        $fn();
        check(false, $name . ' (không ném ngoại lệ)');
    } catch (Throwable $e) {
        check($e instanceof $class, $name . ($e instanceof $class ? '' : ' (nhận ' . get_class($e) . ': ' . $e->getMessage() . ')'));
    }
}

function section(string $title): void
{
    echo "\n== $title ==\n";
}

function finish(): void
{
    echo "\nKết quả: {$GLOBALS['ffm_pass']} đạt, {$GLOBALS['ffm_fail']} lỗi\n";
    exit($GLOBALS['ffm_fail'] > 0 ? 1 : 0);
}
