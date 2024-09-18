<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\Collectors\CollectorManager as BaseCollectorManager;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * CollectorManager contains WooCommerce specific collecting code.
 */
class CollectorManager extends BaseCollectorManager
{
    /**
     * This WooCommerce override adds shipping lines based on the order items of
     * type = 'shipping'.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     * @param \Siel\Acumulus\OpenCart\Invoice\Source $source
     */
    protected function collectShippingLines(Invoice $invoice, Source $source): void
    {
        // Get the shipping lines for this order.
        /** @var \WC_Order_Item_Shipping[] $shippingItems */
        $shippingItems = $source->getShopObject()->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
        foreach ($shippingItems as $shippingItem) {
            $lineCollector = $this->getContainer()->getCollector(DataType::Line, LineType::Shipping);
            $lineMappings = $this->getMappings()->getFor(LineType::Shipping);
            $this->addPropertySource('shippingItem', $shippingItem);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
            $this->removePropertySource('shippingItem');
        }
    }
}
