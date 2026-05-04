<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Summary extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'active',
        'active_text',
        'steps',
        'steps_text',
        'heart_rate',
        'heart_rate_text',
        'distance',
        'distance_text',
        'calories',
        'calories_text',
        'spo2',
        'spo2_text',
        'stress',
        'stress_text',
        'sleep',
        'sleep_text',
        'sleep_start',
        'sleep_end',
        'sleep_type',
        'deep_sleep',
        'light_sleep',
        'rem_sleep',
        'awake',
        'wakeup',
        'send_date',
        'send_time',
        'status',
        'user_id',
        'employee_id',
        'company_id',
        'department_id',
        'shift_id',
        'device_id',
        'device_time',
        'app_version',
        'is_fit1',
        'is_fit2',
        'is_fit3',
        'fit_to_work_q1',
        'fit_to_work_q2',
        'fit_to_work_q3',
        'fit_to_work_submitted_at',
    ];

    protected $casts = [
        'fit_to_work_submitted_at' => 'datetime',
    ];
}
