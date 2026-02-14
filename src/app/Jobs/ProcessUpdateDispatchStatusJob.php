<?php

declare(strict_types=1);

namespace App\Jobs;

use Infrastructure\Console\Commands\CommandProcessor;

class ProcessUpdateDispatchStatusJob extends BaseProcessJob
{
    public function __construct(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        public string $dispatchId,
        public string $statusCode,
        ?string $commandId = null,
    ) {
        parent::__construct($idempotencyKey, $source, $type, $scopeKey, $payload, $commandId);
    }

    protected function processCommand(CommandProcessor $processor, string $commandId): array
    {
        return $processor->process('update_dispatch_status', [
            'commandId' => $commandId,
            'dispatchId' => $this->dispatchId,
            'statusCode' => $this->statusCode,
        ]);
    }

    protected function getJobName(): string
    {
        return 'ProcessUpdateDispatchStatusJob';
    }

    protected function getStartLogContext(): array
    {
        return [
            'dispatchId' => $this->dispatchId,
            'statusCode' => $this->statusCode,
        ];
    }

    protected function getErrorLogContext(): array
    {
        return [
            'dispatchId' => $this->dispatchId,
            'statusCode' => $this->statusCode,
        ];
    }

    protected function getFailedLogContext(): array
    {
        return [
            'dispatchId' => $this->dispatchId,
            'statusCode' => $this->statusCode,
        ];
    }
}

