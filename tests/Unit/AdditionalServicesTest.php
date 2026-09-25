<?php

declare(strict_types=1);

use AlexanderPoellmann\LaravelDpd\Contracts\AdditionalService;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\Aviso;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\CashOnDelivery;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\ConstructionSiteDelivery;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\DepartmentDelivery;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\FreightCollect;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\HigherInsurance;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\IdentityCheck;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\LimitedQuantity;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\Predict;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\RawAdditionalService;
use AlexanderPoellmann\LaravelDpd\Data\AdditionalServices\ValuableParcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Enums\IdentityCheckType;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Enums\Product1;
use AlexanderPoellmann\LaravelDpd\Enums\ProductSlot;

it('serializes documented additional services into their assigned slots', function (AdditionalService $service, ProductSlot $slot, array|string $payload) {
    expect($service->slot())->toBe($slot)
        ->and($service->toPayload())->toBe($payload);
})->with([
    'higher insurance' => [new HigherInsurance(61750), ProductSlot::Product2, ['hv' => '61750']],
    'predict' => [new Predict('recipient@example.com'), ProductSlot::Product6, ['pred' => 'recipient@example.com']],
    'cash on delivery' => [new CashOnDelivery(36000, '81acd'), ProductSlot::Product3, ['nnbar' => '36000', 'refnnbar' => '81acd']],
    'cash on delivery without reference' => [new CashOnDelivery(1), ProductSlot::Product3, ['nnbar' => '1']],
    'identity' => [new IdentityCheck('Maria Muster'), ProductSlot::Product2, ['id' => 'Maria Muster']],
    'age 16' => [new IdentityCheck('Maria Muster', IdentityCheckType::Age16), ProductSlot::Product2, ['id16' => 'Maria Muster']],
    'age 18' => [new IdentityCheck('Maria Muster', IdentityCheckType::Age18), ProductSlot::Product2, ['id18' => 'Maria Muster']],
    'department' => [new DepartmentDelivery('Verkauf'), ProductSlot::Product2, ['abt' => 'Verkauf']],
    'aviso email' => [new Aviso('recipient@example.com'), ProductSlot::Product2, ['aviso' => 'recipient@example.com']],
    'aviso phone' => [new Aviso('+4369912345678'), ProductSlot::Product2, ['aviso' => '+4369912345678']],
    'valuable parcel' => [new ValuableParcel(81250), ProductSlot::Product4, ['wp' => '81250']],
    'freight collect' => [new FreightCollect, ProductSlot::Product5, 'UNFREI'],
    'construction site' => [new ConstructionSiteDelivery, ProductSlot::Product6, 'bau'],
    'limited quantity' => [new LimitedQuantity(1200), ProductSlot::Product7, ['LQ' => '1200']],
]);

it('enforces the insurance ranges in cents', function (string $class, int $amount) {
    expect(fn () => new $class($amount))->toThrow(InvalidArgumentException::class);
})->with([HigherInsurance::class, ValuableParcel::class])->with([-1, 0, 52000, 1500001]);

it('accepts both insurance boundaries', function (string $class, int $amount) {
    expect((new $class($amount))->amountInCents)->toBe($amount);
})->with([HigherInsurance::class, ValuableParcel::class])->with([52001, 1500000]);

it('enforces the primetime COD monetary range', function (int $amount) {
    expect(fn () => new CashOnDelivery($amount))->toThrow(InvalidArgumentException::class);
})->with([-1, 0, 700001]);

it('accepts COD limits and a reference of 200 characters', function (int $amount) {
    $reference = str_repeat('ä', 200);

    expect((new CashOnDelivery($amount, $reference))->toPayload())->toBe(['nnbar' => (string) $amount, 'refnnbar' => $reference])
        ->and(fn () => new CashOnDelivery($amount, $reference.'x'))->toThrow(InvalidArgumentException::class);
})->with([1, 700000]);

it('requires a Predict email address rather than a phone number', function (string $email) {
    expect(fn () => new Predict($email))->toThrow(InvalidArgumentException::class);
})->with(['', 'recipient', 'recipient@', '+4369912345678', "user@example.com\n", ' user@example.com']);

it('validates required identity and department names', function (string $class, string $name) {
    expect(fn () => new $class($name))->toThrow(InvalidArgumentException::class);
})->with([IdentityCheck::class, DepartmentDelivery::class])->with(['', '   ', str_repeat('ä', 31)]);

it('counts multibyte names as characters and preserves their spelling', function () {
    $name = str_repeat('ä', 30);

    expect((new IdentityCheck($name))->toPayload())->toBe(['id' => $name])
        ->and((new DepartmentDelivery($name))->toPayload())->toBe(['abt' => $name]);
});

it('rejects malformed AVISO contacts', function (string $contact) {
    expect(fn () => new Aviso($contact))->toThrow(InvalidArgumentException::class);
})->with(['', 'invalid@', '06641234567', '+', '+43abc', '+00436641234567', "user@example.com\n"]);

it('requires positive limited quantity mass without inventing an ADR limit', function (int $mass) {
    expect(fn () => new LimitedQuantity($mass))->toThrow(InvalidArgumentException::class);
})->with([0, -1]);

it('composes typed services immutably and preserves other slots', function () {
    $base = Products::normalParcel();
    $insured = $base->with(new HigherInsurance(1500000));
    $products = $insured->with(new Predict('recipient@example.com'))->with(new LimitedQuantity(1200));

    expect($base->product2)->toBeNull()
        ->and($insured->product6)->toBeNull()
        ->and($products->product2)->toBeInstanceOf(HigherInsurance::class)
        ->and($products->product6)->toBeInstanceOf(Predict::class)
        ->and($products->toArray())->toBe([
            'produkt1' => 'NP',
            'produkt2' => ['hv' => '1500000'],
            'produkt3' => '',
            'produkt4' => '',
            'produkt5' => '',
            'produkt6' => ['pred' => 'recipient@example.com'],
            'produkt7' => ['LQ' => '1200'],
        ]);
});

it('replaces the selected slot explicitly without mutating the original', function () {
    $products = (new Products(Product1::Primetime10))->with(new IdentityCheck('Maria Muster'));
    $updated = $products->with(new DepartmentDelivery('Verkauf'));

    expect($products->toArray()['produkt2'])->toBe(['id' => 'Maria Muster'])
        ->and($updated->toArray()['produkt2'])->toBe(['abt' => 'Verkauf']);
});

it('keeps the legacy helpers and routes them through typed validation', function () {
    expect(Products::normalParcel()->withHigherInsurance(1500000)->withPredict('recipient@example.com')->toArray())
        ->toBe(Products::normalParcel()->with(new HigherInsurance(1500000))->with(new Predict('recipient@example.com'))->toArray())
        ->and(fn () => Products::normalParcel()->withHigherInsurance(52000))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Products::normalParcel()->withPredict('invalid'))->toThrow(InvalidArgumentException::class);
});

it('accepts typed constructor arguments only in the correct slot', function () {
    expect((new Products(Product1::NormalParcel, product6: new Predict('recipient@example.com')))->toArray()['produkt6'])
        ->toBe(['pred' => 'recipient@example.com'])
        ->and(fn () => new Products(Product1::NormalParcel, product2: new Predict('recipient@example.com')))
        ->toThrow(InvalidArgumentException::class, 'belongs in product 6');
});

it('rejects clearly incompatible Product1 families for enum and string inputs', function (AdditionalService $service, Product1|string $product) {
    expect(fn () => (new Products($product))->with($service))->toThrow(InvalidArgumentException::class);
})->with([
    [new HigherInsurance(52001), Product1::Primetime10],
    [new Predict('recipient@example.com'), 'PM2'],
    [new CashOnDelivery(1), Product1::NormalParcel],
    [new IdentityCheck('Maria Muster'), 'KP'],
    [new DepartmentDelivery('Verkauf'), Product1::Return],
    [new Aviso('recipient@example.com'), Product1::ShopToShop],
    [new ValuableParcel(52001), Product1::NormalParcel],
    [new ConstructionSiteDelivery, 'NP'],
    [new FreightCollect, Product1::Saturday12],
    [new LimitedQuantity(1200), Product1::PrimetimeWindow],
]);

it('supports every documented primetime Product1 for primetime services', function (Product1 $product) {
    expect((new Products($product))->with(new CashOnDelivery(1))->toArray()['produkt3'])->toBe(['nnbar' => '1']);
})->with([Product1::Primetime10, Product1::Primetime12, Product1::Primetime17, Product1::PrimetimeWindow, Product1::Saturday10, Product1::Saturday12]);

it('validates parcel families as well as Product1', function () {
    expect(fn () => Products::normalParcel()->with(new Predict('recipient@example.com'))->validateParcelType(ParcelType::Primetime))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Products(Product1::Primetime17))->with(new CashOnDelivery(1))->validateParcelType(ParcelType::Dpd))
        ->toThrow(InvalidArgumentException::class);
});

it('preserves explicit raw payloads including nested future data in every slot', function (ProductSlot $slot) {
    $payload = ['future' => ['enabled' => true, 'amount' => 42]];
    $products = Products::normalParcel()->withRaw($slot, $payload);

    expect($products->toArray()['produkt'.$slot->value])->toBe($payload)
        ->and((new RawAdditionalService($slot, 'FUTURE'))->toPayload())->toBe('FUTURE');
})->with(ProductSlot::cases());

it('keeps legacy raw constructor payloads and future Product1 codes', function () {
    $products = new Products('FUTURE', product2: ['custom' => 'value'], product5: 'custom-code');

    expect($products->toArray()['produkt2'])->toBe(['custom' => 'value'])
        ->and($products->toArray()['produkt5'])->toBe('custom-code')
        ->and($products->with(new Predict('recipient@example.com'))->toArray()['produkt1'])->toBe('FUTURE')
        ->and((new Products(Product1::NormalParcel))->withRaw(ProductSlot::Product3, ['nnbar' => 'custom'])->toArray()['produkt3'])->toBe(['nnbar' => 'custom']);
});
