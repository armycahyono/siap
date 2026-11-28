<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class DispatchLmsWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 180];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.lms.url', ''), '/');
        $token = (string) config('services.lms.token', '');

        if ($baseUrl === '' || $token === '') {
            return;
        }

        Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->timeout(10)
            ->post('/api/v1/webhooks/siap', [
                'event' => $this->event,
                'payload' => $this->payload,
            ])
            ->throw();
    }
}
