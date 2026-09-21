<?php
namespace App\Services\Assistants\UserFilters;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Assistants\UserFilters\UserFilterContract;

class EmailFilter implements UserFilterContract
{

    public function alterBuilder(Builder $builder, array $filters): Builder
    {
        // здесь код из сервиса
        $builder->when($filters["email"] ?? null, fn(Builder $query, $email) => $query->whereLike("email", "%{$email}%"));
        return $builder;
    }
}
