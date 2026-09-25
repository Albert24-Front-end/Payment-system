<?php

namespace App\Http\Requests\Statistics;

use App\Data\Statistics\TerminalStatisticsData;

class TerminalStatisticsRequest extends StatisticsRequest
{
    public function toDTO(): TerminalStatisticsData
    {
        $data = $this->validated();
        $range = $this->rangeData();

        return new TerminalStatisticsData(
            $data['period'],
            $range->from,
            $range->to,
        );
    }
}
