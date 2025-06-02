<?php

namespace App\Jobs;

use App\Models\AiBatchJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessAiBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private AiBatchJob $batchJob
    ) {}

    public function handle(): void
    {
        try {
            // Si le batch n'a pas encore été soumis, le soumettre
            if ($this->batchJob->isPending()) {
                $this->submitBatch();
            }

            // Si le batch est soumis, vérifier son statut
            if ($this->batchJob->isSubmitted()) {
                $this->checkBatchStatus();
            }

        } catch (\Exception $e) {
            Log::error('Batch processing error', [
                'batch_id' => $this->batchJob->id,
                'error' => $e->getMessage()
            ]);

            $this->batchJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    private function submitBatch(): void
    {
        $openaiKey = env('OPENAI_API_KEY');
        
        // Créer le fichier JSONL pour le batch
        $jsonlContent = '';
        foreach ($this->batchJob->requests as $index => $request) {
            $batchRequest = [
                'custom_id' => "request-{$index}",
                'method' => 'POST',
                'url' => '/v1/chat/completions',
                'body' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => $request['messages'],
                    'temperature' => 0.7,
                    'max_tokens' => 3000,
                ]
            ];
            $jsonlContent .= json_encode($batchRequest) . "\n";
        }

        // Upload du fichier
        $uploadResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $openaiKey,
        ])->attach('file', $jsonlContent, 'batch_requests.jsonl')
          ->post('https://api.openai.com/v1/files', [
              'purpose' => 'batch'
          ]);

        if (!$uploadResponse->successful()) {
            throw new \Exception('Échec de l\'upload du fichier batch');
        }

        $fileId = $uploadResponse->json()['id'];

        // Créer le batch
        $batchResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $openaiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/batches', [
            'input_file_id' => $fileId,
            'endpoint' => '/v1/chat/completions',
            'completion_window' => '24h'
        ]);

        if (!$batchResponse->successful()) {
            throw new \Exception('Échec de la création du batch');
        }

        $batchData = $batchResponse->json();

        $this->batchJob->update([
            'batch_id' => $batchData['id'],
            'status' => 'submitted',
            'submitted_at' => now()
        ]);

        Log::info('Batch submitted successfully', [
            'batch_job_id' => $this->batchJob->id,
            'openai_batch_id' => $batchData['id']
        ]);
    }

    private function checkBatchStatus(): void
    {
        if (!$this->batchJob->batch_id) {
            throw new \Exception('Aucun batch_id trouvé');
        }

        $openaiKey = env('OPENAI_API_KEY');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $openaiKey,
        ])->get("https://api.openai.com/v1/batches/{$this->batchJob->batch_id}");

        if (!$response->successful()) {
            throw new \Exception('Échec de la vérification du statut du batch');
        }

        $batchData = $response->json();
        $status = $batchData['status'];

        // Mettre à jour le statut
        $updateData = [
            'completed_requests' => $batchData['request_counts']['completed'] ?? 0,
            'total_requests' => $batchData['request_counts']['total'] ?? $this->batchJob->total_requests,
        ];

        if ($status === 'completed') {
            $updateData['status'] = 'completed';
            $updateData['completed_at'] = now();

            // Télécharger les résultats
            if (isset($batchData['output_file_id'])) {
                $this->downloadResults($batchData['output_file_id']);
            }

        } elseif ($status === 'failed' || $status === 'expired' || $status === 'cancelled') {
            $updateData['status'] = 'failed';
            $updateData['error_message'] = "Batch {$status}: " . ($batchData['errors'] ?? 'Erreur inconnue');

        } elseif ($status === 'in_progress' || $status === 'finalizing') {
            // Re-programmer la vérification dans 5 minutes
            ProcessAiBatch::dispatch($this->batchJob)->delay(now()->addMinutes(5));
        }

        $this->batchJob->update($updateData);
    }

    private function downloadResults(string $fileId): void
    {
        $openaiKey = env('OPENAI_API_KEY');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $openaiKey,
        ])->get("https://api.openai.com/v1/files/{$fileId}/content");

        if (!$response->successful()) {
            throw new \Exception('Échec du téléchargement des résultats');
        }

        $content = $response->body();
        $lines = explode("\n", trim($content));
        $responses = [];

        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $result = json_decode($line, true);
            if ($result && isset($result['custom_id'], $result['response'])) {
                $responses[$result['custom_id']] = $result['response'];
            }
        }

        $this->batchJob->update([
            'responses' => $responses
        ]);

        Log::info('Batch results downloaded', [
            'batch_job_id' => $this->batchJob->id,
            'results_count' => count($responses)
        ]);
    }
}
