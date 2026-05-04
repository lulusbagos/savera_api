<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Company extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'code',
        'external_id',
        'name',
        'description',
        'status',
        'source_type',
        'is_api_managed',
        'last_synced_at',
        'sync_payload',
    ];
}
