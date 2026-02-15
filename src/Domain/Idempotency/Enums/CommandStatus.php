<?php

declare(strict_types=1);

namespace Domain\Idempotency\Enums;

use Domain\Shared\Exceptions\DomainException;

enum CommandStatus: string
{
    case RECEIVED = 'RECEIVED';
    case ENQUEUED = 'ENQUEUED';
    case PROCESSING = 'PROCESSING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';

    public function shouldProcessInWorker(): bool
    {
        return match ($this) {
            self::RECEIVED, self::ENQUEUED, self::FAILED => true,
            self::PROCESSING, self::SUCCEEDED => false,
        };
    }

    /**
     * @throws DomainException
     */
    public static function fromString(string $status): self
    {
        $normalized = match (strtolower($status)) {
            'pending' => self::ENQUEUED->value,
            'processed' => self::SUCCEEDED->value,
            'failed' => self::FAILED->value,
            default => $status,
        };

        return self::tryFrom($normalized)
            ?? throw new DomainException("Invalid command status: $status");
    }
}

