<?php

declare(strict_types=1);

namespace App\Jobs;

use Domain\Occurrence\Enums\OccurrenceStatus;
use Domain\Occurrence\Services\OccurrenceService;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Infrastructure\Console\Commands\CommandProcessor;
use InvalidArgumentException;

class ProcessResolveOccurrenceJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $occurrenceId,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }

    protected function beforeProcess(CommandProcessor $processor, string $commandId): void
    {
        $occurrenceService = app(OccurrenceService::class);
        $occurrenceId = Uuid::fromString($this->occurrenceId);
        $occurrence = $occurrenceService->findByIdForUpdate($occurrenceId);

        if ($occurrence === null) {
            throw new InvalidArgumentException("Occurrence not found: $this->occurrenceId");
        }

        $currentStatus = OccurrenceStatus::fromString($occurrence->statusCode());
        if ($currentStatus === OccurrenceStatus::RESOLVED) {
            throw new DomainException(
                "A ocorrência '$this->occurrenceId' já está resolvida e não pode ser resolvida novamente"
            );
        }
    }

    protected function processCommand(CommandProcessor $processor, string $commandId): array
    {
        return $processor->process('resolve_occurrence', [
            'commandId' => $commandId,
            'occurrenceId' => $this->occurrenceId,
        ]);
    }

    protected function getJobName(): string
    {
        return 'ProcessResolveOccurrenceJob';
    }

    protected function getStartLogContext(): array
    {
        return [
            'occurrenceId' => $this->occurrenceId,
        ];
    }

    protected function getErrorLogContext(): array
    {
        return [
            'occurrenceId' => $this->occurrenceId,
        ];
    }

    protected function getFailedLogContext(): array
    {
        return [
            'occurrenceId' => $this->occurrenceId,
        ];
    }
}

