<?php

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Enums\Product1;

final readonly class Products
{
    /**
     * Product 2-7 values can be a scalar service code or a keyed structure as documented by DPD,
     * e.g. ['hv' => '1500000'] or ['pred' => 'mail@example.com'].
     *
     * @param  array<string, string>|string|null  $product2
     * @param  array<string, string>|string|null  $product3
     * @param  array<string, string>|string|null  $product4
     * @param  array<string, string>|string|null  $product5
     * @param  array<string, string>|string|null  $product6
     * @param  array<string, string>|string|null  $product7
     */
    public function __construct(
        public Product1|string $product1,
        public array|string|null $product2 = null,
        public array|string|null $product3 = null,
        public array|string|null $product4 = null,
        public array|string|null $product5 = null,
        public array|string|null $product6 = null,
        public array|string|null $product7 = null,
    ) {}

    public static function normalParcel(): self
    {
        return new self(Product1::NormalParcel);
    }

    public static function smallParcel(): self
    {
        return new self(Product1::SmallParcel);
    }

    public function withHigherInsurance(int $amountInCents): self
    {
        return new self(
            product1: $this->product1,
            product2: ['hv' => (string) $amountInCents],
            product3: $this->product3,
            product4: $this->product4,
            product5: $this->product5,
            product6: $this->product6,
            product7: $this->product7,
        );
    }

    public function withPredict(string $email): self
    {
        return new self(
            product1: $this->product1,
            product2: $this->product2,
            product3: $this->product3,
            product4: $this->product4,
            product5: $this->product5,
            product6: ['pred' => $email],
            product7: $this->product7,
        );
    }

    /** @return array<string, array|string> */
    public function toArray(): array
    {
        return [
            'produkt1' => $this->product1 instanceof Product1 ? $this->product1->value : $this->product1,
            'produkt2' => $this->product2 ?? '',
            'produkt3' => $this->product3 ?? '',
            'produkt4' => $this->product4 ?? '',
            'produkt5' => $this->product5 ?? '',
            'produkt6' => $this->product6 ?? '',
            'produkt7' => $this->product7 ?? '',
        ];
    }
}
