<?php

declare(strict_types=1);

namespace AlexanderPoellmann\LaravelDpd\Shipping;

use AlexanderPoellmann\LaravelDpd\Data\Address as DpdAddress;
use AlexanderPoellmann\LaravelDpd\Data\Label as DpdLabel;
use AlexanderPoellmann\LaravelDpd\Data\LabelRequest;
use AlexanderPoellmann\LaravelDpd\Data\Parcel as DpdParcel;
use AlexanderPoellmann\LaravelDpd\Data\Products;
use AlexanderPoellmann\LaravelDpd\Data\Sender;
use AlexanderPoellmann\LaravelDpd\Enums\LabelFormat;
use AlexanderPoellmann\LaravelDpd\Enums\ParcelType;
use AlexanderPoellmann\LaravelDpd\Exceptions\DpdShipmentCreationException;
use AlexanderPoellmann\LaravelDpd\LaravelDpd;
use AlexanderPoellmann\Shipping\Contracts\CancelsShipments;
use AlexanderPoellmann\Shipping\Contracts\Carrier;
use AlexanderPoellmann\Shipping\Contracts\CreatesShipments;
use AlexanderPoellmann\Shipping\Contracts\DownloadsLabels;
use AlexanderPoellmann\Shipping\Data\Address;
use AlexanderPoellmann\Shipping\Data\CancellationResult;
use AlexanderPoellmann\Shipping\Data\Label;
use AlexanderPoellmann\Shipping\Data\Parcel;
use AlexanderPoellmann\Shipping\Data\Shipment;
use AlexanderPoellmann\Shipping\Data\ShipmentResult;
use AlexanderPoellmann\Shipping\Data\TrackingNumber;
use DateTimeImmutable;
use LogicException;

final readonly class DpdShippingAdapter implements CancelsShipments, Carrier, CreatesShipments, DownloadsLabels
{
    public function __construct(
        private LaravelDpd $client,
        private ?Products $products = null,
        private ParcelType $parcelType = ParcelType::Dpd,
        private LabelFormat $labelFormat = LabelFormat::Pdf,
    ) {}

    public function carrier(): string
    {
        return 'dpd';
    }

    public function forProducts(Products $products): self
    {
        return new self($this->client, $products, $this->parcelType, $this->labelFormat);
    }

    public function forParcelType(ParcelType $parcelType): self
    {
        return new self($this->client, $this->products, $parcelType, $this->labelFormat);
    }

    public function withLabelFormat(LabelFormat $labelFormat): self
    {
        return new self($this->client, $this->products, $this->parcelType, $labelFormat);
    }

    public function createShipment(Shipment $shipment): ShipmentResult
    {
        if ($this->products === null) {
            throw new LogicException('Configure DPD products with forProducts() before creating a shipment.');
        }

        $result = $this->client->createLabel(new LabelRequest(
            recipient: $this->recipient($shipment->recipient),
            parcels: array_map($this->parcel(...), $shipment->parcels),
            products: $this->products,
            shippingDate: $shipment->shippingDate ?? new DateTimeImmutable('today'),
            sender: $this->sender($shipment->sender),
            format: $this->labelFormat,
            customerReference: $shipment->reference,
        ));

        if (count($result->labels) !== count($shipment->parcels) || ! $result->successful()) {
            throw new DpdShipmentCreationException($result);
        }

        $labels = array_map(function (DpdLabel $label): Label {
            $trackingNumber = $label->trackingNumber !== null
                ? new TrackingNumber($label->trackingNumber)
                : null;

            return new Label(
                trackingNumber: $trackingNumber,
                url: $label->url,
                format: strtolower($this->labelFormat->value),
            );
        }, $result->labels);

        return new ShipmentResult(
            trackingNumbers: array_values(array_filter(array_map(
                static fn (Label $label): ?TrackingNumber => $label->trackingNumber,
                $labels,
            ))),
            labels: $labels,
        );
    }

    public function downloadLabel(Label $label): Label
    {
        if ($label->url === null) {
            throw new LogicException('DPD can only download labels that contain a URL.');
        }

        $document = $this->client->downloadLabel($label->url);

        return $label->withContents(
            contents: $document->contents,
            mimeType: $document->mimeType,
            format: strtolower($document->format->value),
        );
    }

    public function cancelShipment(TrackingNumber $trackingNumber): CancellationResult
    {
        $result = $this->client->cancelLabel($trackingNumber->value);

        return new CancellationResult(
            trackingNumber: new TrackingNumber($result->trackingNumber),
            cancelled: $result->cancelled,
        );
    }

    private function recipient(Address $address): DpdAddress
    {
        return new DpdAddress(
            name: $address->name,
            street: $address->street,
            postalCode: $address->postalCode,
            city: $address->city,
            countryCode: $address->countryCode,
            additional: $address->name2,
            additional2: $address->additional,
            houseNumber: $address->houseNumber,
            contactPerson: $address->contactPerson,
            phone: $address->phone,
            email: $address->email,
        );
    }

    private function sender(Address $address): Sender
    {
        return new Sender(
            name: $address->name,
            address: trim($address->street.' '.($address->houseNumber ?? '')),
            address2: implode(', ', array_filter(
                [$address->name2, $address->additional],
                static fn (?string $line): bool => $line !== null && trim($line) !== '',
            )),
            postalCode: $address->postalCode,
            city: $address->city,
            countryCode: $address->countryCode,
            phoneSenderName: $address->contactPerson,
            phone: $address->phone,
            emailSenderName: $address->contactPerson,
            email: $address->email,
        );
    }

    private function parcel(Parcel $parcel): DpdParcel
    {
        return new DpdParcel(
            type: $this->parcelType,
            weightInGrams: $parcel->weightInGrams,
            lengthInMillimeters: $parcel->lengthInMillimeters,
            widthInMillimeters: $parcel->widthInMillimeters,
            heightInMillimeters: $parcel->heightInMillimeters,
        );
    }
}
