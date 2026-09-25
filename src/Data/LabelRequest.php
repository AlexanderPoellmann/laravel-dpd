<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\LabelOption;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class LabelRequest
{
    public function __construct(
        public Address $recipient,
        public Parcel $parcel,
        public Products $products,
        public DateTimeInterface $shippingDate,
        public int $parcelCount = 1,
        public ?Sender $sender = null,
        public LabelFormat $format = LabelFormat::Pdf,
        public LabelOption|string|null $option = null,
        public ?string $customerReference = null,
        public bool $edi = false,
    ) {
        if ($this->parcelCount < 1 || $this->parcelCount > 20) {
            throw new InvalidArgumentException('DPD parcel count must be between 1 and 20.');
        }

        if ($this->parcel->type === ParcelType::ParcelShop
            && ($this->recipient->customerNumber === null || $this->recipient->contactPerson === null)) {
            throw new InvalidArgumentException('DPD parcel shop delivery requires customerNumber (shop ID) and contactPerson.');
        }

        if (($this->parcel->type === ParcelType::Primetime
                || in_array(mb_strtoupper($this->recipient->countryCode), ['RO', 'BG'], true))
            && ! $this->recipient->phone) {
            throw new InvalidArgumentException('DPD requires a recipient phone number for primetime, Romania, and Bulgaria.');
        }

        if (mb_strtoupper($this->recipient->countryCode) === 'DE' && $this->parcel->weightInGrams === null) {
            throw new InvalidArgumentException('DPD requires parcel weight for shipments to Germany.');
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
            'paket' => $this->parcel->toArray(),
            ...$this->products->toArray(),
            'absender' => ($this->sender ?? new Sender)->toArray(),
            'dfu' => $this->edi ? '1' : '0',
            'format' => $this->format->value,
            'kreferenz' => $this->customerReference ?? '',
            'optionen' => $option,
        ];
    }
}
