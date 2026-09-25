<?php

namespace App\Services\Statistics;

use App\Data\Statistics\AdminStatisticsData;
use App\Models\Payment;
use App\Services\Statistics\Periods\PeriodStrategyFactory;

class AdminStatisticsService
{
    public function __construct(private readonly PeriodSeriesFiller $filler) {}

    public function getStatistics(AdminStatisticsData $data): array
    {
        // A join intentionally retains historical payments of soft-deleted terminals.
        $query = Payment::query()
            ->join('terminals', 'terminals.id', '=', 'payments.terminal_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->where('payments.created_at', '>=', $data->start())
            ->where('payments.created_at', '<', $data->endExclusive());

        if ($data->group_by === 'user') {
            $query->join('users', 'users.id', '=', 'terminals.user_id');
            $entity = 'users.id';
            $label = 'users.email';
        } else {
            $entity = 'terminals.id';
            $label = 'terminals.name';
        }

        // Paginate entities, not entity-period rows; only qualifying entities appear.
        $entities = (clone $query)
            ->selectRaw("$entity AS entity_id, $label AS entity_label")
            ->groupBy($entity, $label)
            ->orderBy($entity)
            ->toBase()->paginate($data->per_page, ['*'], 'page', $data->page);

        $axis = $this->filler->axis($data);
        $series = [];
        if ($entities->isNotEmpty()) {
            $bucket = PeriodStrategyFactory::make($data->period)->sqlBucketExpression();
            $rows = $query
                ->whereIn($entity, $entities->getCollection()->pluck('entity_id'))
                ->selectRaw("$entity AS entity_id, $bucket AS period_start, COUNT(*) AS payment_count, SUM(payments.amount) AS payment_amount")
                ->groupBy($entity)->groupByRaw($bucket)
                ->toBase()->get()->groupBy('entity_id');

            foreach ($entities as $item) {
                $series[] = [
                    'entity_id' => (int) $item->entity_id,
                    'entity_label' => $item->entity_label,
                    'periods' => $this->filler->fill($axis, $rows->get($item->entity_id, []), true),
                ];
            }
        }

        return [
            'success' => true,
            'data' => $series,
            'meta' => [
                ...$data->metadata(),
                'group_by' => $data->group_by,
                'current_page' => $entities->currentPage(),
                'per_page' => $entities->perPage(),
                'total' => $entities->total(),
                'last_page' => $entities->lastPage(),
            ],
        ];
    }
}
