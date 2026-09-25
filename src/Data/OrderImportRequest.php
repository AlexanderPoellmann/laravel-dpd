<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Contracts\AdditionalService;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use DateTimeInterface;

final readonly class OrderImportRequest
{
    private Products $products;

    /**
     * @param  AdditionalService|array<string, mixed>|string|null  $product2
     * @param  AdditionalService|array<string, mixed>|string|null  $product3
     * @param  AdditionalService|array<string, mixed>|string|null  $product4
     * @param  AdditionalService|array<string, mixed>|string|null  $product5
     * @param  AdditionalService|array<string, mixed>|string|null  $product6
     * @param  AdditionalService|array<string, mixed>|string|null  $product7
     */
    public function __construct(
        public string $orderNumber,
        public Address $recipient,
        public ParcelType $parcelType,
        public Product1|string $product1,
        public ?DateTimeInterface $shippingDate = null,
        public ?string $invoiceNumber = null,
        public ?int $weightInGrams = null,
        public int $parcelCount = 1,
        public AdditionalService|array|string|null $product2 = null,
        public AdditionalService|array|string|null $product3 = null,
        public AdditionalService|array|string|null $product4 = null,
        public AdditionalService|array|string|null $product5 = null,
        public AdditionalService|array|string|null $product6 = null,
        public AdditionalService|array|string|null $product7 = null,
        public ?string $barcode = null,
        public ?string $option = null,
    ) {
        $this->products = new Products($this->product1, $this->product2, $this->product3, $this->product4, $this->product5, $this->product6, $this->product7);
        $this->products->validateParcelType($this->parcelType);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'auftragsnr' => $this->orderNumber,
            'rechnungsnr' => $this->invoiceNumber ?? '',
            'kundennr' => $this->recipient->customerNumber ?? '',
            'name' => $this->recipient->name,
            'bezugsperson' => $this->recipient->contactPerson ?? '',
            'strasse' => $this->recipient->street,
            'hausnr' => $this->recipient->houseNumber ?? '',
            'tuernr' => $this->recipient->doorNumber ?? '',
            'zusatz' => $this->recipient->additional ?? '',
            'zusatz2' => $this->recipient->additional2 ?? '',
            'plz' => $this->recipient->postalCode,
            'ort' => $this->recipient->city,
            'land' => mb_strtoupper($this->recipient->countryCode),
            'tel' => $this->recipient->phone ?? '',
            'mail' => $this->recipient->email ?? '',
            'vdat' => $this->shippingDate?->format('Ymd') ?? '',
            'gewicht' => $this->weightInGrams !== null ? (string) $this->weightInGrams : '',
            'pakanz' => (string) $this->parcelCount,
            'pakettyp' => $this->parcelType->value,
            ...$this->products->toArray(),
            'barcode' => $this->barcode ?? '',
            'optionen' => $this->option ?? '',
        ];
    }
}
