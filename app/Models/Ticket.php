<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Ticket extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'date',
        'shift',
        'code',
        'fullname',
        'job',
        'sector',
        'area',
        'type',
        'unit',
        'model',
        'fleet',
        'transport',
        'day',
        'employee_id',
        'company_id',
        'department_id',
    ];
}
