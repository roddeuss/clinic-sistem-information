<?php

namespace App\Modules\DoctorLetters\Exceptions;

use RuntimeException;

class DoctorLetterManagementException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly string $errorKey = 'doctor_letter',
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
