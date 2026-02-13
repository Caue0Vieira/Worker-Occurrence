<?php

declare(strict_types=1);

namespace App\Jobs;

use Infrastructure\Console\Commands\CommandProcessor;

class ProcessCreateDispatchJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $occurrenceId,
        public string $resourceCode,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }

    protected function processCommand(CommandProcessor $processor, string $commandId): array
    {
        return $processor->process('create_dispatch', [
            'commandId' => $commandId,
            'occurrenceId' => $this->occurrenceId,
            'resourceCode' => $this->resourceCode,
        ]);
    }

    protected function getJobName(): string
    {
        return 'ProcessCreateDispatchJob';
    }

    protected function getStartLogContext(): array
    {
        return [
            'occurrenceId' => $this->occurrenceId,
            'resourceCode' => $this->resourceCode,
        ];
    }

    protected function getSuccessLogContext(array $result): array
    {
        return [
            'dispatchId' => $result['dispatchId'] ?? null,
            'occurrenceId' => $this->occurrenceId,
        ];
    }

    protected function getErrorLogContext(): array
    {
        return [
            'occurrenceId' => $this->occurrenceId,
            'resourceCode' => $this->resourceCode,
        ];
    }

    protected function getFailedLogContext(): array
    {
        return [
            'occurrenceId' => $this->occurrenceId,
            'resourceCode' => $this->resourceCode,
        ];
    }
}

