<?php

namespace App\Data\Payments;

readonly class PaymentProcessingData
{
    public function __construct(
        public int $status,
    ) {}
}
