<?php

declare(strict_types=1);

namespace Domain\Occurrence\Enums;

use Domain\Shared\Exceptions\DomainException;

enum OccurrenceStatus: string
{
    case REPORTED = 'reported';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CANCELLED = 'cancelled';

    /**
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::REPORTED => [self::IN_PROGRESS, self::CANCELLED],
            self::IN_PROGRESS => [self::RESOLVED, self::CANCELLED],
            self::RESOLVED => [],
            self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $targetStatus): bool
    {
        return in_array($targetStatus, $this->allowedTransitions(), true);
    }

    /**
     * @throws DomainException
     */
    public function validateTransitionTo(self $targetStatus): void
    {
        if (!$this->canTransitionTo($targetStatus)) {
            throw new DomainException(
                "Cannot transition from {$this->value} to {$targetStatus->value}"
            );
        }
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::RESOLVED, self::CANCELLED], true);
    }

    /**
     * @throws DomainException
     */
    public static function fromString(string $status): self
    {
        return self::tryFrom($status)
            ?? throw new DomainException("Invalid occurrence status: {$status}");
    }
}

