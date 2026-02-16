<?php

declare(strict_types=1);

namespace Domain\Dispatch\Service;

use DateTimeImmutable;
use Domain\Audit\Repositories\AuditLoggerInterface;
use Domain\Dispatch\Entities\Dispatch;
use Domain\Dispatch\Enums\DispatchStatus;
use Domain\Dispatch\Repositories\DispatchRepositoryInterface;
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
        // Verifica se já existe despacho na mesma ocorrência
        $existingDispatchInSameOccurrence = $this->dispatchRepository->findByOccurrenceIdAndResourceCode(
            occurrenceId: $occurrenceId,
            resourceCode: $resourceCode
        );

        if ($existingDispatchInSameOccurrence !== null) {
            throw new DomainException(
                "Já existe um despacho com o resource_code '$resourceCode' na ocorrência '{$occurrenceId->toString()}'"
            );
        }

        // Verifica se existe despacho com o mesmo resourceCode em outra ocorrência
        $existingDispatchInOtherOccurrence = $this->dispatchRepository->findByResourceCode($resourceCode);

        if ($existingDispatchInOtherOccurrence !== null) {
            $existingOccurrenceId = $existingDispatchInOtherOccurrence->occurrenceId()->toString();
            $existingStatus = DispatchStatus::fromString($existingDispatchInOtherOccurrence->statusCode());

            // Se o despacho existente está em uma ocorrência diferente
            if ($existingOccurrenceId !== $occurrenceId->toString()) {
                // Verifica se o status bloqueia a criação em outra ocorrência
                if (in_array($existingStatus, [DispatchStatus::ASSIGNED, DispatchStatus::EN_ROUTE, DispatchStatus::ON_SITE], true)) {
                    throw new DomainException(
                        "Não é possível criar despacho com resource_code '$resourceCode' na ocorrência '{$occurrenceId->toString()}'. " .
                        "O recurso já está atribuído à ocorrência '$existingOccurrenceId' com status '{$existingStatus->value}'."
                    );
                }
            }
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

