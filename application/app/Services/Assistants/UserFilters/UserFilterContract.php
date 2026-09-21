<?php
namespace App\Services\Assistants\UserFilters;
use Illuminate\Database\Eloquent\Builder;

interface UserFilterContract
{
    public function alterBuilder(Builder $builder, array $filters): Builder;
}
