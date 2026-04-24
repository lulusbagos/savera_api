<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class P5m extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $table = 'p5m';

    protected $fillable = [
        'date',
        'shift',
        'code',
        'fullname',
        'job',
        'score',
        'status',
        'platform',
        'quiz_id',
        'employee_id',
        'company_id',
        'department_id',
    ];
}
