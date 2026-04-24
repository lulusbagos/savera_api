<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Shift extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'work_start',
        'work_end',
        'work_hours',
        'sleep_start',
        'sleep_end',
        'alarm_clock',
        'status',
        'company_id',
    ];
}
