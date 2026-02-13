<?php

declare(strict_types=1);

namespace App\Jobs;

use Infrastructure\Console\Commands\CommandProcessor;

class ProcessCreateOccurrenceJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $externalId,
        public string $occurrenceType,
        public string $description,
        public string $reportedAt,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }

    protected function processCommand(CommandProcessor $processor, string $commandId): array
    {
        return $processor->process('create_occurrence', [
            'commandId' => $commandId,
            'externalId' => $this->externalId,
            'type' => $this->occurrenceType,
            'description' => $this->description,
            'reportedAt' => $this->reportedAt,
        ]);
    }

    protected function getJobName(): string
    {
        return 'ProcessCreateOccurrenceJob';
    }

    protected function getStartLogContext(): array
    {
        return [
            'externalId' => $this->externalId,
            'type' => $this->occurrenceType,
        ];
    }

    protected function getSuccessLogContext(array $result): array
    {
        return [
            'occurrenceId' => $result['occurrenceId'] ?? null,
        ];
    }

    protected function getErrorLogContext(): array
    {
        return [
            'externalId' => $this->externalId,
        ];
    }

    protected function getFailedLogContext(): array
    {
        return [
            'externalId' => $this->externalId,
        ];
    }
}

