<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait Blameable
{
  /**
   * Boot function from Laravel.
   */
  protected static function bootBlameable()
  {
    static::creating(function ($model) {
      if (Auth::check()) {
        $model->created_by = Auth::id();
      }
    });

    static::updating(function ($model) {
      if (Auth::check()) {
        $model->updated_by = Auth::id();
      }
    });

    static::deleting(function ($model) {
      if (Auth::check()) {
        $model->deleted_by = Auth::id();
      }
    });

    static::deleted(function ($model) {
      if (Auth::check()) {
        $model->update(['deleted_by' => Auth::id()]);
      }
    });
  }
}
