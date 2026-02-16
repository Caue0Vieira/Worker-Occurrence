<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Jobs\ProcessCancelOccurrenceJob;
use Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Models\CommandInboxModel;
use Tests\TestCase;
use Tests\Support\CreatesIntegrationSchema;

class ProcessCancelOccurrenceJobTest extends TestCase
{
    use RefreshDatabase;
    use CreatesIntegrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    public function test_job_processes_occurrence_cancellation_and_marks_command_as_succeeded(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createOccurrence($occurrenceId, 'reported');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-cancel-job-001',
            'source' => 'internal_system',
            'type' => 'cancel_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'ENQUEUED',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessCancelOccurrenceJob(
            idempotencyKey: 'idem-cancel-job-001',
            source: 'internal_system',
            type: 'cancel_occurrence',
            scopeKey: $occurrenceId,
            payload: ['occurrenceId' => $occurrenceId],
            occurrenceId: $occurrenceId,
            commandId: $commandId,
        );

        $job->handle(
            app(\Infrastructure\Console\Commands\CommandProcessor::class),
            app(IdempotencyRepositoryInterface::class),
            app(\Domain\Shared\Repository\LoggerInterface::class)
        );

        $command->refresh();
        $this->assertSame('SUCCEEDED', $command->status);
        $this->assertNotNull($command->result);
        $this->assertNotNull($command->processed_at);

        $occurrence = app(OccurrenceRepositoryInterface::class)->findById(Uuid::fromString($occurrenceId));
        $this->assertNotNull($occurrence);
        $this->assertSame('cancelled', $occurrence->statusCode());
    }

    public function test_job_cancels_occurrence_in_progress(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createOccurrence($occurrenceId, 'in_progress');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-cancel-job-002',
            'source' => 'internal_system',
            'type' => 'cancel_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'ENQUEUED',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessCancelOccurrenceJob(
            idempotencyKey: 'idem-cancel-job-002',
            source: 'internal_system',
            type: 'cancel_occurrence',
            scopeKey: $occurrenceId,
            payload: ['occurrenceId' => $occurrenceId],
            occurrenceId: $occurrenceId,
            commandId: $commandId,
        );

        $job->handle(
            app(\Infrastructure\Console\Commands\CommandProcessor::class),
            app(IdempotencyRepositoryInterface::class),
            app(\Domain\Shared\Repository\LoggerInterface::class)
        );

        $occurrence = app(OccurrenceRepositoryInterface::class)->findById(Uuid::fromString($occurrenceId));
        $this->assertNotNull($occurrence);
        $this->assertSame('cancelled', $occurrence->statusCode());
    }

    public function test_job_rejects_cancellation_when_occurrence_already_cancelled(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createOccurrence($occurrenceId, 'cancelled');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-cancel-job-003',
            'source' => 'internal_system',
            'type' => 'cancel_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'ENQUEUED',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessCancelOccurrenceJob(
            idempotencyKey: 'idem-cancel-job-003',
            source: 'internal_system',
            type: 'cancel_occurrence',
            scopeKey: $occurrenceId,
            payload: ['occurrenceId' => $occurrenceId],
            occurrenceId: $occurrenceId,
            commandId: $commandId,
        );

        try {
            $job->handle(
                app(\Infrastructure\Console\Commands\CommandProcessor::class),
                app(IdempotencyRepositoryInterface::class),
                app(\Domain\Shared\Repository\LoggerInterface::class)
            );
            $this->fail('Expected exception was not thrown');
        } catch (\Domain\Shared\Exceptions\DomainException $e) {
            $this->assertStringContainsString('já está cancelada', $e->getMessage());
        }

        $command->refresh();
        $this->assertSame('FAILED', $command->status);
        $this->assertNotNull($command->error_message);
    }

    public function test_job_rejects_cancellation_when_occurrence_already_resolved(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createOccurrence($occurrenceId, 'resolved');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-cancel-job-004',
            'source' => 'internal_system',
            'type' => 'cancel_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'ENQUEUED',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessCancelOccurrenceJob(
            idempotencyKey: 'idem-cancel-job-004',
            source: 'internal_system',
            type: 'cancel_occurrence',
            scopeKey: $occurrenceId,
            payload: ['occurrenceId' => $occurrenceId],
            occurrenceId: $occurrenceId,
            commandId: $commandId,
        );

        try {
            $job->handle(
                app(\Infrastructure\Console\Commands\CommandProcessor::class),
                app(IdempotencyRepositoryInterface::class),
                app(\Domain\Shared\Repository\LoggerInterface::class)
            );
            $this->fail('Expected exception was not thrown');
        } catch (\Domain\Shared\Exceptions\DomainException $e) {
            $this->assertStringContainsString('já está resolvida', $e->getMessage());
        }

        $command->refresh();
        $this->assertSame('FAILED', $command->status);
        $this->assertNotNull($command->error_message);
    }

    public function test_job_marks_command_as_failed_when_occurrence_not_found(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-cancel-job-005',
            'source' => 'internal_system',
            'type' => 'cancel_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'ENQUEUED',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessCancelOccurrenceJob(
            idempotencyKey: 'idem-cancel-job-005',
            source: 'internal_system',
            type: 'cancel_occurrence',
            scopeKey: $occurrenceId,
            payload: ['occurrenceId' => $occurrenceId],
            occurrenceId: $occurrenceId,
            commandId: $commandId,
        );

        try {
            $job->handle(
                app(\Infrastructure\Console\Commands\CommandProcessor::class),
                app(IdempotencyRepositoryInterface::class),
                app(\Domain\Shared\Repository\LoggerInterface::class)
            );
            $this->fail('Expected exception was not thrown');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Occurrence not found', $e->getMessage());
        }

        $command->refresh();
        $this->assertSame('FAILED', $command->status);
        $this->assertNotNull($command->error_message);
    }

    private function createOccurrence(string $id, string $statusCode): void
    {
        DB::table('occurrences')->insert([
            'id' => $id,
            'external_id' => 'ext-' . $id,
            'type_code' => 'incendio_urbano',
            'status_code' => $statusCode,
            'description' => 'Test occurrence',
            'reported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

