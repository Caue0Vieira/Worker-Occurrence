<?php

declare(strict_types=1);

namespace App\Jobs;

use Infrastructure\Console\Commands\CommandProcessor;

class ProcessStartOccurrenceJob extends BaseProcessJob
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

    protected function processCommand(CommandProcessor $processor, string $commandId): array
    {
        return $processor->process('start_occurrence', [
            'commandId' => $commandId,
            'occurrenceId' => $this->occurrenceId,
        ]);
    }

    protected function getJobName(): string
    {
        return 'ProcessStartOccurrenceJob';
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

