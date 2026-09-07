<?php

namespace App\Console\Commands;

use App\Services\ExpiredPaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:cancel-expired-payments')]
#[Description('cancels expired payments')]
class CancelExpiredPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExpiredPaymentService $service)
    {
        // вся логика в сервисе - так лучше, чем писать все здесь, в команде
        $service->cancelExpiredPayments();
    }
}
