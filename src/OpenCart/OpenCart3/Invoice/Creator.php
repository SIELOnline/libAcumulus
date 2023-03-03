<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Creator as BaseCreator;

/**
 * OC3 specific invoice Creator code.
 */
class Creator extends BaseCreator
{
    protected function getOrderProductOptions(array $item): array
    {
        return $this->getOrderModel()->getOrderOptions($item['order_id'], $item['order_product_id']);
    }

    protected function getOrderProducts(): array
    {
        return $this->getOrderModel()->getOrderProducts($this->invoiceSource->getId());
    }
}
