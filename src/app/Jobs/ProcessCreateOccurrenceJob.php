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

class ProcessCreateOccurrenceJob implements ShouldQueue
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
        public string $externalId,
        public string $occurrenceType,
        public string $description,
        public string $reportedAt,
        public ?string $commandId = null,
    ) {
    }

    public function handle(
        CommandProcessor $processor,
        IdempotencyRepositoryInterface $idempotencyRepository,
        LoggerInterface $logger
    ): void {
        $logger->info('🚀 [Worker] ProcessCreateOccurrenceJob started', [
            'idempotencyKey' => $this->idempotencyKey,
            'externalId' => $this->externalId,
            'type' => $this->type,
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

            $result = $processor->process('create_occurrence', [
                'commandId' => $decision->commandId,
                'externalId' => $this->externalId,
                'type' => $this->occurrenceType,
                'description' => $this->description,
                'reportedAt' => $this->reportedAt,
            ]);

            $idempotencyRepository->markAsProcessed($decision->commandId, $result);

            $logger->info('✅ [Worker] ProcessCreateOccurrenceJob completed successfully', [
                'commandId' => $decision->commandId,
                'occurrenceId' => $result['occurrenceId'] ?? null,
                'status' => $result['status'] ?? null,
            ]);
        } catch (Throwable $exception) {
            $logger->error('❌ [Worker] ProcessCreateOccurrenceJob failed', [
                'idempotencyKey' => $this->idempotencyKey,
                'externalId' => $this->externalId,
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
        $logger->critical('💀 [Worker] ProcessCreateOccurrenceJob permanently failed after all retries', [
            'idempotencyKey' => $this->idempotencyKey,
            'externalId' => $this->externalId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

