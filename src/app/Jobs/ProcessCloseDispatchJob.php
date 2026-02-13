<?php

declare(strict_types=1);

namespace App\Jobs;

use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Infrastructure\Console\Commands\CommandProcessor;
use Throwable;


class ProcessCloseDispatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        public string $idempotencyKey,
        public string $source,
        public string $type,
        public string $scopeKey,
        public array $payload,
        public string $dispatchId,
        public ?string $commandId = null,
    ) {
    }

    public function handle(
        CommandProcessor $processor,
        IdempotencyRepositoryInterface $idempotencyRepository
    ): void {
        Log::info('🚀 [Worker] ProcessCloseDispatchJob started', [
            'idempotencyKey' => $this->idempotencyKey,
            'dispatchId' => $this->dispatchId,
        ]);

        try {
            $decision = $idempotencyRepository->checkOrRegister(
                idempotencyKey: $this->idempotencyKey,
                source: $this->source,
                type: $this->type,
                scopeKey: $this->scopeKey,
                payload: $this->payload,
                commandId: $this->commandId,
            );

            Log::info('🔑 [Worker] Idempotency check completed', [
                'commandId' => $decision->commandId,
                'shouldProcess' => $decision->shouldProcess,
                'currentStatus' => $decision->currentStatus,
            ]);

            if (!$decision->shouldProcess) {
                Log::info('⏭️ [Worker] Command already processed, skipping', [
                    'commandId' => $decision->commandId,
                ]);
                return;
            }

            $result = $processor->process('close_dispatch', [
                'commandId' => $decision->commandId,
                'dispatchId' => $this->dispatchId,
            ]);

            $idempotencyRepository->markAsProcessed($decision->commandId, $result);

            Log::info('✅ [Worker] ProcessCloseDispatchJob completed successfully', [
                'commandId' => $decision->commandId,
                'dispatchId' => $this->dispatchId,
                'status' => $result['status'] ?? null,
            ]);
        } catch (Throwable $exception) {
            Log::error('❌ [Worker] ProcessCloseDispatchJob failed', [
                'idempotencyKey' => $this->idempotencyKey,
                'dispatchId' => $this->dispatchId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($decision) && property_exists($decision, 'commandId')) {
                try {
                    $idempotencyRepository->markAsFailed($decision->commandId, $exception->getMessage());
                } catch (Throwable $e) {
                    Log::warning('⚠️ [Worker] Failed to mark command as failed', [
                        'commandId' => $decision->commandId ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('💀 [Worker] ProcessCloseDispatchJob permanently failed after all retries', [
            'idempotencyKey' => $this->idempotencyKey,
            'dispatchId' => $this->dispatchId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

