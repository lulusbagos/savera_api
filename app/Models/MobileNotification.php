<?php

namespace App\Models;

use App\Traits\Blameable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MobileNotification extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'company_id',
        'user_id',
        'username',
        'title',
        'message_html',
        'source_type',
        'source_ref',
        'source_event_at',
        'payload_json',
        'status',
        'read_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'read_at' => 'datetime',
            'published_at' => 'datetime',
            'source_event_at' => 'datetime',
            'payload_json' => 'array',
        ];
    }
}
