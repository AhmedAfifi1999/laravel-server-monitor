<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    protected $fillable = [
        'cpu_percent',
        'memory_percent',
        'disk_percent',
        'load_1min',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];
}
