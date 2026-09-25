<?php

namespace App\Http\Requests\Statistics;

use App\Data\Statistics\TerminalStatisticsData;
use App\Services\Statistics\Periods\PeriodStrategyFactory;
use App\Services\Statistics\PeriodSeriesFiller;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LengthException;

abstract class StatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Routes enforce authentication, banned-user restrictions and policies.
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'string', Rule::in(PeriodStrategyFactory::supported())],
            'from' => ['required', 'date', 'date_format:Y-m-d'],
            'to' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'This parameter is not supported.');
            }
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            try {
                (new PeriodSeriesFiller)->axis($this->rangeData());
            } catch (LengthException $exception) {
                $validator->errors()->add('to', $exception->getMessage());
            }
        }];
    }

    protected function rangeData(): TerminalStatisticsData
    {
        return new TerminalStatisticsData(
            $this->string('period')->toString(),
            CarbonImmutable::createFromFormat('!Y-m-d', $this->input('from'), 'UTC'),
            CarbonImmutable::createFromFormat('!Y-m-d', $this->input('to'), 'UTC'),
        );
    }
}
