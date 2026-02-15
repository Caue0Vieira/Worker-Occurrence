<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use Illuminate\Support\Facades\Schema;
use Infrastructure\Persistence\Repositories\IdempotencyRepository;
use Tests\TestCase;

class IdempotencyRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createCommandInboxTableForTests();
    }

    public function test_idempotency_reuses_succeeded_command_by_command_id(): void
    {
        $repository = app(IdempotencyRepository::class);
        $commandId = '018f0e2b-f278-7be1-88f9-cf0d43edc801';

        $first = $repository->checkOrRegister(
            idempotencyKey: 'idem-worker-001',
            source: 'internal_system',
            type: 'start_occurrence',
            scopeKey: 'occ-1',
            payload: ['occurrenceId' => 'occ-1'],
            commandId: $commandId,
        );

        $this->assertTrue($first->shouldProcess);
        $repository->markAsProcessed($commandId, ['status' => 'in_progress']);

        $second = $repository->checkOrRegister(
            idempotencyKey: 'idem-worker-001',
            source: 'internal_system',
            type: 'start_occurrence',
            scopeKey: 'occ-1',
            payload: ['occurrenceId' => 'occ-1'],
            commandId: $commandId,
        );

        $this->assertSame($commandId, $second->commandId);
        $this->assertFalse($second->shouldProcess);
        $this->assertSame('SUCCEEDED', $second->currentStatus);
    }

    public function test_simulated_concurrency_with_same_key_keeps_single_record(): void
    {
        $repository = app(IdempotencyRepository::class);

        $first = $repository->checkOrRegister(
            idempotencyKey: 'idem-worker-concurrency-01',
            source: 'internal_system',
            type: 'update_dispatch_status',
            scopeKey: 'dispatch-1',
            payload: ['dispatchId' => 'dispatch-1', 'statusCode' => 'en_route'],
        );

        $second = $repository->checkOrRegister(
            idempotencyKey: 'idem-worker-concurrency-01',
            source: 'internal_system',
            type: 'update_dispatch_status',
            scopeKey: 'dispatch-1',
            payload: ['dispatchId' => 'dispatch-1', 'statusCode' => 'en_route'],
        );

        $this->assertSame($first->commandId, $second->commandId);
        $this->assertDatabaseCount('command_inbox', 1);
    }

    public function test_mark_as_processing_is_atomic_and_only_first_transition_succeeds(): void
    {
        $repository = app(IdempotencyRepository::class);
        $commandId = '018f0e2b-f278-7be1-88f9-cf0d43edc900';

        $registration = $repository->checkOrRegister(
            idempotencyKey: 'idem-worker-processing-lock-01',
            source: 'internal_system',
            type: 'start_occurrence',
            scopeKey: 'occ-processing-lock-1',
            payload: ['occurrenceId' => 'occ-processing-lock-1'],
            commandId: $commandId,
        );

        $this->assertTrue($registration->shouldProcess);

        $firstTransition = $repository->markAsProcessing($commandId);
        $secondTransition = $repository->markAsProcessing($commandId);

        $this->assertTrue($firstTransition);
        $this->assertFalse($secondTransition);
        $this->assertDatabaseHas('command_inbox', [
            'id' => $commandId,
            'status' => 'PROCESSING',
        ]);

        $decisionAfterLock = $repository->checkOrRegister(
            idempotencyKey: 'idem-worker-processing-lock-01',
            source: 'internal_system',
            type: 'start_occurrence',
            scopeKey: 'occ-processing-lock-1',
            payload: ['occurrenceId' => 'occ-processing-lock-1'],
            commandId: $commandId,
        );

        $this->assertFalse($decisionAfterLock->shouldProcess);
        $this->assertSame('PROCESSING', $decisionAfterLock->currentStatus);
    }

    private function createCommandInboxTableForTests(): void
    {
        Schema::dropIfExists('command_inbox');

        Schema::create('command_inbox', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('idempotency_key');
            $table->string('source', 32);
            $table->string('type', 64);
            $table->string('scope_key', 191);
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->string('status', 16)->default('RECEIVED');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['idempotency_key', 'type', 'scope_key'], 'command_inbox_idem_type_scope_unique');
        });
    }
}

