<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Data;

use AlexanderPoellmann\LaravelDpd\Contracts\AdditionalService;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\HigherInsurance;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\Predict;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\RawAdditionalService;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;
use InvalidArgumentException;

final readonly class Products
{
    /**
     * Arrays and strings remain supported for backwards compatibility. Prefer typed services or withRaw().
     *
     * @param  AdditionalService|array<string, mixed>|string|null  $product2
     * @param  AdditionalService|array<string, mixed>|string|null  $product3
     * @param  AdditionalService|array<string, mixed>|string|null  $product4
     * @param  AdditionalService|array<string, mixed>|string|null  $product5
     * @param  AdditionalService|array<string, mixed>|string|null  $product6
     * @param  AdditionalService|array<string, mixed>|string|null  $product7
     */
    public function __construct(
        public Product1|string $product1,
        public AdditionalService|array|string|null $product2 = null,
        public AdditionalService|array|string|null $product3 = null,
        public AdditionalService|array|string|null $product4 = null,
        public AdditionalService|array|string|null $product5 = null,
        public AdditionalService|array|string|null $product6 = null,
        public AdditionalService|array|string|null $product7 = null,
    ) {
        foreach ($this->additionalProducts() as $slot => $service) {
            if ($service instanceof AdditionalService) {
                if ($service->slot()->value !== $slot) {
                    throw new InvalidArgumentException("DPD additional service belongs in product {$service->slot()->value}, not product {$slot}.");
                }

                $service->validateProduct($this->product1);
            }
        }
    }

    public static function normalParcel(): self
    {
        return new self(Product1::NormalParcel);
    }

    public static function smallParcel(): self
    {
        return new self(Product1::SmallParcel);
    }

    /** Returns a new Products instance, replacing the service in the same product slot. */
    public function with(AdditionalService $service): self
    {
        $slot = $service->slot();

        return new self(
            product1: $this->product1,
            product2: $slot === ProductSlot::Product2 ? $service : $this->product2,
            product3: $slot === ProductSlot::Product3 ? $service : $this->product3,
            product4: $slot === ProductSlot::Product4 ? $service : $this->product4,
            product5: $slot === ProductSlot::Product5 ? $service : $this->product5,
            product6: $slot === ProductSlot::Product6 ? $service : $this->product6,
            product7: $slot === ProductSlot::Product7 ? $service : $this->product7,
        );
    }

    /** @param array<string, mixed>|string $payload */
    public function withRaw(ProductSlot $slot, array|string $payload): self
    {
        return $this->with(new RawAdditionalService($slot, $payload));
    }

    public function withHigherInsurance(int $amountInCents): self
    {
        return $this->with(new HigherInsurance($amountInCents));
    }

    public function withPredict(string $email): self
    {
        return $this->with(new Predict($email));
    }

    public function validateParcelType(ParcelType $type): void
    {
        foreach ($this->additionalProducts() as $service) {
            if ($service instanceof AdditionalService) {
                $service->validateParcelType($type);
            }
        }
    }

    /** @return array<string, array<string, mixed>|string> */
    public function toArray(): array
    {
        $payload = ['produkt1' => $this->product1 instanceof Product1 ? $this->product1->value : $this->product1];

        foreach ($this->additionalProducts() as $slot => $service) {
            $payload['produkt'.$slot] = $service instanceof AdditionalService ? $service->toPayload() : ($service ?? '');
        }

        return $payload;
    }

    /** @return array<int, AdditionalService|array<string, mixed>|string|null> */
    private function additionalProducts(): array
    {
        return [2 => $this->product2, 3 => $this->product3, 4 => $this->product4, 5 => $this->product5, 6 => $this->product6, 7 => $this->product7];
    }
}
