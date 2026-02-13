<?php

declare(strict_types=1);

namespace Domain\Occurrence\Services;

use DateTimeImmutable;
use Domain\Audit\Repositories\AuditLoggerInterface;
use Domain\Occurrence\Entities\Dispatch;
use Domain\Occurrence\Enums\DispatchStatus;
use Domain\Occurrence\Repositories\DispatchRepositoryInterface;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Exception;

readonly class DispatchService
{
    public function __construct(
        private DispatchRepositoryInterface $dispatchRepository,
        private AuditLoggerInterface $auditLogger
    )
    {
    }

    /**
     * @throws DomainException
     * @throws Exception
     */
    public function createDispatch(Uuid $occurrenceId, string $resourceCode): Dispatch {
        // Validação de negócio: verificar se já existe despacho com mesmo resource_code na ocorrência
        $existingDispatch = $this->dispatchRepository->findByOccurrenceIdAndResourceCode(
            occurrenceId: $occurrenceId,
            resourceCode: $resourceCode
        );

        if ($existingDispatch !== null) {
            throw new DomainException(
                "Já existe um despacho com o resource_code '{$resourceCode}' na ocorrência '{$occurrenceId->toString()}'"
            );
        }

        $dispatch = Dispatch::create(
            occurrenceId: $occurrenceId,
            resourceCode: $resourceCode
        );

        return $this->dispatchRepository->save($dispatch);
    }

    public function findById(Uuid $id): ?Dispatch
    {
        return $this->dispatchRepository->findById($id);
    }

    /**
     * @throws Exception
     */
    public function findByOccurrenceIdAndResourceCode(Uuid $occurrenceId, string $resourceCode): ?Dispatch
    {
        return $this->dispatchRepository->findByOccurrenceIdAndResourceCode($occurrenceId, $resourceCode);
    }

    /**
     * @throws Exception
     */
    public function close(Dispatch $dispatch): Dispatch
    {
        return $this->updateStatus($dispatch, DispatchStatus::CLOSED->value);
    }

    public function updateStatus(Dispatch $dispatch, string $statusCode): Dispatch
    {
        $newStatus = DispatchStatus::fromString($statusCode);
        $before = ['status_code' => $dispatch->statusCode()];
        $updated = $this->transition($dispatch, $newStatus);
        $after = ['status_code' => $updated->statusCode()];

        $this->auditLogger->log(
            entityType: 'dispatch',
            entityId: $updated->id()->toString(),
            action: 'status_changed',
            before: $before,
            after: $after,
            meta: ['transition' => $dispatch->statusCode() . ' -> ' . $updated->statusCode()]
        );

        return $updated;
    }

    private function transition(Dispatch $dispatch, DispatchStatus $newStatus): Dispatch
    {
        $currentStatus = DispatchStatus::fromString($dispatch->statusCode());
        $currentStatus->validateTransitionTo($newStatus);

        $data = $dispatch->toArray();
        $data['status_code'] = $newStatus->value;
        $data['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $updated = Dispatch::fromArray($data);

        return $this->dispatchRepository->save($updated);
    }
}

