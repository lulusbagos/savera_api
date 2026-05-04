<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Device extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'brand',
        'device_name',
        'mac_address',
        'auth_key',
        'serial_number',
        'external_device_id',
        'license_number',
        'app_version',
        'os_name',
        'os_version',
        'os_sdk',
        'phone_brand',
        'phone_model',
        'phone_product',
        'is_active',
        'source_type',
        'is_api_managed',
        'last_synced_at',
        'company_id',
    ];
}
