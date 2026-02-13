<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesIntegrationSchema
{
    protected function createRequiredTables(): void
    {
        $this->createOccurrenceTables();
        $this->createDispatchTables();
        $this->createCommandInboxTable();
        $this->createAuditLogsTable();
        $this->seedReferenceData();
    }

    private function createOccurrenceTables(): void
    {
        if (!Schema::hasTable('occurrences')) {
            Schema::create('occurrences', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('external_id', 100)->unique();
                $table->string('type_code', 50);
                $table->string('status_code', 50);
                $table->text('description');
                $table->timestamp('reported_at');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('occurrence_types')) {
            Schema::create('occurrence_types', function (Blueprint $table): void {
                $table->string('code', 50)->primary();
                $table->string('name', 100);
                $table->string('category', 50);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('occurrence_status')) {
            Schema::create('occurrence_status', function (Blueprint $table): void {
                $table->string('code', 50)->primary();
                $table->string('name', 100);
                $table->boolean('is_final')->default(false);
                $table->timestamps();
            });
        }
    }

    private function createDispatchTables(): void
    {
        if (!Schema::hasTable('dispatches')) {
            Schema::create('dispatches', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('occurrence_id');
                $table->string('resource_code', 50);
                $table->string('status_code', 50);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dispatch_status')) {
            Schema::create('dispatch_status', function (Blueprint $table): void {
                $table->string('code', 50)->primary();
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    private function createCommandInboxTable(): void
    {
        if (!Schema::hasTable('command_inbox')) {
            Schema::create('command_inbox', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('idempotency_key');
                $table->string('source', 32);
                $table->string('type', 64);
                $table->string('scope_key', 191);
                $table->string('payload_hash', 64);
                $table->json('payload');
                $table->string('status', 16)->default('pending');
                $table->json('result')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['idempotency_key', 'type', 'scope_key'], 'command_inbox_idem_type_scope_unique');
            });
        }
    }

    private function createAuditLogsTable(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('aggregate_type', 100);
                $table->uuid('aggregate_id');
                $table->string('event_type', 100);
                $table->json('event_data');
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }
    }

    private function seedReferenceData(): void
    {
        if (!DB::table('occurrence_types')->where('code', 'incendio_urbano')->exists()) {
            DB::table('occurrence_types')->insert([
                'code' => 'incendio_urbano',
                'name' => 'Incendio urbano',
                'category' => 'fire',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $occurrenceStatuses = [
            ['code' => 'reported', 'name' => 'Reported', 'is_final' => false],
            ['code' => 'in_progress', 'name' => 'In progress', 'is_final' => false],
            ['code' => 'resolved', 'name' => 'Resolved', 'is_final' => true],
        ];

        foreach ($occurrenceStatuses as $status) {
            if (!DB::table('occurrence_status')->where('code', $status['code'])->exists()) {
                DB::table('occurrence_status')->insert([
                    ...$status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $dispatchStatuses = [
            ['code' => 'assigned', 'name' => 'Assigned', 'is_active' => true],
            ['code' => 'en_route', 'name' => 'En route', 'is_active' => true],
            ['code' => 'on_site', 'name' => 'On site', 'is_active' => true],
            ['code' => 'closed', 'name' => 'Closed', 'is_active' => false],
        ];

        foreach ($dispatchStatuses as $status) {
            if (!DB::table('dispatch_status')->where('code', $status['code'])->exists()) {
                DB::table('dispatch_status')->insert([
                    ...$status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

