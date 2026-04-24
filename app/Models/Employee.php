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
    ];
}
