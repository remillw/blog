<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function dispatch(string $event, $model): void
    {
        $webhooks = Webhook::where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        foreach ($webhooks as $webhook) {
            $this->deliver($webhook, $event, $model);
        }
    }

    protected function deliver(Webhook $webhook, string $event, $model): void
    {
        try {
            $payload = $this->preparePayload($event, $model);
            $signature = $this->generateSignature($webhook->secret, $payload);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $event,
            ])->post($webhook->url, $payload);

            $delivery = new WebhookDelivery([
                'event' => $event,
                'payload' => $payload,
                'response' => $response->json(),
                'status_code' => $response->status(),
                'error_message' => $response->failed() ? $response->body() : null,
            ]);

            $delivery->webhook()->associate($webhook);
            $delivery->deliverable()->associate($model);
            $delivery->save();

            if ($response->failed()) {
                Log::error('Webhook delivery failed', [
                    'webhook_id' => $webhook->id,
                    'event' => $event,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook delivery error', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);

            $delivery = new WebhookDelivery([
                'event' => $event,
                'payload' => $this->preparePayload($event, $model),
                'error_message' => $e->getMessage(),
            ]);

            $delivery->webhook()->associate($webhook);
            $delivery->deliverable()->associate($model);
            $delivery->save();
        }
    }

    protected function preparePayload(string $event, $model): array
    {
        return [
            'event' => $event,
            'data' => $model->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    protected function generateSignature(string $secret, array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }
} 