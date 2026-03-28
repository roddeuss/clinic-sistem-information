<?php

namespace App\Modules\Queues\Exceptions;

use RuntimeException;

class QueueManagementException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly string $errorKey = 'queue_management'
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
