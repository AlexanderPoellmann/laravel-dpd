<?php

namespace AlexanderPoellmann\LaravelDpd\Services;

use AlexanderPoellmann\LaravelDpd\Contracts\DpdTransport;
use AlexanderPoellmann\LaravelDpd\Data\Credentials;
use AlexanderPoellmann\LaravelDpd\Data\DpdResponse;
use AlexanderPoellmann\LaravelDpd\Enums\ApiFunction;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class RestTransport implements DpdTransport
{
    public function call(ApiFunction $function, array $data = []): DpdResponse
    {
        $credentials = Credentials::fromConfig($function->requiresClient());
        $payload = [
            'service' => 'PaketomatRest',
            'function' => $function->value,
            'data' => [
                ...$credentials->toArray($function->requiresClient()),
                ...$data,
            ],
        ];

        try {
            $response = $this->pendingRequest($function)->post((string) config('dpd.endpoint'), $payload);
        } catch (ConnectionException $exception) {
            throw DpdTransportException::fromThrowable($exception);
        }

        if ($response->failed()) {
            throw DpdTransportException::fromHttpStatus($response->status(), $response->body());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw DpdTransportException::invalidJson($response->body());
        }

        $dpdResponse = DpdResponse::fromArray($json);

        if (! $dpdResponse->successful()) {
            throw DpdApiException::fromResponse($dpdResponse);
        }

        return $dpdResponse;
    }

    private function pendingRequest(ApiFunction $function): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('dpd.timeout', 30))
            ->connectTimeout((int) config('dpd.connect_timeout', 10));

        $retries = max(0, (int) config('dpd.retries', 2));

        if ($retries > 0 && $function->safeToRetry()) {
            $request->retry(
                $retries,
                (int) config('dpd.retry_delay_ms', 250),
                null,
                false,
            );
        }

        return $request;
    }
}
