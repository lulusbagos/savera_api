<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileUploadChunk extends Model
{
    protected $fillable = [
        'mobile_upload_batch_id',
        'upload_id',
        'source',
        'chunk_index',
        'chunk_count',
        'status',
        'payload_hash',
        'payload_size',
        'storage_path',
        'received_at',
        'queued_at',
        'processing_started_at',
        'processed_at',
        'failed_at',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'queued_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MobileUploadBatch::class, 'mobile_upload_batch_id');
    }
}
