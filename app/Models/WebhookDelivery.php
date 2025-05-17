<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'event',
        'payload',
        'response',
        'status_code',
        'error_message',
        'deliverable_type',
        'deliverable_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'status_code' => 'integer',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function deliverable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }
} 