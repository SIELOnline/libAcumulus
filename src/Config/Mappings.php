<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Meta;

use function in_array;
use function is_array;
use function is_string;

/**
 * Mappings returns sets of mappings for the different
 * {@see \Siel\Acumulus\Data\_Documentation data} objects.
 */
class Mappings
{
    public const validDataTypes = [
        DataType::Invoice,
        DataType::Customer,
        AddressType::Invoice,
        AddressType::Shipping,
        LineType::Item,
        EmailAsPdfType::Invoice,
        EmailAsPdfType::PackingSlip,
    ];

    private Config $config;
    private ShopCapabilities $shopCapabilities;

    /**
     * @var string[][][]
     *   See {@see getAll()}
     */
    private array $allMappings;
    private array $defaultMappings;

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
        $mappings = $this->getAll();
        return ($mappings[$forType] ?? []);
    }

    /**
     * Saves the mappings in the config.
     *
     * @param array $mappings
     *   A keyed 2-dimensional array that contains the mappings to store, this may be a
     *   subset of the possible keys. Keys that are not present will not be changed.
     *   Keys that do not differ from its default will not be stored.
     *
     * @return bool
     *   Success.
     *
     * @throws \JsonException
     */
    public function save(array $mappings): bool
    {
        $mappings = array_filter($mappings, static function (string $key) {
            return in_array($key, Mappings::validDataTypes, true);
        }, ARRAY_FILTER_USE_KEY);
        $existingMappings = $this->getAll();
        $mappings = array_replace_recursive($existingMappings, $mappings);
        $mappings = $this->getOverriddenValues($mappings, $this->getDefaults());
        $result = $this->getConfig()->save(['mappings' => $mappings]);
        // Clear internal cache.
        unset($this->allMappings);
        return $result;
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
    public function getAll(): array
    {
        if (!isset($this->allMappings)) {
            $this->allMappings = array_replace_recursive(
                $this->getDefaults(),
                $this->getUserDefined()
            );
        }
        return $this->allMappings;
    }

    /**
     * Returns the default mappings for all objects, shop specific and shop independent.
     *
     * @return string[][]
     *   The default mappings:
     *     - 1st dimension: keys being one of the Data\...Type::... constants.
     *     - 2nd dimension: keys being property names or metadata keys.
     *   Values are mappings for the specified property or metadata field. These are
     *   typically strings that may contain a field expansion specification, see
     *   {@see \Siel\Acumulus\Helpers\FieldExpander}, but occasionally, it may contain a
     *   value of another scalar type or even complex data when it is metadata.
     */
    protected function getDefaults(): array
    {
        if (!isset($this->defaultMappings)) {
            $this->defaultMappings = array_replace_recursive(
                $this->getShopIndependentDefaults(),
                $this->getShopDefaults()
            );
        }
        return $this->defaultMappings;
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
    protected function getUserDefined(): array
    {
        return $this->getConfig()->get(Config::Mappings);
    }

    /**
     * Returns the default mappings for the current shop
     *
     * These are hard coded in the shop's {@see \Siel\Acumulus\Config\ShopCapabilities}
     * class and override the defaults from {@see getDefaults()}.
     *
     * @return string[][]
     *   The default mappings for the current shop.
     */
    protected function getShopDefaults(): array
    {
        return $this->getShopCapabilities()->getDefaultShopMappings();
    }

    /**
     * Returns the default mappings that are shop independent.
     *
     * These are hard coded here in the {@see \Siel\Acumulus\Config\Mappings} class.
     *
     * @return string[][]
     *   The shop independent default mappings.
     */
    protected function getShopIndependentDefaults(): array
    {
        return [
            DataType::Invoice => [
                Fld::PaymentStatus => '[source::getPaymentStatus()]',
                Fld::PaymentDate => '[source::getPaymentDate()]',
                Fld::Description => '[source::getTypeLabel(2)+source::getReference()'
                    . '+"-"+source::getParent()::getTypeLabel(1)+source::getParent()::getReference()]',
                Meta::SourceType => '[source::getType()]',
                Meta::SourceId => '[source::getId()]',
                Meta::SourceReference => '[source::getReference()]',
                Meta::SourceDate => '[source::getDate()]',
                Meta::SourceStatus => '[source::getStatus()]',
                Meta::PaymentMethod => '[source::getPaymentMethod()]',
                Meta::ShopInvoiceId => '[source::getInvoiceId()]',
                Meta::ShopInvoiceReference => '[source::getInvoiceReference()]',
                Meta::ShopInvoiceDate => '[source::getInvoiceDate()]',
                Meta::Currency => '[source::getCurrency()]',
                Meta::Totals => '[source::getTotals()]',
            ],
            DataType::Customer => [
            ],
            AddressType::Invoice => [
                Fld::CountryCode => '[source::getCountryCode()|"nl"]',
            ],
            AddressType::Shipping => [
                Fld::CountryCode => '[source::getCountryCode()|"nl"]',
            ],
            EmailAsPdfType::Invoice => [
                Fld::Subject => 'Factuur voor [source::getTypeLabel(1)+source::getReference()'
                    . '+"-"+source::getParent()::getTypeLabel(1)+source::getParent()::getReference()]',
            ],
            EmailAsPdfType::PackingSlip => [
                Fld::Subject => 'Pakbon voor [source::getTypeLabel(1)+source::getReference()]',
            ],
            LineType::Item => [
// @todo: The adapter part of Product has been stalled temporarily: first move code from
//    the Creators to ItemLineCollectors without changing it, only then refactor that
//    code to use this.
//                Fld::ItemNumber => '[product::getReference()]',
//                Fld::Product => '[product::getName()]',
//                Fld::Quantity => '[product::getQuantity()]',
// @todo: are these 2 meta keys used by all webshops?
//                Meta::VatClassId => '[product::getVatClassId()]',
//                Meta::VatClassName => '[product::getVatClassName()]',
                Meta::Id => '[item::getId()]',
                Meta::ProductId => '[product::getId()]',
            ],
        ];
    }

    /**
     * Returns only the values that do differ from the defaults.
     */
    protected function getOverriddenValues(array $mappings, array $existingMappings): array
    {
        $result = [];
        foreach ($mappings as $key => $value) {
            $existingValue = $existingMappings[$key] ?? '';
            if (is_array($value)) {
                $result[$key] = $this->getOverriddenValues($value, $existingValue);
            } else {
                if (!is_string($existingValue)) {
                    $existingValue = json_encode($existingValue, Meta::JsonFlags);
                }
                if ($value !== $existingValue) {
                    $result[$key] = $value;
                }
            }
        }
        return $result;
    }
}
