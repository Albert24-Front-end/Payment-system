<?php

namespace App\Data\Withdrawal;

class WithdrawalData
{
    public function __construct(
        public int $terminal_id,
        public int $amount,
        public string $bank_code,
        public string $account_number,
    )
    {}
}
