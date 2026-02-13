<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Jobs\ProcessStartOccurrenceJob;
use Domain\Occurrence\Repositories\OccurrenceRepositoryInterface;
use Domain\Shared\Repositories\IdempotencyRepositoryInterface;
use Domain\Shared\ValueObjects\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Infrastructure\Persistence\Models\CommandInboxModel;
use Tests\TestCase;
use Tests\Support\CreatesIntegrationSchema;

class ProcessStartOccurrenceJobTest extends TestCase
{
    use RefreshDatabase;
    use CreatesIntegrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRequiredTables();
    }

    public function test_job_processes_occurrence_and_marks_command_as_processed(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createOccurrence($occurrenceId, 'reported');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-start-job-001',
            'source' => 'internal_system',
            'type' => 'start_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessStartOccurrenceJob(
            idempotencyKey: 'idem-start-job-001',
            source: 'internal_system',
            type: 'start_occurrence',
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
        $this->assertSame('processed', $command->status);
        $this->assertNotNull($command->result);
        $this->assertNotNull($command->processed_at);

        $occurrence = app(OccurrenceRepositoryInterface::class)->findById(Uuid::fromString($occurrenceId));
        $this->assertNotNull($occurrence);
        $this->assertSame('in_progress', $occurrence->statusCode());
    }

    public function test_job_skips_processing_when_command_already_processed(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $this->createOccurrence($occurrenceId, 'in_progress');

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-start-job-002',
            'source' => 'internal_system',
            'type' => 'start_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'processed',
            'result' => ['occurrenceId' => $occurrenceId, 'status' => 'in_progress'],
            'processed_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessStartOccurrenceJob(
            idempotencyKey: 'idem-start-job-002',
            source: 'internal_system',
            type: 'start_occurrence',
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
        $this->assertSame('processed', $command->status);
        $this->assertSame('in_progress', $command->result['status']);
    }

    public function test_job_marks_command_as_failed_on_error(): void
    {
        $occurrenceId = Uuid::generate()->toString();
        $commandId = Uuid::generate()->toString();

        $command = CommandInboxModel::create([
            'id' => $commandId,
            'idempotency_key' => 'idem-start-job-003',
            'source' => 'internal_system',
            'type' => 'start_occurrence',
            'scope_key' => $occurrenceId,
            'payload_hash' => hash('sha256', json_encode(['occurrenceId' => $occurrenceId])),
            'payload' => ['occurrenceId' => $occurrenceId],
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $job = new ProcessStartOccurrenceJob(
            idempotencyKey: 'idem-start-job-003',
            source: 'internal_system',
            type: 'start_occurrence',
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
        $this->assertSame('failed', $command->status);
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

