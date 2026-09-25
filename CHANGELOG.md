# Changelog

All notable changes to `laravel-dpd` will be documented in this file.

## 0.3.0 - 2026-09-25

- Integrate shared shipping contracts through `DpdShippingAdapter` for carrier identity, shipment creation, label downloads, and cancellation.
- Register the concrete adapter under `shipping.adapters` without globally binding shared capability interfaces.
- Keep DPD products, parcel types, and label formats on immutable adapter configurations.
- Preserve sender address additions and reject incomplete shipment results with `DpdShipmentCreationException`, retaining the native result for recovery.
- Add adapter regression coverage and document shared DTO mapping, installation, and failure handling.

## 0.2.0 - 2026-09-25

- Add `serviceStatus()` for WEB.Service availability; retain `status()` as a deprecated forwarding alias on the client and facade.
- Add opt-in sanitized request started/succeeded/failed Laravel events for the REST transport and label downloads. Events include timing and outcome metadata only; no automatic logging is added.
- Add `downloadLabel()` and binary `LabelDocument` results with validated DPD URLs, disabled redirects, and format/MIME metadata.
- Expose structured `DpdError` values on API exceptions and label results, including documented service-specific matchcodes and global errors inside successful envelopes.
- Keep raw transport response bodies in explicit diagnostic properties instead of exception messages.
- Add typed Product 2–7 additional services with monetary, email, name, and product-family validation, plus explicit raw payload support.
- Compose services with `Products::with()`, retain existing helper methods and raw constructor arguments, and support typed order import service values.
- Model label shipments as an explicit list of parcels, deriving `pakanz` and encoding individual weights in DPD's tilde format.
- Move delivery references and invoice number to `LabelRequest`, with documented field limits.
- Validate parcel counts, individual weights, shared request fields, and shipping dates against WEB.Service 1.0.6.
- Add exact HTTP payload tests for single parcels, heterogeneous three-parcel shipments, and the documented REST example with omitted weights.
- Breaking: rename `LabelRequest`'s `parcel` argument/property to `parcels`; remove `Parcel`'s references/invoice fields. The optional legacy count argument must match the actual list. See README migration notes.

## 0.1.0 - 2026-09-25

- Initial DPD WEB.Service 1.0.6 REST integration.
- Add typed actions for label creation, label sheets, cancellation, reprint, self-booking lists, pickup orders, collection requests, WEB.omat order import, and service status.
- Add typed DPD requests, results, enums, transport/API exceptions, credential handling, timeouts, and retry configuration.
- Keep the package standalone with DPD-specific requests, results, and container bindings.
- Add Pest/Testbench coverage with Laravel HTTP fakes.
