<?php

namespace App\Traits;

use App\Services\WebhookService;

trait HasWebhooks
{
    protected static function bootHasWebhooks(): void
    {
        static::created(function ($model) {
            app(WebhookService::class)->dispatch('created', $model);
        });

        static::updated(function ($model) {
            app(WebhookService::class)->dispatch('updated', $model);
        });

        static::deleted(function ($model) {
            app(WebhookService::class)->dispatch('deleted', $model);
        });

        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class))) {
            static::restored(function ($model) {
                app(WebhookService::class)->dispatch('restored', $model);
            });
        }
    }
} 