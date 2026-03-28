<?php

namespace App\Modules\MedicalServices\Exceptions;

use RuntimeException;

class MedicalServiceManagementException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly string $errorKey = 'medical_service',
    ) {
        parent::__construct($message, $status);
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
