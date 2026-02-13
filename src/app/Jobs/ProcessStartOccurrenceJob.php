<?php

declare(strict_types=1);

namespace App\Jobs;

use Infrastructure\Console\Commands\CommandProcessor;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Domain\Shared\Repository\LoggerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessStartOccurrenceJob implements ShouldQueue
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
        IdempotencyRepositoryInterface $idempotencyRepository,
        LoggerInterface $logger
    ): void {
        $logger->info('🚀 [Worker] ProcessStartOccurrenceJob started', [
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

            $logger->info('🔑 [Worker] Idempotency check completed', [
                'commandId' => $decision->commandId,
                'shouldProcess' => $decision->shouldProcess,
                'currentStatus' => $decision->currentStatus,
            ]);

            if (!$decision->shouldProcess) {
                $logger->info('⏭️ [Worker] Command already processed, skipping', [
                    'commandId' => $decision->commandId,
                ]);
                return;
            }

            $result = $processor->process('start_occurrence', [
                'commandId' => $decision->commandId,
                'occurrenceId' => $this->occurrenceId,
            ]);

            $idempotencyRepository->markAsProcessed($decision->commandId, $result);

            $logger->info('✅ [Worker] ProcessStartOccurrenceJob completed successfully', [
                'commandId' => $decision->commandId,
                'occurrenceId' => $this->occurrenceId,
                'status' => $result['status'] ?? null,
            ]);
        } catch (Throwable $exception) {
            $logger->error('❌ [Worker] ProcessStartOccurrenceJob failed', [
                'idempotencyKey' => $this->idempotencyKey,
                'occurrenceId' => $this->occurrenceId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($decision) && property_exists($decision, 'commandId')) {
                try {
                    $idempotencyRepository->markAsFailed($decision->commandId, $exception->getMessage());
                } catch (Throwable $e) {
                    $logger->warning('⚠️ [Worker] Failed to mark command as failed', [
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
        $logger = app(LoggerInterface::class);
        $logger->critical('💀 [Worker] ProcessStartOccurrenceJob permanently failed after all retries', [
            'idempotencyKey' => $this->idempotencyKey,
            'occurrenceId' => $this->occurrenceId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

