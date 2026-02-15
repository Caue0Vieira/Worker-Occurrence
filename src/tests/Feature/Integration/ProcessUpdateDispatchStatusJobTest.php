<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Jobs\ProcessUpdateDispatchStatusJob;
use Domain\Occurrence\Repositories\DispatchRepositoryInterface;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Models\CommandInboxModel;
use Tests\TestCase;
use Tests\Support\CreatesIntegrationSchema;

class ProcessUpdateDispatchStatusJobTest extends TestCase
{
    use RefreshDatabase;
    use CreatesIntegrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    public function test_job_updates_dispatch_status_and_marks_command_as_succeeded(): void
    {
        $dispatchId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createDispatch($dispatchId, 'assigned');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-update-status-001',
            'source' => 'internal_system',
            'type' => 'update_dispatch_status',
            'scope_key' => $dispatchId,
            'payload_hash' => hash('sha256', json_encode(['dispatchId' => $dispatchId, 'statusCode' => 'en_route'])),
            'payload' => ['dispatchId' => $dispatchId, 'statusCode' => 'en_route'],
            'status' => 'ENQUEUED',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessUpdateDispatchStatusJob(
            idempotencyKey: 'idem-update-status-001',
            source: 'internal_system',
            type: 'update_dispatch_status',
            scopeKey: $dispatchId,
            payload: ['dispatchId' => $dispatchId, 'statusCode' => 'en_route'],
            dispatchId: $dispatchId,
            statusCode: 'en_route',
            commandId: $commandId,
        );

        $job->handle(
            app(\Infrastructure\Console\Commands\CommandProcessor::class),
            app(IdempotencyRepositoryInterface::class)
        );

        $command->refresh();
        $this->assertSame('SUCCEEDED', $command->status);
        $this->assertNotNull($command->result);
        $this->assertSame('en_route', $command->result['status']);

        $dispatch = app(DispatchRepositoryInterface::class)->findById(Uuid::fromString($dispatchId));
        $this->assertNotNull($dispatch);
        $this->assertSame('en_route', $dispatch->statusCode());
    }

    public function test_job_skips_when_command_already_succeeded(): void
    {
        $dispatchId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createDispatch($dispatchId, 'en_route');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-update-status-002',
            'source' => 'internal_system',
            'type' => 'update_dispatch_status',
            'scope_key' => $dispatchId,
            'payload_hash' => hash('sha256', json_encode(['dispatchId' => $dispatchId, 'statusCode' => 'en_route'])),
            'payload' => ['dispatchId' => $dispatchId, 'statusCode' => 'en_route'],
            'status' => 'SUCCEEDED',
            'result' => ['dispatchId' => $dispatchId, 'status' => 'en_route'],
            'processed_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessUpdateDispatchStatusJob(
            idempotencyKey: 'idem-update-status-002',
            source: 'internal_system',
            type: 'update_dispatch_status',
            scopeKey: $dispatchId,
            payload: ['dispatchId' => $dispatchId, 'statusCode' => 'en_route'],
            dispatchId: $dispatchId,
            statusCode: 'en_route',
            commandId: $commandId,
        );

        $job->handle(
            app(\Infrastructure\Console\Commands\CommandProcessor::class),
            app(IdempotencyRepositoryInterface::class)
        );

        $command->refresh();
        $this->assertSame('SUCCEEDED', $command->status);
    }

    public function test_job_marks_as_failed_on_invalid_status_transition(): void
    {
        $dispatchId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createDispatch($dispatchId, 'assigned');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-update-status-003',
            'source' => 'internal_system',
            'type' => 'update_dispatch_status',
            'scope_key' => $dispatchId,
            'payload_hash' => hash('sha256', json_encode(['dispatchId' => $dispatchId, 'statusCode' => 'on_site'])),
            'payload' => ['dispatchId' => $dispatchId, 'statusCode' => 'on_site'],
            'status' => 'ENQUEUED',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessUpdateDispatchStatusJob(
            idempotencyKey: 'idem-update-status-003',
            source: 'internal_system',
            type: 'update_dispatch_status',
            scopeKey: $dispatchId,
            payload: ['dispatchId' => $dispatchId, 'statusCode' => 'on_site'],
            dispatchId: $dispatchId,
            statusCode: 'on_site',
            commandId: $commandId,
        );

        try {
            $job->handle(
                app(\Infrastructure\Console\Commands\CommandProcessor::class),
                app(IdempotencyRepositoryInterface::class)
            );
            $this->fail('Expected exception was not thrown');
        } catch (\Domain\Shared\Exceptions\DomainException $e) {
            $this->assertStringContainsString('Cannot transition from assigned to on_site', $e->getMessage());
        }

        $command->refresh();
        $this->assertSame('FAILED', $command->status);
        $this->assertNotNull($command->error_message);
    }

    private function createDispatch(string $id, string $statusCode): void
    {
        $occurrenceId = Uuid::generate()->toString();

        DB::table('occurrences')->insert([
            'id' => $occurrenceId,
            'external_id' => 'ext-' . $occurrenceId,
            'type_code' => 'incendio_urbano',
            'status_code' => 'reported',
            'description' => 'Test occurrence',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dispatches')->insert([
            'id' => $id,
            'occurrence_id' => $occurrenceId,
            'resource_code' => 'ABT-01',
            'status_code' => $statusCode,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

}

