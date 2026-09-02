<?php

namespace App\Data\Terminals;

readonly class TerminalData
{
    public function __construct(
        public string $name,
        public string $success_url,
        public string $fail_url,
        public string $webhook_url,

    )
    {

    }
}
