<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Exceptions\DpdResponseException;

final readonly class Label
{
    /** @var list<DpdError> */
    public array $errors;

    /** @param array<string, mixed> $raw */
    public function __construct(
        public ?string $url,
        public ?string $trackingNumber,
        public bool $saved,
        public ?string $errorCode,
        public ?string $barcodeContent,
        public ?string $customerReference,
        public ?string $digitalCodeUrl,
        public array $raw,
    ) {
        $this->errors = $this->errorCode !== null && $this->errorCode !== ''
            ? DpdError::parseMany($this->errorCode)
            : [];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        foreach (['label', 'paknr', 'err_code', 'barcodecontent', 'kreferenz', 'code2d'] as $field) {
            if (isset($payload[$field]) && ! is_string($payload[$field])) {
                throw new DpdResponseException("DPD returned an invalid label {$field}.");
            }
        }

        return new self(
            url: isset($payload['label']) ? (string) $payload['label'] : null,
            trackingNumber: isset($payload['paknr']) ? (new TrackingNumber($payload['paknr']))->value : null,
            saved: in_array($payload['saved'] ?? false, [true, 1, '1'], true),
            errorCode: isset($payload['err_code']) && $payload['err_code'] !== '' ? (string) $payload['err_code'] : null,
            barcodeContent: isset($payload['barcodecontent']) ? (string) $payload['barcodecontent'] : null,
            customerReference: isset($payload['kreferenz']) ? (string) $payload['kreferenz'] : null,
            digitalCodeUrl: isset($payload['code2d']) ? (string) $payload['code2d'] : null,
            raw: $payload,
        );
    }

    public function successful(): bool
    {
        return $this->errorCode === null && $this->url !== null && trim($this->url) !== '';
    }
}
