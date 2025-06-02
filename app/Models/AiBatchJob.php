<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBatchJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'status',
        'requests',
        'responses',
        'total_requests',
        'completed_requests',
        'estimated_cost',
        'actual_cost',
        'submitted_at',
        'completed_at',
        'error_message',
        'user_id',
    ];

    protected $casts = [
        'requests' => 'array',
        'responses' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'estimated_cost' => 'decimal:4',
        'actual_cost' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_requests === 0) {
            return 0;
        }

        return round(($this->completed_requests / $this->total_requests) * 100, 2);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
