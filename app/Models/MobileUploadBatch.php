<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MobileUploadBatch extends Model
{
    protected $fillable = [
        'upload_id',
        'source',
        'company_id',
        'user_id',
        'employee_id',
        'device_id',
        'upload_date',
        'status',
        'chunks_total',
        'chunks_received',
        'payload_bytes_total',
        'payload_hash',
        'idempotency_key',
        'summary_id',
        'accepted_counts_json',
        'parsed_counts_json',
        'stored_counts_json',
        'extra_json',
        'received_at',
        'queued_at',
        'processing_started_at',
        'completed_at',
        'failed_at',
        'last_chunk_at',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'upload_date' => 'date',
        'accepted_counts_json' => 'array',
        'parsed_counts_json' => 'array',
        'stored_counts_json' => 'array',
        'extra_json' => 'array',
        'received_at' => 'datetime',
        'queued_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'last_chunk_at' => 'datetime',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(MobileUploadChunk::class, 'mobile_upload_batch_id');
    }
}
