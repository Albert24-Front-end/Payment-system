<?php

namespace App\Services\Statistics\Periods;

use Carbon\CarbonImmutable;

class DayPeriodStrategy implements PeriodStrategy
{
    public function name(): string
    {
        return 'day';
    }

    public function start(CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfDay();
    }

    public function nextStart(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addDay();
    }

    public function sqlBucketExpression(): string
    {
        // created_at is a timestamp without time zone containing UTC values.
        return "date_trunc('day', payments.created_at)::date";
    }
}
