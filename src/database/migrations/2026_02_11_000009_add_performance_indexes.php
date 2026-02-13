<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['aggregate_type', 'occurred_at'], 'audit_logs_type_occurred_at_index');
            $table->index(['aggregate_id', 'occurred_at'], 'audit_logs_id_occurred_at_index');
        });

        if (Schema::hasTable('command_inbox')) {
            Schema::table('command_inbox', function (Blueprint $table) {
                if (!Schema::hasIndex('command_inbox', 'command_inbox_status_created_at_index')) {
                    $table->index(['status', 'created_at'], 'command_inbox_status_created_at_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_type_occurred_at_index');
            $table->dropIndex('audit_logs_id_occurred_at_index');
        });

        if (Schema::hasTable('command_inbox')) {
            Schema::table('command_inbox', function (Blueprint $table) {
                $table->dropIndex('command_inbox_status_created_at_index');
            });
        }
    }
};

