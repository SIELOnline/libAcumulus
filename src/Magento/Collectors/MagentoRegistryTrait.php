<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Model\CountryInformationAcquirer;
use Magento\Framework\App\Config as MagentoConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Tax\Model\Config as MagentoTaxConfig;
use Siel\Acumulus\Magento\Helpers\Registry;

/**
 * Provides methods to access the {@see \Siel\Acumulus\Magento\Helpers\Registry}
 * and objects that it can create.
 */
trait MagentoRegistryTrait
{
    protected function getScopeConfig(): ScopeConfigInterface
    {
        return $this->getRegistry()->create(MagentoConfig::class);
    }

    protected function getTaxConfig(): MagentoTaxConfig
    {
        return $this->getRegistry()->create(MagentoTaxConfig::class);
    }

    protected function getCountryInformation(): CountryInformationAcquirerInterface
    {
        return $this->getRegistry()->create(CountryInformationAcquirer::class);
    }

    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }

}
