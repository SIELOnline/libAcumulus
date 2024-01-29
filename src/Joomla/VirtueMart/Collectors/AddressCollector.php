<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Siel\Acumulus\Collectors\AddressCollector as BaseAddressCollector;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Meta;
use VmModel;

/**
 * AddressCollector for VirtueMart.
 */
class AddressCollector extends BaseAddressCollector
{
    /**
     * @param \Siel\Acumulus\Data\Address $acumulusObject
     *
     * @noinspection MissingIssetImplementationInspection  class vObject does
     *    create real properties for all fields.
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        // Country code lookup:
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $shopCountryId = $acumulusObject->metadataGet(Meta::ShopCountryId);
        if (!empty($shopCountryId)) {
            /** @var \VirtueMartModelCountry $countryModel */
            $countryModel = VmModel::getModel('country');
            $country = $countryModel->getData($shopCountryId);
            if (!empty($country->country_2_code)) {
                $acumulusObject->countryCode = $country->country_2_code;
            }
            if (!empty($country->country_name)) {
                \vmLanguage::loadJLang('com_virtuemart_countries');
                $key = "COM_VIRTUEMART_COUNTRY_$country->country_3_code";
                $language = Factory::getApplication()->getLanguage();
                $name = $language->hasKey($key) ? $language->_($key) : $country->country_name;
                $acumulusObject->metadataSet(Meta::ShopCountryName, $name);
            }
        }
        parent::collectLogicFields($acumulusObject);
    }
}
