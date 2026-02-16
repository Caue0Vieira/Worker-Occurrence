<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Occurrence;

use Domain\Audit\Repositories\AuditLoggerInterface;
use Domain\Dispatch\Entities\Dispatch;
use Domain\Dispatch\Repositories\DispatchRepositoryInterface;
use Domain\Dispatch\Service\DispatchService;
use Domain\Shared\Exceptions\DomainException;
use Domain\Shared\ValueObjects\Uuid;
use Tests\TestCase;

class DispatchServiceTest extends TestCase
{
    public function test_update_status_with_valid_transition_updates_entity_and_generates_audit(): void
    {
        $dispatch = Dispatch::fromArray([
            'id' => '018f0e2b-f278-7be1-88f9-cf0d43edc711',
            'occurrence_id' => '018f0e2b-f278-7be1-88f9-cf0d43edc712',
            'resource_code' => 'ABT-01',
            'status_code' => 'assigned',
            'created_at' => '2026-02-12 10:00:00',
            'updated_at' => '2026-02-12 10:00:00',
        ]);

        $repository = new InMemoryDispatchRepository($dispatch);
        $auditLogger = new SpyAuditLogger();
        $service = new DispatchService($repository, $auditLogger);

        $updated = $service->updateStatus($dispatch, 'en_route');

        $this->assertSame('en_route', $updated->statusCode());
        $this->assertCount(1, $auditLogger->entries);
        $this->assertSame('dispatch', $auditLogger->entries[0]['entityType']);
        $this->assertSame('status_changed', $auditLogger->entries[0]['action']);
        $this->assertSame('assigned', $auditLogger->entries[0]['before']['status_code']);
        $this->assertSame('en_route', $auditLogger->entries[0]['after']['status_code']);
    }

    public function test_update_status_with_invalid_transition_throws_and_does_not_log_audit(): void
    {
        $dispatch = Dispatch::fromArray([
            'id' => '018f0e2b-f278-7be1-88f9-cf0d43edc721',
            'occurrence_id' => '018f0e2b-f278-7be1-88f9-cf0d43edc722',
            'resource_code' => 'ABT-02',
            'status_code' => 'assigned',
            'created_at' => '2026-02-12 10:00:00',
            'updated_at' => '2026-02-12 10:00:00',
        ]);

        $repository = new InMemoryDispatchRepository($dispatch);
        $auditLogger = new SpyAuditLogger();
        $service = new DispatchService($repository, $auditLogger);

        try {
            $service->updateStatus($dispatch, 'on_site');
            $this->fail('Era esperado DomainException para transicao invalida.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Cannot transition from assigned to on_site', $exception->getMessage());
            $this->assertCount(0, $auditLogger->entries);
        }
    }
}

final class InMemoryDispatchRepository implements DispatchRepositoryInterface
{
    /**
     * @var array<string, Dispatch>
     */
    private array $items = [];

    public function __construct(Dispatch $seed)
    {
        $this->items[$seed->id()->toString()] = $seed;
    }

    public function save(Dispatch $dispatch): Dispatch
    {
        $this->items[$dispatch->id()->toString()] = $dispatch;

        return $dispatch;
    }

    public function findById(Uuid $id): ?Dispatch
    {
        return $this->items[$id->toString()] ?? null;
    }

    public function findByOccurrenceIdAndResourceCode(Uuid $occurrenceId, string $resourceCode): ?Dispatch
    {
        foreach ($this->items as $dispatch) {
            if (
                $dispatch->occurrenceId()->toString() === $occurrenceId->toString()
                && $dispatch->resourceCode() === $resourceCode
            ) {
                return $dispatch;
            }
        }

        return null;
    }

    public function findByResourceCode(string $resourceCode): ?Dispatch
    {
        foreach ($this->items as $dispatch) {
            if ($dispatch->resourceCode() === $resourceCode) {
                return $dispatch;
            }
        }

        return null;
    }
}

final class SpyAuditLogger implements AuditLoggerInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $entries = [];

    public function log(string $entityType, string $entityId, string $action, ?array $before = null, ?array $after = null, array $meta = []): void
    {
        $this->entries[] = [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'meta' => $meta,
        ];
    }
}

