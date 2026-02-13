<?php

declare(strict_types=1);

namespace App\Jobs;

use Infrastructure\Console\Commands\CommandProcessor;

class ProcessCloseDispatchJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $dispatchId,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }

    protected function processCommand(CommandProcessor $processor, string $commandId): array
    {
        return $processor->process('close_dispatch', [
            'commandId' => $commandId,
            'dispatchId' => $this->dispatchId,
        ]);
    }

    protected function getJobName(): string
    {
        return 'ProcessCloseDispatchJob';
    }

    protected function getStartLogContext(): array
    {
        return [
            'dispatchId' => $this->dispatchId,
        ];
    }

    protected function getErrorLogContext(): array
    {
        return [
            'dispatchId' => $this->dispatchId,
        ];
    }

    protected function getFailedLogContext(): array
    {
        return [
            'dispatchId' => $this->dispatchId,
        ];
    }
}

