<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class P5mPoint extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $table = 'p5m_point';

    protected $fillable = [
        'key',
        'answer',
        'seq',
        'point',
        'p5m_id',
        'quiz_id',
        'item_id',
    ];
}
