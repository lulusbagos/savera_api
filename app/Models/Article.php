<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Blameable;

class Article extends Model
{
    use HasFactory, SoftDeletes, Blameable;

    protected $fillable = [
        'title',
        'content',
        'type',
        'category',
        'author',
        'published_date',
        'link',
        'image',
        'image_url',
        'status',
        'company_id',
    ];
}
