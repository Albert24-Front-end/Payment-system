<?php

namespace App\Data\Payments;

readonly class PaymentData
{
    public function __construct(
        public int $terminal_id,
        public string $order_id,
        public string $description,
        public int $amount,
    )
    {}
}
