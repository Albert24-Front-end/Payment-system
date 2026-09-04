<?php

namespace App\Exceptions;

class InvalidSignature extends \Exception
{
    public function __construct()
    {
        parent::__construct('Invalid signature');
    }
}
