<?php

namespace App\Modules\VisitRegistrations\Exceptions;

use RuntimeException;

class VisitRegistrationException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly string $errorKey = 'visit_registration'
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorKey(): string
    {
        return $this->errorKey;
    }
}
