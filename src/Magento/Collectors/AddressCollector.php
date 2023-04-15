<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;

/**
 * AddressCollector for WooCommerce.
 */
class AddressCollector extends \Siel\Acumulus\Collectors\AddressCollector
{
    use MagentoRegistryTrait;

    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        if (!empty($acumulusObject->countryCode)) {
            $country = $this->getCountryInformation();
            $countryInfo = $country->getCountryInfo($acumulusObject->countryCode);
            // or getFullNameEnglish() ...
            $acumulusObject->metadataSet(Meta::ShopCountryName, $countryInfo->getFullNameLocale());
        }
    }
}
