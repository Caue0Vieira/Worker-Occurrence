<?php

declare(strict_types=1);

namespace Application\Exceptions;

use RuntimeException;

/**
 * Exceção lançada quando há conflito de idempotência
 *
 * Ocorre quando uma chave de idempotência já existe mas com payload diferente.
 */
final class IdempotencyConflictException extends RuntimeException
{
    public static function withPayloadMismatch(string $idempotencyKey, string $scopeKey): self
    {
        return new self(
            "Idempotency key '{$idempotencyKey}' already exists with different payload for scope '{$scopeKey}'"
        );
    }
}
