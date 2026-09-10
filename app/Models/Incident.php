<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'metric_type',
        'identifier',
        'status',
        'threshold_value',
        'peak_value',
        'started_at',
        'resolved_at',
        'notified_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function label(): string
    {
        return $this->identifier
            ? "{$this->metric_type} ({$this->identifier})"
            : $this->metric_type;
    }

    public function durationInMinutes(): int
    {
        $end = $this->resolved_at ?? now();

        return (int) $this->started_at->diffInMinutes($end);
    }
}
