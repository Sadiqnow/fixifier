<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaystackGateway
{
    public function ready(): void
    {
        abort_unless(config('journey.payments_enabled') && str_starts_with((string) config('journey.paystack_secret'), 'sk_test_'),
            503, 'Payments are disabled. Configure an approved Paystack test account. Live custody and settlement capabilities are not certified.');
    }

    public function call(string $method, string $path, array $data = []): array
    {
        $this->ready();
        $response = Http::withToken(config('journey.paystack_secret'))->acceptJson()->timeout(20)
            ->send($method, 'https://api.paystack.co/'.$path, [$method === 'GET' ? 'query' : 'json' => $data]);
        abort_unless($response->successful() && $response->json('status') === true, 502, 'Provider request failed. Reconcile its reference before retrying.');
        return $response->json('data');
    }
}
