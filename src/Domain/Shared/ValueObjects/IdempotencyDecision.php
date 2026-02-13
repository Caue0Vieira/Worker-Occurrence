<?php

declare(strict_types=1);

namespace Domain\Shared\ValueObjects;

/**
 * Decisão de Idempotência
 *
 * Value Object que representa a decisão sobre se um comando deve ser processado
 * ou se já foi processado anteriormente.
 */
readonly class IdempotencyDecision
{
    public function __construct(
        public string $commandId,
        public bool $shouldProcess,
        public ?string $currentStatus = null
    ) {
    }
}

