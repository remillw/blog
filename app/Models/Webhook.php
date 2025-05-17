<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'description',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($webhook) {
            if (empty($webhook->secret)) {
                $webhook->secret = Str::random(32);
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function shouldReceiveEvent(string $event): bool
    {
        return in_array($event, $this->events);
    }
}
