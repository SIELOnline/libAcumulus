<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

/**
 * Mappings returns sets of mappings for the different
 * {@see \Siel\Acumulus\Data\_Documentation data} objects.
 */
class Mappings
{
    public const Invoice = 'invoice';
    public const ItemLine = 'itemLine';
    public const ShippingLine = 'shippingLine';
    public const PaymentLine = 'paymentLine';
    public const FeeLine = 'feeLine';
    public const Customer = 'customer';
    public const EmailInvoiceAsPdf = 'emailInvoiceAsPdf';
    public const EmailPackingSlipAsPdf = 'emailPackingSlipAsPdf';
    public const ShippingAddress = 'shippingAddress';
    public const InvoiceAddress = 'invoiceAddress';

    private Config $config;
    private ShopCapabilities $shopCapabilities;

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
     * mapping and pass them to the {@see \Siel\Acumulus\Collectors\Collector}
     * that can create the {@see \Siel\Acumulus\Data\AcumulusObject} of the
     * right type.
     *
     * @param string $for
     *   One of the Mappings::... constants, indicating for which object type
     *   the settings should be returned.
     *
     * @return array
     *   An array with as keys the property names and as values the mappings to
     *   be used to extract the value for the property.
     */
    public function get(string $for): array
    {
        $mappings = $this->getAll();
        return $mappings[$for] ?? [];
    }

    /**
     * Returns all mappings for all objects.
     *
     * If a given property has no mapping, no array entry will be set for that
     * property. This implies that all array entries are non-null.
     *
     * @return array[]
     *   All mappings for all objects.
     */
    protected function getAll(): array
    {
        return array_merge_recursive(
            $this->getDefaultShopMappings(),
            $this->getConfiguredMappings(),
        );

    }

    /**
     * Returns all  mappings that are stored in the configuration object.
     *
     * @return array[]
     *   The mappings that are overridden by the user.
     */
    protected function getConfiguredMappings(): array
    {
        return $this->getConfig()->getMappings();
    }

    /**
     * Returns the default mappings for the current shop.
     *
     * @return array[]
     *   The default mappings for the current shop.
     */
    protected function getDefaultShopMappings(): array
    {
        return $this->getShopCapabilities()->getDefaultShopMappings();
    }
}
