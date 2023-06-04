<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;

/**
 * Mappings returns sets of mappings for the different
 * {@see \Siel\Acumulus\Data\_Documentation data} objects.
 */
class Mappings
{
    private Config $config;
    private ShopCapabilities $shopCapabilities;

    /**
     * @var string[][][]
     *   See {@see getAllMappings()}
     */
    private array $allMappings;

    public function __construct(Config $config, ShopCapabilities $shopCapabilities)
    {
        $this->config = $config;
        $this->shopCapabilities = $shopCapabilities;
    }

    protected function getConfig(): Config
    {
        return $this->config;
    }

    protected function getShopCapabilities(): ShopCapabilities
    {
        return $this->shopCapabilities;
    }

    /**
     * Returns the mappings for a given object type.
     *
     * The {@see \Siel\Acumulus\Collectors\CollectorManager} will retrieve these
     * mappings and pass them to the {@see \Siel\Acumulus\Collectors\Collector}
     * that can create the {@see \Siel\Acumulus\Data\AcumulusObject} of the
     * right type.
     *
     * @param string $forType
     *   either one of the:
     *     - {@see \Siel\Acumulus\Data\DataType} constants, indicating for which object
     *       type the mappings should be returned.
     *     - {@see \Siel\Acumulus\Data\AddressType} constants for an
     *       {@see \Siel\Acumulus\Data\Address} object.
     *     - {@see \Siel\Acumulus\Data\EmailAsPdfTypeType} constants for an
     *       {@see \Siel\Acumulus\Data\EmailAsPdf} object.
     *     - {@see \Siel\Acumulus\Data\LineType} constants for a
     *       {@see \Siel\Acumulus\Data\Line} object.
     *
     * @return array
     *   An array with as keys property names or metadata keys, and as values mappings for
     *   the specified property or metadata value. These are typically strings that may
     *   contain a field expansion specification, see
     *   {@see \Siel\Acumulus\Helpers\FieldExpander}, but occasionally, it may contain a
     *   value of another scalar type or even complex data when it is metadata.
     */
    public function getFor(string $forType): array
    {
        $mappings = $this->getAllMappings();
        return ($mappings[$forType] ?? []);
    }

    /**
     * Returns all mappings for all objects.
     *
     * @return string[][]
     *   The mappings that are stored in the config.
     *     - 1st dimension: keys being one of the Data\...Type::... constants.
     *     - 2nd dimension: keys being property names or metadata keys.
     *   Values are mappings for the specified property or metadata field. These are
     *   typically strings that may contain a field expansion specification, see
     *   {@see \Siel\Acumulus\Helpers\FieldExpander}, but occasionally, it may contain a
     *   value of another scalar type or even complex data when it is metadata.
     */
    protected function getAllMappings(): array
    {
        if (!isset($this->allMappings)) {
            $this->allMappings = $this->getUserDefinedMappings();
            foreach ($this->getDefaultShopMappings() as $dataType => $defaultMapping) {
                $this->allMappings[$dataType] ??= [];
                $this->allMappings[$dataType] += $defaultMapping;
            }
            foreach ($this->getDefaultMappings() as $dataType => $defaultMapping) {
                $this->allMappings[$dataType] ??= [];
                $this->allMappings[$dataType] += $defaultMapping;
            }
        }
        return $this->allMappings;
    }

    /**
     * Returns the mappings that the user has overridden.
     *
     * These are stored in the {@see \Siel\Acumulus\Config\Config} to facilitate
     * migrating or recovery.
     *
     * @return string[][]
     *   The mappings that are overridden by the user.
     */
    protected function getUserDefinedMappings(): array
    {
        return $this->getConfig()->get(Config::Mappings);
    }

    /**
     * Returns the default mappings for the current shop
     *
     * These are hard coded in the shop's {@see \Siel\Acumulus\Config\ShopCapabilities}
     * class and override the defaults from {@see getDefaultMappings()}.
     *
     * @return string[][]
     *   The default mappings for the current shop.
     */
    protected function getDefaultShopMappings(): array
    {
        return $this->getShopCapabilities()->getDefaultShopMappings();
    }

    /**
     * Returns the default mappings
     *
     * These are hard coded in the {@see \Siel\Acumulus\Config\Config} class.
     *
     * @return string[][]
     *   The default mappings.
     */
    protected function getDefaultMappings(): array
    {
        return [
            DataType::Invoice => [
                Fld::PaymentStatus => '[source::getPaymentStatus()]',
                Fld::PaymentDate => '[source::getPaymentDate()]',
                Fld::Description => '[source::getTypeLabel(2)+source::getReference()'
                    . '+"-"+source::getParent()::getTypeLabel(1)+source::getParent()::getReference()]',
                Meta::ShopSourceType => '[source::getType()]',
                Meta::Id => '[source::getId()]',
                Meta::Reference => '[source::getReference()]',
                Meta::ShopSourceDate => '[source::getDate()]',
                Meta::Status => '[source::getStatus()]',
                Meta::PaymentMethod => '[source::getPaymentMethod()]',
                Meta::ShopInvoiceId => '[source::getShopInvoiceId()]',
                Meta::ShopInvoiceReference => '[source::getShopInvoiceReference()]',
                Meta::ShopInvoiceDate => '[source::getShopInvoiceDate()]',
                Meta::Currency => '[source::getCurrency()]',
                Meta::Totals => '[source::getTotals()]',
            ],
            DataType::Customer => [
            ],
            AddressType::Invoice => [
                Fld::CountryCode => '[source::getCountryCode()]',
                Meta::AddressType => AddressType::Invoice,
            ],
            AddressType::Shipping => [
                Fld::CountryCode => '[source::getCountryCode()]',
                Meta::AddressType => AddressType::Shipping,
            ],
            EmailAsPdfType::Invoice => [
                Fld::Subject => 'Factuur voor [source::getTypeLabel(1)+source::getReference()'
                    . '+"-"+source::getParent()::getTypeLabel(1)+source::getParent()::getReference()]',
            ],
            EmailAsPdfType::PackingSlip => [
                Fld::Subject => 'Pakbon voor [source::getTypeLabel(1)+source::getReference()]',
            ],
            LineType::Item => [
            ],
        ];
    }
}
