<?php

namespace App\Http\Requests\Statistics;

use App\Data\Statistics\AdminStatisticsData;
use Illuminate\Validation\Rule;

class AdminStatisticsRequest extends StatisticsRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'group_by' => ['required', 'string', Rule::in(['terminal', 'user'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function toDTO(): AdminStatisticsData
    {
        $data = $this->validated();
        $range = $this->rangeData();

        return new AdminStatisticsData(
            $data['period'], $range->from, $range->to, $data['group_by'],
            (int) ($data['page'] ?? 1), (int) ($data['perPage'] ?? 10),
        );
    }
}
