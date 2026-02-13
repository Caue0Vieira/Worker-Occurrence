<?php

declare(strict_types=1);

namespace Domain\Occurrence\Enums;

use Domain\Shared\Exceptions\DomainException;

enum DispatchStatus: string
{
    case ASSIGNED = 'assigned';
    case EN_ROUTE = 'en_route';
    case ON_SITE = 'on_site';
    case CLOSED = 'closed';

    /**
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ASSIGNED => [self::EN_ROUTE, self::CLOSED],
            self::EN_ROUTE => [self::ON_SITE, self::CLOSED],
            self::ON_SITE => [self::CLOSED],
            self::CLOSED => [],
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

    public function isActive(): bool
    {
        return $this !== self::CLOSED;
    }

    /**
     * @throws DomainException
     */
    public static function fromString(string $status): self
    {
        return self::tryFrom($status)
            ?? throw new DomainException("Invalid dispatch status: {$status}");
    }
}

