<?php

namespace App\Services\Statistics\Periods;

use Carbon\CarbonImmutable;

interface PeriodStrategy
{
    public function name(): string;

    public function start(CarbonImmutable $date): CarbonImmutable;

    public function nextStart(CarbonImmutable $start): CarbonImmutable;

    public function sqlBucketExpression(): string;
}
