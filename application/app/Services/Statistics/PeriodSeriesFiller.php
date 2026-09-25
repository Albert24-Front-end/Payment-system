<?php

namespace App\Services\Statistics;

use App\Data\Statistics\TerminalStatisticsData;
use App\Services\Statistics\Periods\PeriodStrategyFactory;
use LengthException;

class PeriodSeriesFiller
{
    public const MAX_BUCKETS = 366;

    public function axis(TerminalStatisticsData $data): array
    {
        $strategy = PeriodStrategyFactory::make($data->period);
        $last = $strategy->start($data->to);
        $axis = [];
        for ($date = $strategy->start($data->from); $date <= $last; $date = $strategy->nextStart($date)) {
            if (count($axis) === self::MAX_BUCKETS) {
                throw new LengthException('The range must contain no more than 366 calendar periods.');
            }
            $axis[] = $date->toDateString();
        }

        return $axis;
    }

    public function fill(array $axis, iterable $rows, bool $income): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->period_start] = $row;
        }

        $series = [];
        foreach ($axis as $key) {
            $row = $indexed[$key] ?? null;
            $amount = StatisticsMath::integer($row?->payment_amount ?? 0);
            $count = StatisticsMath::integer($row?->payment_count ?? 0);
            $series[] = [
                'period_start' => $key,
                'payment_count' => $count,
                'payment_amount' => $amount,
                $income ? 'income' : 'average_check' => StatisticsMath::divide($amount, $income ? 10 : $count),
            ];
        }

        return $series;
    }
}
