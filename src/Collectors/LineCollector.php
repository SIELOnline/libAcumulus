<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Meta;

/**
 * Collects invoice line data from the shop and the module's settings.
 *
 * Web shops may not store all properties as defined by Acumulus. So we also collect a lot
 * of metadata, think of unit price including VAT or line price instead of unit price; vat
 * amount instead of vat rate. The idea is that enough information is collected to be able
 * to complete each line.
 *
 * Properties that can be mapped:
 * - string $itemNumber
 * - string $product
 * - string $nature *
 * - float $unitPrice
 * - float $vatRate
 * - float $quantity
 * - float $costPrice
 *
 * Metadata that is "often" mapped:
 * - {@see \Siel\Acumulus\Meta::UnitPriceInc}
 * - {@see \Siel\Acumulus\Meta::VatAmount}
 * - {@see \Siel\Acumulus\Meta::LineAmount}
 * - {@see \Siel\Acumulus\Meta::LineAmountInc}
 * - {@see \Siel\Acumulus\Meta::LineVatAmount}
 * - ...
 *
 * To be able to complete a line, lots of other metadata may be collected in the logic
 * phase, think of information like:
 * - {@see \Siel\Acumulus\Meta::VatClassId}
 * - {@see \Siel\Acumulus\Meta::VatClassName}
 * - {@see \Siel\Acumulus\Meta::VatRateLookup}
 * - {@see \Siel\Acumulus\Meta::VatRateLookupLabel}
 * - {@see \Siel\Acumulus\Meta::VatRateLookupSource}
 * - {@see \Siel\Acumulus\Meta::PrecisionUnitPrice} and many other for other amounts.
 * - ...
 *
 * Properties that may be based on configuration, if not mapped:
 * - string $nature, though it will be rare that Nature can be mapped from data stored in
 *   the web shop.
 */
class LineCollector extends SubTypedCollector
{
}
