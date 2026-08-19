<?php

namespace App\Listeners;

use App\Services\AuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendVerificationEmail
{
    /**
     * Create the event listener.
     */
    public function __construct(private AuthService $authService)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        $this->authService->sendVerificationEmail($event->user);
    }
}
