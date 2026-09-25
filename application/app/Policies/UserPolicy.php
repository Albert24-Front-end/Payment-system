<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewStatistics(User $user): bool
    {
        return $user->status !== User::STATUS_BANNED && $user->hasPermission('statistics.view');
    }

    // действие viewList может совершить только юзер, обладающий разрешением users.view
    // привязываем политику как мидлвар в api.php к конкретному url
    public function viewList(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function banUser(User $userWhoBans, User $userToBan): bool
    {
        return $userWhoBans->hasPermission('users.ban') && $userToBan->id !== $userWhoBans->id;
    }
}
