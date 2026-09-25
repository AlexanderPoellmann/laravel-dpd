<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Data\Label;
use AlexanderPoellmann\LaravelDpd\Data\LabelSheetResult;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdApiException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdTransportException;
use AlexanderPoellmann\LaravelDpd\Facades\Dpd;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('downloads a label as binary contents without credentials or storage', function (string $input) {
    $url = 'https://ws-etikett.paketomat.at/secure/1852_2602_example.pdf';
    $contents = "%PDF-1.7\n\x00\xff\x80\n%%EOF";
    config()->set(['dpd.username' => null, 'dpd.password_md5' => null, 'dpd.timeout' => 17, 'dpd.connect_timeout' => 4]);

    Http::fake(function (Request $request, array $options) use ($contents) {
        expect($options['allow_redirects'])->toBeFalse()
            ->and($options['timeout'])->toBe(17)
            ->and($options['connect_timeout'])->toBe(4);

        return Http::response($contents, 200, ['Content-Type' => 'application/pdf']);
    });

    $label = match ($input) {
        'label' => Label::fromArray(['label' => $url, 'paknr' => '06215000000217']),
        'sheet' => new LabelSheetResult($url, []),
        default => $url,
    };
    $document = Dpd::downloadLabel($label);

    expect($document->contents)->toBe($contents)
        ->and($document->mimeType)->toBe('application/pdf')
        ->and($document->format)->toBe(LabelFormat::Pdf)
        ->and($document->extension)->toBe('pdf');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === $url
        && $request->body() === ''
        && ! $request->hasHeader('Authorization')
        && ! $request->hasHeader('Cookie'));
    Http::assertSentCount(1);
})->with(['url', 'label', 'sheet']);

it('returns format and MIME metadata for supported documents', function (string $path, string $contents, string $contentType, LabelFormat $format, string $mime, string $extension) {
    $url = 'https://ws-etikett.paketomat.at/'.$path;
    Http::fake([$url => Http::response($contents, 200, $contentType === '' ? [] : ['Content-Type' => $contentType])]);

    $document = app(LaravelDpd::class)->downloadLabel($url);

    expect($document->contents)->toBe($contents)
        ->and($document->format)->toBe($format)
        ->and($document->mimeType)->toBe($mime)
        ->and($document->extension)->toBe($extension);
})->with([
    ['example.pdf', '%PDF-1.7', 'Application/PDF; charset=binary', LabelFormat::Pdf, 'application/pdf', 'pdf'],
    ['secure/example.pdf', '%PDF-1.7', 'application/octet-stream', LabelFormat::Pdf, 'application/pdf', 'pdf'],
    ['secure/example.pdf', '%PDF-1.7', '', LabelFormat::Pdf, 'application/pdf', 'pdf'],
    ['secure/example.zpl', '^XA^FO50,50^FDExample^FS^XZ', 'text/plain; charset=UTF-8', LabelFormat::Zpl, 'text/plain', 'zpl'],
    ['example.EPL', "N\nA50,50,0,1,1,1,N,\"Example\"\nP1\n", 'application/octet-stream', LabelFormat::Epl, 'text/plain', 'epl'],
]);

it('rejects unexpected label URLs before making an HTTP request', function (string $url) {
    Http::fake();

    expect(fn () => app(LaravelDpd::class)->downloadLabel($url))->toThrow(DpdResponseException::class);

    Http::assertNothingSent();
})->with([
    '', '/secure/label.pdf', 'file:///tmp/label.pdf',
    'http://ws-etikett.paketomat.at/secure/label.pdf',
    'https://example.com/label.pdf',
    'https://127.0.0.1/label.pdf',
    'https://ws-etikett.paketomat.at.evil.example/label.pdf',
    'https://evil.ws-etikett.paketomat.at/label.pdf',
    'https://ws-etikett.paketomat.at@evil.example/label.pdf',
    'https://user:password@ws-etikett.paketomat.at/label.pdf',
    'https://ws-etikett.paketomat.at:444/label.pdf',
    'https://ws-etikett.paketomat.at/label.pdf#fragment',
    'https://ws-etikett.paketomat.at/secure/../label.pdf',
    'https://ws-etikett.paketomat.at/secure/%2e%2e/label.pdf',
    'https://ws-etikett.paketomat.at/secure/label.php',
    'https://ws-etikett.paketomat.at/secure/label.png',
    'https://ws-etikett.paketomat.at/secure/label.pdf/other',
    "https://ws-etikett.paketomat.at/secure/label.pdf\n",
    'https://ws-etikett.paketomat.at\@evil.example/label.pdf',
]);

it('does not follow redirects even when the destination looks like a label', function () {
    $url = 'https://ws-etikett.paketomat.at/secure/label.pdf';
    Http::fake(function (Request $request, array $options) {
        expect($options['allow_redirects'])->toBeFalse();

        return Http::response('', 302, ['Location' => 'https://example.com/private.pdf']);
    });

    expect(fn () => app(LaravelDpd::class)->downloadLabel($url))->toThrow(DpdTransportException::class, 'HTTP 302');
    Http::assertSentCount(1);
});

it('rejects failed label results before downloading', function () {
    Http::fake();
    $label = Label::fromArray(['label' => 'https://ws-etikett.paketomat.at/label.pdf', 'err_code' => '[2] ER12-Weight limit exceeded']);

    expect(fn () => app(LaravelDpd::class)->downloadLabel($label))->toThrow(DpdApiException::class, 'ER12')
        ->and(fn () => app(LaravelDpd::class)->downloadLabel(Label::fromArray(['label' => null])))->toThrow(DpdResponseException::class);

    Http::assertNothingSent();
});

it('rejects empty documents and error pages instead of returning them as labels', function (string $extension, string $body, string $mime) {
    $url = 'https://ws-etikett.paketomat.at/secure/label.'.$extension;
    Http::fake([$url => Http::response($body, 200, ['Content-Type' => $mime])]);

    expect(fn () => app(LaravelDpd::class)->downloadLabel($url))->toThrow(DpdResponseException::class);
})->with([
    ['pdf', '', 'application/pdf'],
    ['pdf', '<html>Expired</html>', 'text/html'],
    ['pdf', '<html>Expired</html>', 'application/pdf'],
    ['pdf', '{"error":"expired"}', 'application/octet-stream'],
    ['pdf', '^XA^XZ', 'application/pdf'],
    ['zpl', '<html>Expired</html>', 'text/plain'],
    ['epl', '{"error":"expired"}', 'application/octet-stream'],
    ['zpl', '   ', 'text/plain'],
    ['epl', 'N\nP1', 'application/json'],
]);

it('keeps expired label response bodies out of transport exception messages', function (int $status) {
    $url = 'https://ws-etikett.paketomat.at/secure/label.pdf';
    Http::fake([$url => Http::response('private recipient address and token', $status)]);

    try {
        app(LaravelDpd::class)->downloadLabel($url);
        test()->fail('Expected a transport exception.');
    } catch (DpdTransportException $exception) {
        expect($exception->getMessage())->not->toContain('private', $url)
            ->and($exception->rawBody)->toBe('private recipient address and token')
            ->and($exception->statusCode)->toBe($status);
    }
})->with([403, 404, 410, 500]);

it('wraps download connection failures without exposing temporary URLs', function () {
    $url = 'https://ws-etikett.paketomat.at/secure/private-token.pdf';
    $original = new ConnectionException('Could not connect to '.$url);
    Http::fake(fn () => throw $original);

    try {
        app(LaravelDpd::class)->downloadLabel($url);
        test()->fail('Expected a transport exception.');
    } catch (DpdTransportException $exception) {
        expect($exception->getMessage())->not->toContain($url, 'private-token')
            ->and($exception->getPrevious())->toBe($original);
    }
});
