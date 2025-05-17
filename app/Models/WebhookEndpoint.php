<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'url',
        'secret_key',
        'is_active',
        'headers',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'headers' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }
} 