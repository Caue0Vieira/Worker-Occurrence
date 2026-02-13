<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

final readonly class Uuid
{
    private function __construct(
        private UuidInterface $value
    ) {
    }

    public static function generate(): self
    {
        return new self(RamseyUuid::uuid7());
    }

    public static function fromString(string $uuid): self
    {
        if (!RamseyUuid::isValid($uuid)) {
            throw new InvalidArgumentException("Invalid UUID format: {$uuid}");
        }

        return new self(RamseyUuid::fromString($uuid));
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }
}

