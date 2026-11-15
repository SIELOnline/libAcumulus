<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Collectors;

use Configuration;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;

/**
 * GiftWrappingFeeLineCollector contains PrestaShop specific {@see LineType::PaymentFee}
 * collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class GiftWrappingFeeLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A gift wrapping fee line with the mapped fields filled in.
     *
     * @throws \Exception
     *
     * @noinspection PhpMissingParentCallCommonInspection  Empty parent
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->addVatRateLookupMetadata($acumulusObject, $propertySources['invoice'], (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP'));
    }
}

