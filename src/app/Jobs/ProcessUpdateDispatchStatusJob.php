<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Infrastructure\Console\Commands\CommandProcessor;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Throwable;

class ProcessUpdateDispatchStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $source,
        public string $type,
        public string $scopeKey,
        public array $payload,
        public string $dispatchId,
        public string $statusCode,
        public ?string $commandId = null,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(
        CommandProcessor $processor,
        IdempotencyRepositoryInterface $idempotencyRepository,
    ): void {
        Log::info('🚀 [Worker] ProcessUpdateDispatchStatusJob started', [
            'dispatchId' => $this->dispatchId,
            'statusCode' => $this->statusCode,
        ]);

        try {
            $decision = $idempotencyRepository->checkOrRegister(
                idempotencyKey: '',
                source: $this->source,
                type: $this->type,
                scopeKey: $this->scopeKey,
                payload: $this->payload,
                commandId: $this->commandId,
            );

            if (!$decision->shouldProcess) {
                Log::info('⏭️ [Worker] Command already processed, skipping', [
                    'commandId' => $decision->commandId,
                ]);

                return;
            }

            $result = $processor->process('update_dispatch_status', [
                'commandId' => $decision->commandId,
                'dispatchId' => $this->dispatchId,
                'statusCode' => $this->statusCode,
            ]);

            $idempotencyRepository->markAsProcessed($decision->commandId, $result);

            Log::info('✅ [Worker] ProcessUpdateDispatchStatusJob completed successfully', [
                'commandId' => $decision->commandId,
                'dispatchId' => $this->dispatchId,
                'status' => $result['status'] ?? null,
            ]);
        } catch (Throwable $exception) {
            Log::error('❌ [Worker] ProcessUpdateDispatchStatusJob failed', [
                'dispatchId' => $this->dispatchId,
                'statusCode' => $this->statusCode,
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
        Log::critical('💀 [Worker] ProcessUpdateDispatchStatusJob permanently failed after all retries', [
            'dispatchId' => $this->dispatchId,
            'statusCode' => $this->statusCode,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

