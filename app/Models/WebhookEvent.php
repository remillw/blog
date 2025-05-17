<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_endpoint_id',
        'event_type',
        'payload',
        'response',
        'status',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'processed_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
} 