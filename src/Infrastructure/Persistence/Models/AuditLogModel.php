<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'event_data',
        'user_id',
        'ip_address',
        'occurred_at',
    ];

    protected $casts = [
        'event_data' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

