<?php

namespace App\Data\Statistics;

use Carbon\CarbonImmutable;

readonly class TerminalStatisticsData
{
    public function __construct(
        public string $period,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public function start(): string
    {
        return $this->from->startOfDay()->toDateTimeString();
    }

    public function endExclusive(): string
    {
        return $this->to->addDay()->startOfDay()->toDateTimeString();
    }

    public function metadata(): array
    {
        return [
            'period' => $this->period,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'timezone' => 'UTC',
            'money_unit' => 'tiyin',
        ];
    }
}
