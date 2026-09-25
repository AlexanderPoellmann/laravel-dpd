<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Actions;

use AlexanderPoellmann\LaravelDpd\Data\Label;
use AlexanderPoellmann\LaravelDpd\Data\LabelDocument;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;
use AlexanderPoellmann\LaravelDpd\Services\RequestEvents;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class DownloadLabel
{
    public function handle(Label|LabelSheetResult|string $label): LabelDocument
    {
        if ($label instanceof Label && ! $label->successful()) {
            if ($label->errorCode !== null) {
                throw DpdApiException::fromErrorString($label->errorCode);
            }

            throw new DpdResponseException('DPD label result does not contain a usable document.');
        }

        $url = is_string($label) ? $label : $label->url;
        $format = $this->validateUrl($url);

        $events = new RequestEvents('downloadLabel');
        $response = null;

        try {
            try {
                $response = Http::withoutRedirecting()
                    ->timeout((int) config('dpd.timeout', 30))
                    ->connectTimeout((int) config('dpd.connect_timeout', 10))
                    ->get($url);
            } catch (ConnectionException $exception) {
                throw DpdTransportException::fromThrowable($exception);
            }

            $document = $this->parseDocument($response, $format);
        } catch (Throwable $exception) {
            $events->failed($response?->status());

            throw $exception;
        }

        $events->succeeded($response->status());

        return $document;
    }

    private function parseDocument(Response $response, LabelFormat $format): LabelDocument
    {
        if (! $response->successful()) {
            throw DpdTransportException::fromHttpStatus($response->status(), $response->body());
        }

        $contents = $response->body();
        $mimeType = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
        $expectedMimeType = $format === LabelFormat::Pdf ? 'application/pdf' : 'text/plain';

        if (! in_array($mimeType, ['', 'application/octet-stream', $expectedMimeType], true)) {
            throw new DpdResponseException('DPD label document has an unexpected MIME type.');
        }

        if (trim($contents) === ''
            || ($format === LabelFormat::Pdf && ! str_starts_with($contents, '%PDF-'))
            || ($format !== LabelFormat::Pdf && preg_match('/\A\s*(?:<|\{|\[)/', $contents) === 1)) {
            throw new DpdResponseException('DPD returned invalid label document contents.');
        }

        return new LabelDocument($contents, $expectedMimeType, $format);
    }

    private function validateUrl(?string $url): LabelFormat
    {
        $parts = $url !== null ? parse_url($url) : false;

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false || $parts === false
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || strtolower($parts['host'] ?? '') !== 'ws-etikett.paketomat.at'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || preg_match('~\A/(?:secure/)?[a-zA-Z0-9_-]+\.(pdf|zpl|epl)\z~i', $parts['path'] ?? '', $matches) !== 1) {
            throw new DpdResponseException('Expected an HTTPS PDF, ZPL, or EPL label URL on ws-etikett.paketomat.at.');
        }

        return LabelFormat::from(strtoupper($matches[1]));
    }
}
