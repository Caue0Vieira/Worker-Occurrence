<?php

declare(strict_types=1);

namespace Infrastructure\Adapters;

use Domain\Audit\Repositories\AuditLoggerInterface;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Infrastructure\Persistence\Models\AuditLogModel;
use Throwable;

class AuditLogger implements AuditLoggerInterface
{
    public function log(string $entityType, string $entityId, string $action, ?array $before = null, ?array $after = null, array $meta = []): void
    {
        Log::info('🔔 [Audit] log() method called', [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'action' => $action,
        ]);

        try {
            if (empty($entityType)) {
                Log::warning('⚠️ [Audit] Empty entityType provided, skipping audit log');
                return;
            }

            if (empty($entityId)) {
                Log::warning('⚠️ [Audit] Empty entityId provided, skipping audit log');
                return;
            }

            if (empty($action)) {
                Log::warning('⚠️ [Audit] Empty action provided, skipping audit log');
                return;
            }

            if (!DB::getSchemaBuilder()->hasTable('audit_logs')) {
                Log::error('❌ [Audit] Table audit_logs does not exist. Please run migrations.');
                return;
            }

            $eventData = [
                'action' => $action,
                'before' => $before,
                'after' => $after,
                'meta' => $meta,
            ];

            $auditLogId = Uuid::generate()->toString();
            $occurredAt = now();

            Log::debug('🔍 [Audit] Attempting to create audit log', [
                'id' => $auditLogId,
                'entityType' => $entityType,
                'entityId' => $entityId,
                'action' => $action,
            ]);

            $auditLog = AuditLogModel::create([
                'id' => $auditLogId,
                'aggregate_type' => $entityType,
                'aggregate_id' => $entityId,
                'event_type' => $action,
                'event_data' => $eventData,
                'occurred_at' => $occurredAt,
            ]);

            if ($auditLog->exists) {
                Log::info('📝 [Audit] Log registered successfully', [
                    'id' => $auditLog->id,
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'action' => $action,
                ]);
            } else {
                Log::error('❌ [Audit] Log created but model does not exist', [
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'action' => $action,
                ]);
            }
        } catch (Throwable $e) {
            // Não deve quebrar o fluxo principal se o log falhar
            Log::error('❌ [Audit] Failed to register audit log', [
                'entityType' => $entityType,
                'entityId' => $entityId,
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}

