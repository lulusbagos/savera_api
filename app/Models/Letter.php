<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Letter extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'date',
        'shift',
        'code',
        'fullname',
        'job',
        'title',
        'content',
        'type',
        'link',
        'image',
        'status',
        'employee_id',
        'company_id',
        'department_id',
    ];
}
