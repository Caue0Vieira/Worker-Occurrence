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

class ProcessResolveOccurrenceJob implements ShouldQueue
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
        public string $occurrenceId,
        public ?string $commandId = null,
    ) {
    }

    public function handle(
        CommandProcessor $processor,
        IdempotencyRepositoryInterface $idempotencyRepository
    ): void {
        Log::info('🚀 [Worker] ProcessResolveOccurrenceJob started', [
            'idempotencyKey' => $this->idempotencyKey,
            'occurrenceId' => $this->occurrenceId,
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

            $result = $processor->process('resolve_occurrence', [
                'commandId' => $decision->commandId,
                'occurrenceId' => $this->occurrenceId,
            ]);

            $idempotencyRepository->markAsProcessed($decision->commandId, $result);

            Log::info('✅ [Worker] ProcessResolveOccurrenceJob completed successfully', [
                'commandId' => $decision->commandId,
                'occurrenceId' => $this->occurrenceId,
                'status' => $result['status'] ?? null,
            ]);
        } catch (Throwable $exception) {
            Log::error('❌ [Worker] ProcessResolveOccurrenceJob failed', [
                'idempotencyKey' => $this->idempotencyKey,
                'occurrenceId' => $this->occurrenceId,
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
        Log::critical('💀 [Worker] ProcessResolveOccurrenceJob permanently failed after all retries', [
            'idempotencyKey' => $this->idempotencyKey,
            'occurrenceId' => $this->occurrenceId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

