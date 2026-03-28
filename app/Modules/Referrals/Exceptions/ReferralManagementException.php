<?php

namespace App\Modules\Referrals\Exceptions;

use RuntimeException;

class ReferralManagementException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly string $errorKey = 'referral',
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
