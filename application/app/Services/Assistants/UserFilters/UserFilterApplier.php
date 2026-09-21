<?php

namespace App\Services\Assistants\UserFilters;

use Illuminate\Database\Eloquent\Builder;

class UserFilterApplier
{
    const FILTER_CLASSES = [
        EmailFilter::class,
        CreateFromFilter::class,
        TerminalFilter::class,
    ];

    static public function applyFilters(Builder $builder, array $filters): Builder
    {
        foreach (self::FILTER_CLASSES as $filter) {
            $builder = (new $filter)->alterBuilder($builder, $filters);
        }
        return $builder;
    }
}
