<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZabbixClient
{
    public function request(string $method, array $params = []): array
    {
        $url = config('zabbix.url');
        $token = config('zabbix.token');

        if (! $url || ! $token) {
            throw new RuntimeException('ZABBIX_API_URL and ZABBIX_API_TOKEN must be set.');
        }

        $response = $this->http()
            ->withToken($token)
            ->post($url, [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Zabbix API HTTP error: {$response->status()}");
        }

        $payload = $response->json();

        if (isset($payload['error'])) {
            $message = $payload['error']['message'] ?? 'Unknown Zabbix API error';
            $data = $payload['error']['data'] ?? '';

            throw new RuntimeException(trim("Zabbix API error: {$message} {$data}"));
        }

        return $payload['result'] ?? [];
    }

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(10);
    }
}
