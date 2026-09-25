<?php

namespace App\Services\Statistics;

use OverflowException;

class StatisticsMath
{
    public static function integer(int|string $value): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($integer === false) {
            throw new OverflowException('Statistics aggregate is outside the supported integer range.');
        }

        return $integer;
    }

    public static function divide(int $amount, int $count): int
    {
        if ($count === 0) {
            return 0;
        }

        // Quotient/remainder avoids floats and overflow from adding half the divisor.
        return intdiv($amount, $count)
            + (int) ($amount % $count >= intdiv($count, 2) + $count % 2);
    }
}
