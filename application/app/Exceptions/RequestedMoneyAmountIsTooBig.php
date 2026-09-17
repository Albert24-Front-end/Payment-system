<?php

namespace App\Exceptions;

class RequestedMoneyAmountIsTooBig extends \Exception
{
    public function __construct()
    {
        parent::__construct("Requested money amount is too big");
    }
}
