<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Repositories;

use Application\Exceptions\IdempotencyConflictException;
use Domain\Idempotency\Enums\CommandStatus;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Domain\Shared\ValueObjects\IdempotencyDecision;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Models\CommandInboxModel;
use InvalidArgumentException;
use JsonSerializable;
use JsonException;

final class IdempotencyRepository implements IdempotencyRepositoryInterface
{
    /**
     * @throws JsonException
     */
    public function checkOrRegister(string $idempotencyKey, string $source, string $type, string $scopeKey, array $payload, ?string $commandId = null): IdempotencyDecision
    {
        $normalizedPayload = $this->normalizePayloadForHash($payload);
        $payloadHash = hash('sha256', $normalizedPayload);
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
                    $status = CommandStatus::fromString($existingById->status);
                    return new IdempotencyDecision(
                        commandId: $existingById->id,
                        shouldProcess: $status->shouldProcessInWorker(),
                        currentStatus: $existingById->status
                    );
                }
            }

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

                $status = CommandStatus::fromString($existing->status);
                return new IdempotencyDecision(
                    commandId: $existing->id,
                    shouldProcess: $status->shouldProcessInWorker(),
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
                'status' => CommandStatus::RECEIVED->value,
                'expires_at' => $expiresAt,
            ]);

            return new IdempotencyDecision(
                commandId: $resolvedCommandId,
                shouldProcess: true,
                currentStatus: CommandStatus::RECEIVED->value
            );
        });
    }

    public function markAsProcessing(string $commandId): void
    {
        CommandInboxModel::query()
            ->where('id', $commandId)
            ->whereIn('status', [
                CommandStatus::RECEIVED->value,
                CommandStatus::ENQUEUED->value,
                CommandStatus::FAILED->value,
            ])
            ->update([
                'status' => CommandStatus::PROCESSING->value,
                'updated_at' => now(),
            ]);
    }

    private function normalizeIdempotencyKey(string $idempotencyKey): string
    {
        $trimmed = trim($idempotencyKey);

        if ($trimmed === '') {
            throw new InvalidArgumentException(
                'Idempotency key cannot be empty. Client must provide a valid idempotency key.'
            );
        }

        return $trimmed;
    }

    /**
     * Normaliza payload para hash determinístico.
     *
     * Reordena apenas arrays associativos. Listas numéricas preservam a ordem.
     *
     * @throws JsonException
     */
    private function normalizePayloadForHash(array $payload): string
    {
        $normalized = $this->normalizeForHash($payload);
        return json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Normaliza recursivamente para hash determinístico.
     */
    private function normalizeForHash(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeForHash($item);
            }
        }

        return $value;
    }

    public function markAsProcessed(string $commandId, mixed $result): void
    {
        $normalizedResult = match (true) {
            is_array($result) => $result,
            $result instanceof JsonSerializable => $result->jsonSerialize(),
            is_object($result) && method_exists($result, 'toArray') => $result->toArray(),
            default => $result,
        };

        CommandInboxModel::query()
            ->where('id', $commandId)
            ->update([
                'status' => CommandStatus::SUCCEEDED->value,
                'result' => $normalizedResult,
                'processed_at' => now(),
            ]);
    }

    public function markAsFailed(string $commandId, string $errorMessage): void
    {
        CommandInboxModel::query()
            ->where('id', $commandId)
            ->update([
                'status' => CommandStatus::FAILED->value,
                'error_message' => $errorMessage,
                'processed_at' => now(),
            ]);
    }

}

