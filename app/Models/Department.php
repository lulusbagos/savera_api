<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Department extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'company_id',
        'external_id',
        'source_type',
        'is_api_managed',
        'last_synced_at',
        'sync_payload',
    ];
}
