<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Employee extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'code',
        'external_employee_id',
        'fullname',
        'email',
        'phone',
        'address',
        'city',
        'region',
        'pos',
        'country',
        'birth_date',
        'hire_date',
        'photo',
        'job',
        'position',
        'company_id',
        'department_id',
        'mess_id',
        'user_id',
        'device_id',
        'status',
        'source_type',
        'is_api_managed',
        'external_department_name',
        'external_position_name',
        'external_status',
        'allow_manual_override',
        'synced_at',
        'sync_payload',
    ];
}
