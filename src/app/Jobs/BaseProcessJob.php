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

abstract class BaseProcessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public string $idempotencyKey;

    public function __construct(
        string $idempotencyKey,
        public string $source,
        public string $type,
        public string $scopeKey,
        public array $payload,
        public ?string $commandId = null,
    ) {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function handle(
        CommandProcessor $processor,
        IdempotencyRepositoryInterface $idempotencyRepository
    ): void {
        $this->logStart();

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
                Log::info('⏭️ [Worker] Command is not in a processable state, skipping', [
                    'commandId' => $decision->commandId,
                ]);
                return;
            }

            $idempotencyRepository->markAsProcessing($decision->commandId);

            // Hook para validações antes do processamento
            $this->beforeProcess($processor, $decision->commandId);

            $result = $this->processCommand($processor, $decision->commandId);

            $idempotencyRepository->markAsProcessed($decision->commandId, $result);

            $this->logSuccess($decision->commandId, $result);
        } catch (Throwable $exception) {
            $this->handleException($exception, $idempotencyRepository, $decision ?? null);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical("💀 [Worker] {$this->getJobName()} permanently failed after all retries", [
            'idempotencyKey' => $this->idempotencyKey,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            ...$this->getFailedLogContext(),
        ]);
    }

    /**
     * Hook executado antes do processamento do comando
     * Pode ser sobrescrito para adicionar validações específicas
     * 
     * @param CommandProcessor $processor
     * @param string $commandId
     */
    protected function beforeProcess(CommandProcessor $processor, string $commandId): void
    {
        // Hook vazio por padrão, pode ser sobrescrito
    }

    /**
     * Processa o comando específico do job
     * 
     * @param CommandProcessor $processor
     * @param string $commandId
     * @return array
     */
    abstract protected function processCommand(CommandProcessor $processor, string $commandId): array;

    /**
     * Retorna o nome do job para logs
     */
    abstract protected function getJobName(): string;

    /**
     * Retorna o contexto adicional para logs de início
     */
    protected function getStartLogContext(): array
    {
        return [];
    }

    /**
     * Retorna o contexto adicional para logs de sucesso
     */
    protected function getSuccessLogContext(array $result): array
    {
        return [];
    }

    /**
     * Retorna o contexto adicional para logs de erro
     */
    protected function getErrorLogContext(): array
    {
        return [];
    }

    /**
     * Retorna o contexto adicional para logs de falha permanente
     */
    protected function getFailedLogContext(): array
    {
        return [];
    }

    private function logStart(): void
    {
        Log::info("🚀 [Worker] {$this->getJobName()} started", [
            'idempotencyKey' => $this->idempotencyKey,
            ...$this->getStartLogContext(),
        ]);
    }

    private function logSuccess(string $commandId, array $result): void
    {
        Log::info("✅ [Worker] {$this->getJobName()} completed successfully", [
            'commandId' => $commandId,
            'status' => $result['status'] ?? null,
            ...$this->getSuccessLogContext($result),
        ]);
    }

    private function handleException(
        Throwable $exception,
        IdempotencyRepositoryInterface $idempotencyRepository,
        ?object $decision
    ): void {
        Log::error("❌ [Worker] {$this->getJobName()} failed", [
            'idempotencyKey' => $this->idempotencyKey,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            ...$this->getErrorLogContext(),
        ]);

        if ($decision !== null && property_exists($decision, 'commandId')) {
            try {
                $idempotencyRepository->markAsFailed($decision->commandId, $exception->getMessage());
            } catch (Throwable $e) {
                Log::warning('⚠️ [Worker] Failed to mark command as failed', [
                    'commandId' => $decision->commandId ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

