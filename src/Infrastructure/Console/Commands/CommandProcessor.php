<?php

declare(strict_types=1);

namespace Infrastructure\Console\Commands;

use DateTimeImmutable;
use Domain\Dispatch\Service\DispatchService;
use Domain\Occurrence\Services\OccurrenceService;
use Domain\Shared\ValueObjects\Uuid;
use Infrastructure\Cache\OccurrenceListCacheInvalidator;
use InvalidArgumentException;

readonly class CommandProcessor
{
    public function __construct(
        private OccurrenceService $occurrenceService,
        private DispatchService $dispatchService,
        private OccurrenceListCacheInvalidator $occurrenceListCacheInvalidator,
    )
    {
    }

    public function process(string $commandType, array $data): array
    {
        return match ($commandType) {
            'create_occurrence' => $this->processCreateOccurrence($data),
            'start_occurrence' => $this->processStartOccurrence($data),
            'resolve_occurrence' => $this->processResolveOccurrence($data),
            'create_dispatch' => $this->processCreateDispatch($data),
            'close_dispatch' => $this->processCloseDispatch($data),
            'update_dispatch_status' => $this->processUpdateDispatchStatus($data),
            default => throw new InvalidArgumentException("Unsupported command type: {$commandType}"),
        };
    }

    /**
     * Processa criação de ocorrência
     */
    private function processCreateOccurrence(array $data): array
    {
        $reportedAt = new DateTimeImmutable($data['reportedAt']);

        $occurrence = $this->occurrenceService->createOccurrence(
            externalId: $data['externalId'],
            typeCode: $data['type'],
            description: $data['description'],
            reportedAt: $reportedAt
        );
        $this->occurrenceListCacheInvalidator->invalidate();

        return [
            'occurrenceId' => $occurrence->id()->toString(),
            'externalId' => $occurrence->externalId(),
            'status' => $occurrence->statusCode(),
            'commandId' => $data['commandId'] ?? null,
        ];
    }

    /**
     * Processa início de ocorrência
     */
    private function processStartOccurrence(array $data): array
    {
        $occurrenceId = Uuid::fromString($data['occurrenceId']);
        $occurrence = $this->occurrenceService->findByIdForUpdate($occurrenceId);

        if ($occurrence === null) {
            throw new InvalidArgumentException("Occurrence not found: {$data['occurrenceId']}");
        }

        $updated = $this->occurrenceService->startOccurrence($occurrence);
        $this->occurrenceListCacheInvalidator->invalidate();

        return [
            'occurrenceId' => $updated->id()->toString(),
            'status' => $updated->statusCode(),
            'commandId' => $data['commandId'] ?? null,
        ];
    }

    /**
     * Processa resolução de ocorrência
     */
    private function processResolveOccurrence(array $data): array
    {
        $occurrenceId = Uuid::fromString($data['occurrenceId']);
        $occurrence = $this->occurrenceService->findByIdForUpdate($occurrenceId);

        if ($occurrence === null) {
            throw new InvalidArgumentException("Occurrence not found: {$data['occurrenceId']}");
        }

        $updated = $this->occurrenceService->resolveOccurrence($occurrence);
        $this->occurrenceListCacheInvalidator->invalidate();

        return [
            'occurrenceId' => $updated->id()->toString(),
            'status' => $updated->statusCode(),
            'commandId' => $data['commandId'] ?? null,
        ];
    }

    /**
     * Processa criação de despacho
     */
    private function processCreateDispatch(array $data): array
    {
        $occurrenceId = Uuid::fromString($data['occurrenceId']);

        $dispatch = $this->dispatchService->createDispatch(
            occurrenceId: $occurrenceId,
            resourceCode: $data['resourceCode']
        );

        return [
            'dispatchId' => $dispatch->id()->toString(),
            'occurrenceId' => $dispatch->occurrenceId()->toString(),
            'resourceCode' => $dispatch->resourceCode(),
            'status' => $dispatch->statusCode(),
            'commandId' => $data['commandId'] ?? null,
        ];
    }

    /**
     * Processa fechamento de despacho
     */
    private function processCloseDispatch(array $data): array
    {
        $dispatchId = Uuid::fromString($data['dispatchId']);
        $dispatch = $this->dispatchService->findById($dispatchId);

        if ($dispatch === null) {
            throw new InvalidArgumentException("Dispatch not found: {$data['dispatchId']}");
        }

        $updated = $this->dispatchService->close($dispatch);

        return [
            'dispatchId' => $updated->id()->toString(),
            'status' => $updated->statusCode(),
            'commandId' => $data['commandId'] ?? null,
        ];
    }

    /**
     * Processa atualização de status de despacho
     */
    private function processUpdateDispatchStatus(array $data): array
    {
        $dispatchId = Uuid::fromString($data['dispatchId']);
        $dispatch = $this->dispatchService->findById($dispatchId);

        if ($dispatch === null) {
            throw new InvalidArgumentException("Dispatch not found: {$data['dispatchId']}");
        }

        $updated = $this->dispatchService->updateStatus($dispatch, $data['statusCode']);

        return [
            'dispatchId' => $updated->id()->toString(),
            'status' => $updated->statusCode(),
            'commandId' => $data['commandId'] ?? null,
        ];
    }
}
