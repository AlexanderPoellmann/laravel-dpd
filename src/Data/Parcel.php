<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use InvalidArgumentException;

final readonly class Parcel
{
    /**
     * @param  list<string>  $references
     */
    public function __construct(
        public ParcelType $type = ParcelType::Dpd,
        public ?int $weightInGrams = null,
        public ?int $lengthInMillimeters = null,
        public ?int $widthInMillimeters = null,
        public ?int $heightInMillimeters = null,
        public array $references = [],
        public ?string $invoiceNumber = null,
    ) {
        if ($this->weightInGrams !== null && ($this->weightInGrams < 1 || $this->weightInGrams > 31500)) {
            throw new InvalidArgumentException('DPD parcel weight must be between 1 and 31,500 grams.');
        }

        if (count($this->references) > 10) {
            throw new InvalidArgumentException('DPD supports at most ten delivery references.');
        }

        foreach ($this->dimensions() as $dimension) {
            if ($dimension !== null && ($dimension < 1 || $dimension > 9999)) {
                throw new InvalidArgumentException('DPD parcel dimensions must be between 1 and 9,999 mm.');
            }
        }
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'liefernr' => implode('~', $this->references),
            'rechnungsnr' => $this->invoiceNumber ?? '',
            'pakettyp' => $this->type->value,
            'gewicht' => $this->weightInGrams !== null ? (string) $this->weightInGrams : '',
            'volumen' => $this->volume(),
        ];
    }

    public function volume(): string
    {
        if (in_array(null, $this->dimensions(), true)) {
            return '';
        }

        return sprintf(
            '%04d%04d%04d',
            $this->lengthInMillimeters,
            $this->widthInMillimeters,
            $this->heightInMillimeters,
        );
    }

    /** @return array{0: ?int, 1: ?int, 2: ?int} */
    private function dimensions(): array
    {
        return [$this->lengthInMillimeters, $this->widthInMillimeters, $this->heightInMillimeters];
    }
}
