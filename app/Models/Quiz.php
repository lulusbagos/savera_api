<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Quiz extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'title',
        'content',
        'department',
        'status',
        'company_id',
    ];
}
