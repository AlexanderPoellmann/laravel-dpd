<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

final readonly class Sender
{
    public function __construct(
        public ?string $name = null,
        public ?string $address = null,
        public ?string $address2 = null,
        public ?string $postalCode = null,
        public ?string $city = null,
        public ?string $countryCode = null,
        public ?string $phoneSenderName = null,
        public ?string $phone = null,
        public ?string $emailSenderName = null,
        public ?string $email = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'name' => $this->name ?? '',
            'adresse' => $this->address ?? '',
            'adresse2' => $this->address2 ?? '',
            'plz' => $this->postalCode ?? '',
            'ort' => $this->city ?? '',
            'land' => $this->countryCode ? mb_strtoupper($this->countryCode) : '',
            'tel_name' => $this->phoneSenderName ?? '',
            'tel' => $this->phone ?? '',
            'mail_name' => $this->emailSenderName ?? '',
            'mail' => $this->email ?? '',
        ];
    }
}
