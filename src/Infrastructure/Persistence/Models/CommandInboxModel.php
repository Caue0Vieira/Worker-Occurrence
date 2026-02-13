<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class CommandInboxModel extends Model
{
    protected $table = 'command_inbox';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'idempotency_key',
        'source',
        'type',
        'scope_key',
        'payload_hash',
        'payload',
        'status',
        'result',
        'error_message',
        'processed_at',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'processed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

