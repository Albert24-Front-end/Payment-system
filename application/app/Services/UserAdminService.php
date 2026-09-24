<?php

namespace App\Services;

use App\Contracts\AuditLogContract;
use App\Models\User;
use App\Services\Assistants\UserFilters\UserFilterApplier;
use Illuminate\Database\Eloquent\Builder;

class UserAdminService
{
    public function __construct(readonly private AuditLogContract $auditLog)
    {

    }
    public function getUserList($perPage = 10, array $filters = [])
    {
        $query = User::query()->orderBy("users.created_at", "DESC");
        $query = UserFilterApplier::applyFilters($query, $filters);
        return $query->paginate($perPage);
        /*return User::orderBy("users.created_at", "DESC")
            ->when($filters["email"] ?? null, fn(Builder $query, $email) => $query->whereLike("email", "%{$email}%"))
            ->when($filters["terminal_id"] ?? null, fn(Builder $query, $terminal_id) => $query->hasTerminal($terminal_id))
            ->when($filters["terminal_name"] ?? null, fn(Builder $query, $terminal_name) => $query->hasTerminalWithName($terminal_name))
            ->when($filters["created_from"] ?? null, fn(Builder $query, $created_from) => $query->where("users.created_at", ">=", $created_from))
            // уже очень много запросов в сервисе
            ->paginate($perPage);
        */
    }

    public function banUser(User $admin, User $userToBan): void
    {
        $userToBan->status = User::STATUS_BANNED;
        $userToBan->save();
        $this->auditLog->log("user-ban", $userToBan->id, $admin->id, null);
    }
}
