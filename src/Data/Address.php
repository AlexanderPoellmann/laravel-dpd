<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use InvalidArgumentException;

final readonly class Address
{
    public function __construct(
        public string $name,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public ?string $customerNumber = null,
        public ?string $additional = null,
        public ?string $additional2 = null,
        public ?string $houseNumber = null,
        public ?string $doorNumber = null,
        public ?string $latitude = null,
        public ?string $longitude = null,
        public ?string $contactPerson = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {
        self::assertLength($this->name, 50, 'name');
        self::assertLength($this->street, 50, 'street');
        self::assertLength($this->postalCode, 8, 'postal code');
        self::assertLength($this->city, 30, 'city');

        if (strlen($this->countryCode) !== 2) {
            throw new InvalidArgumentException('DPD country code must be exactly two characters.');
        }
    }

    /** @return array<string, string> */
    public function toRecipientArray(): array
    {
        return [
            'name' => $this->name,
            'anschrift' => $this->street,
            'kdnr' => $this->customerNumber ?? '',
            'zusatz' => $this->additional ?? '',
            'zusatz2' => $this->additional2 ?? '',
            'hausnr' => $this->houseNumber ?? '',
            'tuernr' => $this->doorNumber ?? '',
            'plz' => $this->postalCode,
            'ort' => $this->city,
            'land' => mb_strtoupper($this->countryCode),
            'latitude' => $this->latitude ?? '',
            'longitude' => $this->longitude ?? '',
            'bezugsp' => $this->contactPerson ?? '',
            'tel' => $this->phone ?? '',
            'mail' => $this->email ?? '',
        ];
    }

    /** @return array<string, string> */
    public function toCollectionArray(string $prefix): array
    {
        return [
            $prefix.'name' => $this->name,
            $prefix.'zusatz' => $this->additional ?? '',
            $prefix.'kontakt' => $this->contactPerson ?? '',
            $prefix.'land' => mb_strtoupper($this->countryCode),
            $prefix.'plz' => $this->postalCode,
            $prefix.'ort' => $this->city,
            $prefix.'strasse' => trim($this->street.' '.($this->houseNumber ?? '')),
            ...($prefix === 'a' ? [
                'amail' => $this->email ?? '',
                'atel' => $this->phone ?? '',
            ] : []),
        ];
    }

    private static function assertLength(string $value, int $maximum, string $field): void
    {
        if ($value === '') {
            throw new InvalidArgumentException("DPD {$field} is required.");
        }

        if (mb_strlen($value) > $maximum) {
            throw new InvalidArgumentException("DPD {$field} may not exceed {$maximum} characters.");
        }
    }
}
