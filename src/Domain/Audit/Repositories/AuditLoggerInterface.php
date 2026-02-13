<?php

declare(strict_types=1);

namespace Domain\Audit\Repositories;

interface AuditLoggerInterface
{
    public function log(string $entityType, string $entityId, string $action, ?array $before = null, ?array $after = null, array $meta = []): void;
}
