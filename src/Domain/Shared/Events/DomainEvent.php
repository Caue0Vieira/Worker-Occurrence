<?php

declare(strict_types=1);

namespace Domain\Shared\Events;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;

interface DomainEvent
{
    public function eventId(): Uuid;

    public function eventName(): string;

    public function occurredAt(): DateTimeImmutable;

    public function toArray(): array;
}

