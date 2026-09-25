<?php

namespace App\Services\Statistics\Periods;

use Carbon\CarbonImmutable;

class MonthPeriodStrategy implements PeriodStrategy
{
    public function name(): string
    {
        return 'month';
    }

    public function start(CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfMonth();
    }

    public function nextStart(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addMonth();
    }

    public function sqlBucketExpression(): string
    {
        // created_at is a timestamp without time zone containing UTC values.
        return "date_trunc('month', payments.created_at)::date";
    }
}
