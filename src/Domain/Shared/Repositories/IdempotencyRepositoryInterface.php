<?php

declare(strict_types=1);

namespace Domain\Shared\Repositories;

use Domain\Shared\ValueObjects\IdempotencyDecision;

/**
 * Interface do Repositório de Idempotência
 *
 * Define o contrato para repositórios que gerenciam idempotência de comandos.
 */
interface IdempotencyRepositoryInterface
{
    /**
     * Verifica ou registra uma chave de idempotência
     *
     * @param string $idempotencyKey Chave de idempotência
     * @param string $source Origem do comando
     * @param string $type Tipo do comando
     * @param string $scopeKey Chave de escopo
     * @param array $payload Payload do comando
     * @return IdempotencyDecision Decisão sobre o processamento
     */
    public function checkOrRegister(
        string $idempotencyKey,
        string $source,
        string $type,
        string $scopeKey,
        array $payload,
        ?string $commandId = null
    ): IdempotencyDecision;

    /**
     * Marca um comando como processado
     *
     * @param string $commandId ID do comando
     * @param mixed $result Resultado do processamento
     */
    public function markAsProcessed(string $commandId, mixed $result): void;

    /**
     * Marca um comando como falhado
     *
     * @param string $commandId ID do comando
     * @param string $errorMessage Mensagem de erro
     */
    public function markAsFailed(string $commandId, string $errorMessage): void;
}

