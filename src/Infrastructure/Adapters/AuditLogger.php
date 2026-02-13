<?php

declare(strict_types=1);

namespace Infrastructure\Adapters;

use Domain\Audit\Repositories\AuditLoggerInterface;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\Log;
use Infrastructure\Persistence\Models\AuditLogModel;
use Throwable;

class AuditLogger implements AuditLoggerInterface
{
    public function log(string $entityType, string $entityId, string $action, ?array $before = null, ?array $after = null, array $meta = []): void
    {
        try {
            $eventData = [
                'action' => $action,
                'before' => $before,
                'after' => $after,
                'meta' => $meta,
            ];

            AuditLogModel::create([
                'id' => Uuid::generate()->toString(),
                'aggregate_type' => $entityType,
                'aggregate_id' => $entityId,
                'event_type' => $action,
                'event_data' => $eventData,
                'occurred_at' => now(),
            ]);

            Log::info('📝 [Audit] Log registered', [
                'entityType' => $entityType,
                'entityId' => $entityId,
                'action' => $action,
            ]);
        } catch (Throwable $e) {
            // Não deve quebrar o fluxo principal se o log falhar
            Log::error('❌ [Audit] Failed to register audit log', [
                'entityType' => $entityType,
                'entityId' => $entityId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

