<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestAudit extends Model
{
    protected $table = 'ingest_audit';

    protected $fillable = [
        'upload_id',
        'chunk_index',
        'chunk_count',
        'idempotency_key',
        'user_id',
        'company_id',
        'date',
        'payload_hash',
        'payload_size',
        'accepted_counts_json',
        'parsed_counts_json',
        'stored_counts_json',
        'status',
        'error_code',
        'error_message',
    ];
}

