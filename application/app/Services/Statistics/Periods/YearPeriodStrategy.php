<?php

namespace App\Services\Statistics\Periods;

use Carbon\CarbonImmutable;

class YearPeriodStrategy implements PeriodStrategy
{
    public function name(): string
    {
        return 'year';
    }

    public function start(CarbonImmutable $date): CarbonImmutable
    {
        return $date->startOfYear();
    }

    public function nextStart(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addYear();
    }

    public function sqlBucketExpression(): string
    {
        // created_at is a timestamp without time zone containing UTC values.
        return "date_trunc('year', payments.created_at)::date";
    }
}
