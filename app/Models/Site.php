<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'platform_type',
        'api_key',
        'webhook_url',
        'is_active',
        'primary_color',
        'secondary_color',
        'accent_color',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('default_permissions', 'is_active')
            ->withTimestamps();
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }
}
