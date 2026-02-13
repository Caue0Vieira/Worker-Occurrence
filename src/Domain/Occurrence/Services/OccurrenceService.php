<?php

declare(strict_types=1);

namespace Domain\Occurrence\Services;

use DateTimeImmutable;
use Domain\Audit\Repositories\AuditLoggerInterface;
use Domain\Occurrence\Entities\Occurrence;
use Domain\Occurrence\Enums\OccurrenceStatus;
use Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;

readonly class OccurrenceService
{

    public function __construct(
        private OccurrenceRepositoryInterface $occurrenceRepository,
        private AuditLoggerInterface $auditLogger
    ) {}

    public function createOccurrence(
        string $externalId,
        string $typeCode,
        string $description,
        DateTimeImmutable $reportedAt
    ): Occurrence {
        $occurrence = Occurrence::create(
            externalId: $externalId,
            typeCode: $typeCode,
            description: $description,
            reportedAt: $reportedAt
        );

        return $this->occurrenceRepository->save($occurrence);
    }

    public function findByIdForUpdate(\Domain\Shared\ValueObjects\Uuid $id): ?Occurrence
    {
        return $this->occurrenceRepository->findByIdForUpdate($id);
    }

    public function startOccurrence(Occurrence $occurrence): Occurrence
    {
        $before = ['status_code' => $occurrence->statusCode()];
        $updated = $this->transition($occurrence, OccurrenceStatus::IN_PROGRESS);
        $after = ['status_code' => $updated->statusCode()];

        $this->auditLogger->log(
            entityType: 'occurrence',
            entityId: $updated->id()->toString(),
            action: 'status_changed',
            before: $before,
            after: $after,
            meta: ['transition' => 'reported -> in_progress']
        );

        return $updated;
    }

    public function resolveOccurrence(Occurrence $occurrence): Occurrence
    {
        $before = ['status_code' => $occurrence->statusCode()];
        $updated = $this->transition($occurrence, OccurrenceStatus::RESOLVED);
        $after = ['status_code' => $updated->statusCode()];

        $this->auditLogger->log(
            entityType: 'occurrence',
            entityId: $updated->id()->toString(),
            action: 'status_changed',
            before: $before,
            after: $after,
            meta: ['transition' => 'in_progress -> resolved']
        );

        return $updated;
    }

    private function transition(Occurrence $occurrence, OccurrenceStatus $newStatus): Occurrence
    {
        $currentStatus = OccurrenceStatus::fromString($occurrence->statusCode());
        $currentStatus->validateTransitionTo($newStatus);

        $data = $occurrence->toArray();
        $data['status_code'] = $newStatus->value;
        $data['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $updated = Occurrence::fromArray($data);

        return $this->occurrenceRepository->save($updated);
    }
}

