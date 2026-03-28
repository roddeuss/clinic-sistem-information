<?php

namespace App\Modules\MedicalRecords\Exceptions;

use RuntimeException;

class MedicalRecordManagementException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly string $errorKey = 'medical_record_management'
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
