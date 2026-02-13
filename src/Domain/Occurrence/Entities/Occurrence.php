<?php

declare(strict_types=1);

namespace Domain\Occurrence\Entities;

use DateTimeImmutable;
use Domain\Shared\ValueObjects\Uuid;
use Exception;

class Occurrence
{
    private array $dispatches = [];

    private ?string $typeName = null;
    private ?string $typeCategory = null;

    private ?string $statusName = null;
    private ?bool $statusIsFinal = null;

    private function __construct(
        private readonly Uuid              $id,
        private readonly string            $externalId,
        private readonly string            $typeCode,
        private readonly string            $statusCode,
        private readonly string            $description,
        private readonly DateTimeImmutable $reportedAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    )
    {
    }

    public static function create(
        string            $externalId,
        string            $typeCode,
        string            $description,
        DateTimeImmutable $reportedAt
    ): self
    {
        $now = new DateTimeImmutable();

        return new self(
            id: Uuid::generate(),
            externalId: $externalId,
            typeCode: $typeCode,
            statusCode: 'reported',
            description: $description,
            reportedAt: $reportedAt,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'external_id' => $this->externalId,
            'type_code' => $this->typeCode,
            'status_code' => $this->statusCode,
            'description' => $this->description,
            'reported_at' => $this->reportedAt->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),

            'type_name' => $this->typeName,
            'type_category' => $this->typeCategory,
            'status_name' => $this->statusName,
            'status_is_final' => $this->statusIsFinal,

            'dispatches' => array_map(static fn(Dispatch $d) => $d->toArray(), $this->dispatches),
        ];
    }

    /**
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        $occurrence = new self(
            id: Uuid::fromString($data['id']),
            externalId: $data['external_id'],
            typeCode: $data['type_code'],
            statusCode: $data['status_code'],
            description: $data['description'],
            reportedAt: new DateTimeImmutable($data['reported_at']),
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
        );

        $occurrence->typeName = $data['type_name'] ?? null;
        $occurrence->typeCategory = $data['type_category'] ?? null;
        $occurrence->statusName = $data['status_name'] ?? null;
        $occurrence->statusIsFinal = array_key_exists('status_is_final', $data)
            ? (bool)$data['status_is_final']
            : null;

        if (isset($data['dispatches']) && is_array($data['dispatches'])) {
            $occurrence->dispatches = array_map(
                static fn($d) => $d instanceof Dispatch ? $d : Dispatch::fromArray((array)$d),
                $data['dispatches']
            );
        }

        return $occurrence;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function typeCode(): string
    {
        return $this->typeCode;
    }

    public function typeName(): ?string
    {
        return $this->typeName;
    }

    public function typeCategory(): ?string
    {
        return $this->typeCategory;
    }

    public function statusCode(): string
    {
        return $this->statusCode;
    }

    public function statusName(): ?string
    {
        return $this->statusName;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function reportedAt(): DateTimeImmutable
    {
        return $this->reportedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function dispatches(): array
    {
        return $this->dispatches;
    }

    public function isFinalized(): bool
    {
        return $this->statusIsFinal ?? in_array($this->statusCode, ['resolved', 'cancelled'], true);
    }
}

