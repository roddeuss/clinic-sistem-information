<?php

namespace App\Modules\VitalSigns\Exceptions;

use RuntimeException;

class VitalSignManagementException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly string $errorKey = 'vital_sign_management'
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
