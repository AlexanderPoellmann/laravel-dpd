<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use DateTimeInterface;

final readonly class CollectionRequest
{
    public function __construct(
        public DateTimeInterface $date,
        public Address $pickupAddress,
        public Address $recipientAddress,
        public ParcelType $parcelType,
        public Product1|string $product1,
        public ?string $reference = null,
        public ?string $product2 = null,
        public ?int $parcelCount = null,
        public ?string $comment = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'datum' => $this->date->format('Ymd'),
            'referenz' => $this->reference ?? '',
            'paktyp' => $this->parcelType->value,
            'produkt1' => $this->product1 instanceof Product1 ? $this->product1->value : $this->product1,
            'produkt2' => $this->product2 ?? '',
            'anzahl' => $this->parcelCount !== null ? (string) $this->parcelCount : '',
            'bemerkung' => $this->comment ?? '',
            ...$this->pickupAddress->toCollectionArray('a'),
            ...$this->recipientAddress->toCollectionArray('e'),
        ];
    }
}
