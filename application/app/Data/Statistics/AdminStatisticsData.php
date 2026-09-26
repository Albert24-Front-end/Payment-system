<?php

namespace App\Data\Statistics;

use Carbon\CarbonImmutable;

readonly class AdminStatisticsData extends TerminalStatisticsData
{
    public function __construct(
        string $period,
        CarbonImmutable $from,
        CarbonImmutable $to,
        public string $group_by,
        public int $page = 1,
        public int $per_page = 10,
    ) {
        parent::__construct($period, $from, $to);
    }
}
