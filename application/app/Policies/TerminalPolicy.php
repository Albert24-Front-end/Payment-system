<?php

namespace App\Policies;

use App\Models\Terminal;
use App\Models\User;

class TerminalPolicy
{
    public function viewStatistics(User $user, Terminal $terminal): bool
    {
        return $user->status !== User::STATUS_BANNED && $user->id === $terminal->user_id;
    }

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function update(User $user, Terminal $terminal)
    {
        return $user->id === $terminal->user_id;
    }

    public function delete(User $user, Terminal $terminal)
    {
        return $user->id === $terminal->user_id;
    }

    public function viewSecretKey(User $user, Terminal $terminal): bool
    {
        return $user->id === $terminal->user_id;
    }
}
