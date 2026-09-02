<?php

namespace App\Policies;

use App\Models\Terminal;
use App\Models\User;

class TerminalPolicy
{
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
