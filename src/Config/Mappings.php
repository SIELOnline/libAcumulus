<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

/**
 * Mappings returns sets of mappings for the different
 * {@see \Siel\Acumulus\Data\_Documentation data} objects.
 */
class Mappings
{
    public const Properties = Config::PropertyMappings;
    public const Metadata = Config::MetadataMappings;

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
     * Returns the property mappings for a given object type.
     *
     * The {@see \Siel\Acumulus\Collectors\CollectorManager} will retrieve these
     * mappings and pass them to the {@see \Siel\Acumulus\Collectors\Collector}
     * that can create the {@see \Siel\Acumulus\Data\AcumulusObject} of the
     * right type.
     *
     * @param string $forType
     *   One of the Data\...Type::... constants, indicating for which object
     *   type the mappings should be returned.
     *
     * @return array
     *   An array with as keys the property names and as values mappings for the
     *   specified property. These are typically strings that may contain a
     *   field expansion specification, see
     *   {@see \Siel\Acumulus\Helpers\FieldExpander}, but occasionally, it may
     *   contain a value of another scalar type.
     */
    public function getFor(string $forType): array
    {
        $mappings = $this->getAllMappings();
        return ($mappings[Mappings::Properties][$forType] ?? []) + ($mappings[Mappings::Metadata][$forType] ?? []);
    }

    /**
     * Returns all mappings for all objects.
     *
     * @return string[][][]
     *   The mappings that are stored in the config.
     *     - 1st dimension: 2 keys: Mappings::Properties and Mappings::Metadata.
     *     - 2nd dimension: keys being one of the Data\...Type::... constants.
     *     - 3rd dimension: keys being property names or metadata keys.
     *   Values are mappings for the specified property or metadata field. These
     *   are typically strings that may contain a field expansion specification,
     *   see {@see \Siel\Acumulus\Helpers\FieldExpander}, but occasionally, it
     *   may contain a value of another scalar type.
     */
    protected function getAllMappings(): array
    {
        if (!isset($this->allMappings)) {
            $defaultShopPropertyMappings = $this->getDefaultShopPropertyMappings();
            $defaultShopMetadataMappings = $this->getDefaultShopMetadataMappings();
            $configuredPropertyMappings = $this->getConfiguredPropertyMappings();
            $configuredMetaMappings = $this->getConfiguredMetaMappings();
            $this->allMappings = [
                Mappings::Properties => $defaultShopPropertyMappings,
                Mappings::Metadata => $defaultShopMetadataMappings,
            ];
            foreach ($configuredPropertyMappings as $dataType => $configuredMapping) {
                $this->allMappings[Mappings::Properties][$dataType] ??= [];
                $this->allMappings[Mappings::Properties][$dataType] += $configuredMapping;
            }
            foreach ($configuredMetaMappings as $dataType => $configuredMapping) {
                $this->allMappings[Mappings::Metadata][$dataType] ??= [];
                $this->allMappings[Mappings::Metadata][$dataType] += $configuredMapping;
            }
        }
        return $this->allMappings;

    }

    /**
     * Returns all property mappings that are stored in config.
     *
     * @return string[][]
     *   The default property mappings as stored in config.
     */
    protected function getConfiguredPropertyMappings(): array
    {
        return $this->getConfig()->get(Config::PropertyMappings);
    }

    /**
     * Returns all metadata mappings that are stored in config.
     *
     * @return string[][]
     *   The default metadata mappings as stored in config.
     */
    protected function getConfiguredMetaMappings(): array
    {
        return $this->getConfig()->get(Config::MetadataMappings);
    }

    /**
     * Returns the default property mappings for the current shop.
     *
     * @return string[][]
     *   The default property mappings for the current shop.
     */
    protected function getDefaultShopPropertyMappings(): array
    {
        return $this->getShopCapabilities()->getDefaultPropertyMappings();
    }

    /**
     * Returns the default metadata mappings for the current shop.
     *
     * @return string[][]
     *   The default metadata mappings for the current shop.
     */
    protected function getDefaultShopMetadataMappings(): array
    {
        return $this->getShopCapabilities()->getDefaultMetadataMappings();
    }
}
