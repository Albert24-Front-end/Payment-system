<?php
namespace App\Services\Assistants\UserFilters;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Assistants\UserFilters\UserFilterContract;

class CreateFromFilter implements UserFilterContract
{

    public function alterBuilder(Builder $builder, array $filters): Builder
    {
        return $builder->when($filters["created_from"] ?? null, fn(Builder $query, $created_from) => $query->where("users.created_at", ">=", $created_from));
    }
}
