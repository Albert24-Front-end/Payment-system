<?php

namespace App\Services\Statistics;

use App\Data\Statistics\TerminalStatisticsData;
use App\Models\Payment;
use App\Models\Terminal;
use App\Services\Statistics\Periods\PeriodStrategyFactory;

class MerchantStatisticsService
{
    public function __construct(private readonly PeriodSeriesFiller $filler) {}

    public function getStatistics(Terminal $terminal, TerminalStatisticsData $data): array
    {
        $bucket = PeriodStrategyFactory::make($data->period)->sqlBucketExpression();
        $rows = Payment::query()
            ->where('terminal_id', $terminal->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('created_at', '>=', $data->start())
            ->where('created_at', '<', $data->endExclusive())
            ->selectRaw("$bucket AS period_start, COUNT(*) AS payment_count, SUM(amount) AS payment_amount")
            ->groupByRaw($bucket)
            ->toBase()->get();

        return [
            'success' => true,
            'data' => $this->filler->fill($this->filler->axis($data), $rows, false),
            'meta' => $data->metadata(),
        ];
    }
}
