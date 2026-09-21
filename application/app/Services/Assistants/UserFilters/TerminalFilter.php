<?php
namespace App\Services\Assistants\UserFilters;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Assistants\UserFilters\UserFilterContract;

class TerminalFilter implements UserFilterContract
{

    public function alterBuilder(Builder $builder, array $filters): Builder
    {
        return $builder
            ->when($filters["terminal_id"] ?? null, fn(Builder $query, $terminal_id) => $query->hasTerminal($terminal_id))
            ->when($filters["terminal_name"] ?? null, fn(Builder $query, $terminal_name) => $query->hasTerminalWithName($terminal_name));
    }
}
