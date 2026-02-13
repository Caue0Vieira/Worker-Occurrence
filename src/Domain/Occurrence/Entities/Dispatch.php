<?php

declare(strict_types=1);

namespace Domain\Occurrence\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Exception;

class Dispatch
{
    private ?string $statusName = null;
    private ?bool $statusIsActive = null;

    private function __construct(
        private readonly Uuid              $id,
        private readonly Uuid              $occurrenceId,
        private readonly string            $resourceCode,
        private readonly string            $statusCode,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    )
    {
    }

    public static function create(Uuid $occurrenceId, string $resourceCode): self
    {
        $now = new DateTimeImmutable();

        return new self(
            id: Uuid::generate(),
            occurrenceId: $occurrenceId,
            resourceCode: $resourceCode,
            statusCode: 'assigned',
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'occurrence_id' => $this->occurrenceId->toString(),
            'resource_code' => $this->resourceCode,
            'status_code' => $this->statusCode,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),

            'status_name' => $this->statusName,
            'status_is_active' => $this->statusIsActive,
        ];
    }

    /**
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        $dispatch = new self(
            id: Uuid::fromString($data['id']),
            occurrenceId: Uuid::fromString($data['occurrence_id']),
            resourceCode: $data['resource_code'],
            statusCode: $data['status_code'],
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
        );

        $dispatch->statusName = $data['status_name'] ?? null;
        $dispatch->statusIsActive = array_key_exists('status_is_active', $data)
            ? (bool)$data['status_is_active']
            : null;

        return $dispatch;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function occurrenceId(): Uuid
    {
        return $this->occurrenceId;
    }

    public function resourceCode(): string
    {
        return $this->resourceCode;
    }

    public function statusCode(): string
    {
        return $this->statusCode;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function statusName(): ?string
    {
        return $this->statusName;
    }

    public function isActive(): bool
    {
        return $this->statusIsActive ?? ($this->statusCode !== 'closed');
    }
}

