<?php

namespace Tests\Unit;

use App\Data\Statistics\TerminalStatisticsData;
use App\Services\Statistics\Periods\PeriodStrategyFactory;
use App\Services\Statistics\PeriodSeriesFiller;
use App\Services\Statistics\StatisticsMath;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LengthException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class StatisticsPeriodTest extends TestCase
{
    public function test_calendar_axes_include_leap_day_and_partial_months_and_years(): void
    {
        $filler = new PeriodSeriesFiller;
        foreach ([
            ['day', '2024-02-28', '2024-03-01', ['2024-02-28', '2024-02-29', '2024-03-01']],
            ['month', '2025-12-31', '2026-02-01', ['2025-12-01', '2026-01-01', '2026-02-01']],
            ['year', '2024-12-31', '2026-01-02', ['2024-01-01', '2025-01-01', '2026-01-01']],
        ] as [$period, $from, $to, $expected]) {
            $data = new TerminalStatisticsData($period, CarbonImmutable::parse($from, 'UTC'), CarbonImmutable::parse($to, 'UTC'));
            $this->assertSame($expected, $filler->axis($data));
        }
    }

    public function test_factory_rejects_unsupported_periods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PeriodStrategyFactory::make('week');
    }

    public function test_axis_rejects_more_than366_buckets(): void
    {
        $this->expectException(LengthException::class);
        (new PeriodSeriesFiller)->axis(new TerminalStatisticsData('day', CarbonImmutable::parse('2024-01-01'), CarbonImmutable::parse('2025-01-01')));
    }

    public function test_filling_preserves_order_and_rounds_integer_metrics(): void
    {
        $rows = [(object) ['period_start' => '2026-09-02', 'payment_count' => '2', 'payment_amount' => '15']];
        $axis = ['2026-09-01', '2026-09-02', '2026-09-03'];
        $filler = new PeriodSeriesFiller;
        $merchant = $filler->fill($axis, $rows, false);
        $admin = $filler->fill($axis, $rows, true);
        $this->assertSame($axis, array_column($merchant, 'period_start'));
        $this->assertSame([0, 8, 0], array_column($merchant, 'average_check'));
        $this->assertSame([0, 2, 0], array_column($admin, 'income'));
        $this->assertSame([0, 2, 0], array_column($merchant, 'payment_count'));
    }

    public function test_arithmetic_does_not_overflow_when_rounding_large_integers(): void
    {
        $this->assertSame(PHP_INT_MAX, StatisticsMath::integer((string) PHP_INT_MAX));
        $this->assertSame(PHP_INT_MAX, StatisticsMath::divide(PHP_INT_MAX, 1));
        $this->assertSame(intdiv(PHP_INT_MAX, 2) + 1, StatisticsMath::divide(PHP_INT_MAX, 2));
        $this->assertSame(0, StatisticsMath::divide(0, 0));
        $this->assertSame(1, StatisticsMath::divide(14, 10));
        $this->assertSame(2, StatisticsMath::divide(15, 10));
        $this->expectException(OverflowException::class);
        StatisticsMath::integer('9223372036854775808');
    }
}
