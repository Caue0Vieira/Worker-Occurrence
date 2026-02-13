<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Application\Exceptions\IdempotencyConflictException;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Domain\Shared\ValueObjects\IdempotencyDecision;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Models\CommandInboxModel;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

class IdempotencyRepository implements IdempotencyRepositoryInterface
{
    /**
     * @throws JsonException
     */
    public function checkOrRegister(string $idempotencyKey, string $source, string $type, string $scopeKey, array $payload, ?string $commandId = null): IdempotencyDecision
    {
        $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $ttlInSeconds = (int)config('api.idempotency.ttl', 86400);
        $expiresAt = now()->addSeconds($ttlInSeconds);
        $normalizedIdempotencyKey = $this->normalizeIdempotencyKey($idempotencyKey);

        return DB::transaction(function () use (
            $normalizedIdempotencyKey,
            $source,
            $type,
            $scopeKey,
            $payload,
            $payloadHash,
            $expiresAt,
            $commandId
        ): IdempotencyDecision {
            if ($commandId !== null) {
                $existingById = CommandInboxModel::query()
                    ->where('id', $commandId)
                    ->lockForUpdate()
                    ->first();

                if ($existingById !== null) {
                    return new IdempotencyDecision(
                        commandId: $existingById->id,
                        shouldProcess: $existingById->status === 'pending',
                        currentStatus: $existingById->status
                    );
                }
            }

            CommandInboxModel::query()
                ->where('idempotency_key', $normalizedIdempotencyKey)
                ->where('type', $type)
                ->where('scope_key', $scopeKey)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->delete();

            $existing = CommandInboxModel::query()
                ->where('idempotency_key', $normalizedIdempotencyKey)
                ->where('type', $type)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->payload_hash !== $payloadHash) {
                    throw IdempotencyConflictException::withPayloadMismatch($normalizedIdempotencyKey, $scopeKey);
                }

                return new IdempotencyDecision(
                    commandId: $existing->id,
                    shouldProcess: $existing->status === 'pending',
                    currentStatus: $existing->status
                );
            }

            $resolvedCommandId = $commandId ?? Uuid::generate()->toString();

            CommandInboxModel::create([
                'id' => $resolvedCommandId,
                'idempotency_key' => $normalizedIdempotencyKey,
                'source' => $source,
                'type' => $type,
                'scope_key' => $scopeKey,
                'payload_hash' => $payloadHash,
                'payload' => $payload,
                'status' => 'pending',
                'expires_at' => $expiresAt,
            ]);

            return new IdempotencyDecision(
                commandId: $resolvedCommandId,
                shouldProcess: true,
                currentStatus: 'pending'
            );
        });
    }

    private function normalizeIdempotencyKey(string $idempotencyKey): string
    {
        $trimmed = trim($idempotencyKey);

        if ($trimmed !== '') {
            return $trimmed;
        }

        return 'auto-' . Uuid::generate()->toString();
    }

    public function markAsProcessed(string $commandId, mixed $result): void
    {
        CommandInboxModel::query()
            ->where('id', $commandId)
            ->update([
                'status' => 'processed',
                'result' => is_object($result) && method_exists($result, 'toArray')
                    ? $result->toArray()
                    : $result,
                'processed_at' => now(),
            ]);
    }

    public function markAsFailed(string $commandId, string $errorMessage): void
    {
        CommandInboxModel::query()
            ->where('id', $commandId)
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'processed_at' => now(),
            ]);
    }

    public function getResult(string $commandId): array
    {
        $maxWaitSeconds = 5;
        $pollIntervalMs = 100;
        $maxAttempts = (int)($maxWaitSeconds * 1000 / $pollIntervalMs);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $command = CommandInboxModel::query()
                ->where('id', $commandId)
                ->first();

            if ($command === null) {
                throw new InvalidArgumentException("Command not found: $commandId");
            }

            if ($command->status === 'failed') {
                throw new RuntimeException("Command failed: $command->error_message");
            }

            if ($command->status === 'processed' && $command->result !== null) {
                return is_array($command->result) ? $command->result : (array)$command->result;
            }

            if ($command->status === 'pending') {
                usleep($pollIntervalMs * 1000);
                continue;
            }

            // Status desconhecido
            throw new RuntimeException("Command in unexpected status: $command->status");
        }

        throw new RuntimeException("Command result not available yet (timeout after {$maxWaitSeconds}s)");
    }
}

