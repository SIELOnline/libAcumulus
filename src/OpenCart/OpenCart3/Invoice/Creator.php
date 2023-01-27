<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Creator as BaseCreator;

/**
 * Creates a raw version of the Acumulus invoice from an OpenCart
 * {@see \Siel\Acumulus\OpenCart\Invoice\Source}.
 */
class Creator extends BaseCreator
{
    /**
     * {@inheritDoc}
     */
    protected function getOrderProductOptions(array $item): array
    {
        return $this->getOrderModel()->getOrderOptions($item['order_id'], $item['order_product_id']);
    }

    protected function getOrderProducts(): array
    {
        return $this->getOrderModel()->getOrderProducts($this->invoiceSource->getId());
    }
}
