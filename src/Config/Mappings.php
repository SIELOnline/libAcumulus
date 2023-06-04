<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

/**
 * Mappings returns sets of mappings for the different
 * {@see \Siel\Acumulus\Data\_Documentation data} objects.
 */
class Mappings
{
    public const Mappings = Config::Mappings;

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
     *   One of the Data\...Type::... constants, indicating for which object
     *   type the mappings should be returned.
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
            $this->allMappings = $this->getDefaultShopMappings();
            foreach ($this->getConfiguredMappings() as $dataType => $defaultMapping) {
                $this->allMappings[$dataType] ??= [];
                $this->allMappings[$dataType] += $defaultMapping;
            }
        }
        return $this->allMappings;

    }

    /**
     * Returns the default mappings (that are hard coded in config).
     *
     * @return string[][]
     *   The default mappings as are hard coded in config.
     */
    protected function getConfiguredMappings(): array
    {
        return $this->getConfig()->get(Config::Mappings);
    }

    /**
     * Returns the default mappings for the current shop
     *
     * These are hard coded in the shop's {@see \Siel\Acumulus\Config\ShopCapabilities}
     * class.
     *
     * @return string[][]
     *   The default mappings for the current shop.
     */
    protected function getDefaultShopMappings(): array
    {
        return $this->getShopCapabilities()->getDefaultShopMappings();
    }
}
