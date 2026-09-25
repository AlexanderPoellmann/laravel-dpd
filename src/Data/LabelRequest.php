<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\LabelOption;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class LabelRequest
{
    /** @var non-empty-list<Parcel> */
    public array $parcels;

    public int $parcelCount;

    /**
     * @param  Parcel|list<Parcel>  $parcels
     * @param  list<string>  $references
     * @param  int|null  $parcelCount  Deprecated: if supplied, must match the number of parcels.
     */
    public function __construct(
        public Address $recipient,
        Parcel|array $parcels,
        public Products $products,
        public DateTimeInterface $shippingDate,
        ?int $parcelCount = null,
        public ?Sender $sender = null,
        public LabelFormat $format = LabelFormat::Pdf,
        public LabelOption|string|null $option = null,
        public ?string $customerReference = null,
        public bool $edi = false,
        public array $references = [],
        public ?string $invoiceNumber = null,
    ) {
        $this->parcels = self::normalizeParcels($parcels);
        $this->parcelCount = count($this->parcels);

        if ($parcelCount !== null && $parcelCount !== $this->parcelCount) {
            throw new InvalidArgumentException('DPD parcelCount must match the number of supplied parcels; supply one Parcel per label.');
        }

        self::validateReferences($this->references);

        if ($this->invoiceNumber !== null && mb_strlen($this->invoiceNumber) > 50) {
            throw new InvalidArgumentException('DPD invoice number may not exceed 50 characters.');
        }

        $latestShippingDate = (new DateTimeImmutable('today', $this->shippingDate->getTimezone()))->modify('+25 days');

        if ($this->shippingDate->format('Ymd') > $latestShippingDate->format('Ymd')) {
            throw new InvalidArgumentException('DPD shipping date may not be more than 25 days in the future.');
        }

        $firstParcel = $this->parcels[0];
        $this->products->validateParcelType($firstParcel->type);
        $product1 = $this->products->product1 instanceof Product1 ? $this->products->product1->value : $this->products->product1;

        foreach ($this->parcels as $parcel) {
            if ($parcel->type !== $firstParcel->type) {
                throw new InvalidArgumentException('DPD label requests require the same parcel type for every parcel.');
            }

            if ($parcel->volume() !== $firstParcel->volume()) {
                throw new InvalidArgumentException('DPD documents one volume per label request; use identical dimensions or separate requests.');
            }

            if (mb_strtoupper($this->recipient->countryCode) === 'DE' && $parcel->weightInGrams === null) {
                throw new InvalidArgumentException('DPD requires every parcel weight for shipments to Germany.');
            }

            if (($parcel->weightInGrams === null) !== ($firstParcel->weightInGrams === null)) {
                throw new InvalidArgumentException('DPD parcel weights must be supplied for every parcel or omitted for all parcels.');
            }

            if ($product1 === Product1::SmallParcel->value && $parcel->weightInGrams > 3000) {
                throw new InvalidArgumentException('DPD small parcels may not exceed 3,000 grams.');
            }
        }

        if ($firstParcel->type === ParcelType::ParcelShop
            && ($this->recipient->customerNumber === null || $this->recipient->contactPerson === null)) {
            throw new InvalidArgumentException('DPD parcel shop delivery requires customerNumber (shop ID) and contactPerson.');
        }

        if (($firstParcel->type === ParcelType::Primetime
                || in_array(mb_strtoupper($this->recipient->countryCode), ['RO', 'BG'], true))
            && ! $this->recipient->phone) {
            throw new InvalidArgumentException('DPD requires a recipient phone number for primetime, Romania, and Bulgaria.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $option = $this->option instanceof LabelOption ? $this->option->value : ($this->option ?? '');

        return [
            'vdat' => $this->shippingDate->format('Ymd'),
            'pakanz' => (string) $this->parcelCount,
            'empfaenger' => $this->recipient->toRecipientArray(),
            'paket' => [
                'liefernr' => implode('~', $this->references),
                'rechnungsnr' => $this->invoiceNumber ?? '',
                'pakettyp' => $this->parcels[0]->type->value,
                'gewicht' => $this->parcels[0]->weightInGrams === null
                    ? ''
                    : implode('~', array_map(fn (Parcel $parcel): string => (string) $parcel->weightInGrams, $this->parcels)),
                'volumen' => $this->parcels[0]->volume(),
            ],
            ...$this->products->toArray(),
            'absender' => ($this->sender ?? new Sender)->toArray(),
            'dfu' => $this->edi ? '1' : '0',
            'format' => $this->format->value,
            'kreferenz' => $this->customerReference ?? '',
            'optionen' => $option,
        ];
    }

    /** @return non-empty-list<Parcel> */
    private static function normalizeParcels(Parcel|array $parcels): array
    {
        $parcels = $parcels instanceof Parcel ? [$parcels] : array_values($parcels);

        if ($parcels === [] || count($parcels) > 20) {
            throw new InvalidArgumentException('DPD parcel count must be between 1 and 20.');
        }

        foreach ($parcels as $parcel) {
            if (! $parcel instanceof Parcel) {
                throw new InvalidArgumentException('DPD label requests must contain only Parcel objects.');
            }
        }

        return $parcels;
    }

    /** @param array<array-key, mixed> $references */
    private static function validateReferences(array $references): void
    {
        if (count($references) > 10) {
            throw new InvalidArgumentException('DPD supports at most ten delivery references.');
        }

        foreach ($references as $reference) {
            if (! is_string($reference) || $reference === '' || str_contains($reference, '~')) {
                throw new InvalidArgumentException('DPD delivery references must be nonempty strings without a tilde.');
            }
        }

        if (mb_strlen(implode('~', $references)) > 210) {
            throw new InvalidArgumentException('DPD delivery references may not exceed 210 characters including separators.');
        }
    }
}
