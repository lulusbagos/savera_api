<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Banner extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'title',
        'description',
        'type',
        'image',
        'seq',
        'status',
        'company_id',
    ];
}
