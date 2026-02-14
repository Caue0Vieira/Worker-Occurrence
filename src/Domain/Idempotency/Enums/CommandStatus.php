<?php

declare(strict_types=1);

namespace Domain\Idempotency\Enums;

use Domain\Shared\Exceptions\DomainException;

enum CommandStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case FAILED = 'failed';

    public function shouldDispatch(): bool
    {
        return match ($this) {
            self::PENDING, self::FAILED => true,
            self::PROCESSED => false,
        };
    }

    /**
     * @throws DomainException
     */
    public static function fromString(string $status): self
    {
        return self::tryFrom($status)
            ?? throw new DomainException("Invalid command status: $status");
    }
}

