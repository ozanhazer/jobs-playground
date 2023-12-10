<?php

namespace App\Exceptions;

class ThrottleableException extends \Exception
{
    public bool $recoverable;

    public function __construct(bool $recoverable)
    {
        $this->recoverable = $recoverable;
        parent::__construct($this->recoverable ? 'Recoverable error' : 'Unrecoverable error (no retries)');
    }
}
