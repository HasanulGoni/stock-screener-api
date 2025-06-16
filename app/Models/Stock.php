<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    protected $fillable = [
        'id',
        'symbol',
        'price',
        // 'close_price',
        'float',
        'gap_percent',
        'volume',
        'relative_volume',
        'short_interest',
        // 'fetched_at',
    ];
}
