<?php

namespace App\Services\Statistics\Periods;

use InvalidArgumentException;

class PeriodStrategyFactory
{
    private const STRATEGIES = [
        'day' => DayPeriodStrategy::class,
        'month' => MonthPeriodStrategy::class,
        'year' => YearPeriodStrategy::class,
    ];

    public static function supported(): array
    {
        return array_keys(self::STRATEGIES);
    }

    public static function make(string $period): PeriodStrategy
    {
        $class = self::STRATEGIES[$period] ?? throw new InvalidArgumentException('Unsupported statistics period.');

        return new $class;
    }
}
