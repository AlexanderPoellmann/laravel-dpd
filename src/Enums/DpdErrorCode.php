<?php

namespace AlexanderPoellmann\LaravelDpd\Enums;

enum DpdErrorCode: string
{
    case InvalidUsername = 'ER01';
    case InvalidPassword = 'ER02';
    case InvalidClient = 'ER03';
    case InvalidName = 'ER04';
    case InvalidAddress = 'ER05';
    case InvalidPostalCode = 'ER06';
    case InvalidCity = 'ER07';
    case InvalidCountry = 'ER08';
    case MissingPhone = 'ER09';
    case MissingSerialNumber = 'ER10';
    case ShippingDateTooFar = 'ER11';
    case WeightLimitExceeded = 'ER12';
    case MissingParcelShopData = 'ER13';
    case InvalidDestinationForProduct = 'ER14';
    case InvalidParcelCount = 'ER15';
    case InvalidProduct1 = 'PR01';
    case InvalidProduct2 = 'PR02';
    case InvalidProduct3 = 'PR03';
    case InvalidProduct4 = 'PR04';
    case InvalidProduct5 = 'PR05';
    case InvalidProduct6 = 'PR06';
    case InvalidProduct7 = 'PR07';
    case InvalidCancellationUsername = 'FR01';
    case InvalidCancellationPassword = 'FR02';
    case InvalidCancellationClient = 'FR03';
    case InvalidTrackingNumber = 'FR04';
    case InvalidDateFormat = 'FR05';
    case AlreadyCancelled = 'FR06';
    case InvalidDateRange = 'FR07';
    case NumberRangeUnavailable = 'NK01';
    case NumberRangeUpdateFailed = 'NK02';
    case NumberRangeExceeded = 'NK03';
    case NoValidParcelNumber = 'NK04';
    case InvalidOrderClient = 'AU01';
    case OrderUpdateFailed = 'AU02';
    case OrderSaveFailed = 'AU03';
    case OrderUpdateNotAllowed = 'AU04';
    case OrderSaveGeneralError = 'AU05';
}
