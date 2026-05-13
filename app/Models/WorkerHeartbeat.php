<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerHeartbeat extends Model
{
    protected $fillable = [
        'worker_name',
        'queue_connection',
        'queue_name',
        'status',
        'current_upload_id',
        'current_source',
        'processed_count',
        'failed_count',
        'last_seen_at',
        'meta_json',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'meta_json' => 'array',
    ];
}
