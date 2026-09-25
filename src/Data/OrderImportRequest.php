<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use DateTimeInterface;

final readonly class OrderImportRequest
{
    /**
     * @param  array<string, string>|string|null  $product2
     * @param  array<string, string>|string|null  $product3
     * @param  array<string, string>|string|null  $product4
     * @param  array<string, string>|string|null  $product5
     * @param  array<string, string>|string|null  $product6
     * @param  array<string, string>|string|null  $product7
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
        public array|string|null $product2 = null,
        public array|string|null $product3 = null,
        public array|string|null $product4 = null,
        public array|string|null $product5 = null,
        public array|string|null $product6 = null,
        public array|string|null $product7 = null,
        public ?string $barcode = null,
        public ?string $option = null,
    ) {}

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
            'produkt1' => $this->product1 instanceof Product1 ? $this->product1->value : $this->product1,
            'produkt2' => $this->product2 ?? '',
            'produkt3' => $this->product3 ?? '',
            'produkt4' => $this->product4 ?? '',
            'produkt5' => $this->product5 ?? '',
            'produkt6' => $this->product6 ?? '',
            'produkt7' => $this->product7 ?? '',
            'barcode' => $this->barcode ?? '',
            'optionen' => $this->option ?? '',
        ];
    }
}
