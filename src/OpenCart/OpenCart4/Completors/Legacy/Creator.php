<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Completors\Legacy;

use Siel\Acumulus\OpenCart\Completors\Legacy\Creator as BaseCreator;

/**
 * OC4 specific invoice Creator code.
 */
class Creator extends BaseCreator
{
    protected function getOrderProductOptions(array $item): array
    {
        return $this->getOrderModel()->getOptions($item['order_id'], $item['order_product_id']);
    }

    protected function getOrderProducts(): array
    {
        return $this->getOrderModel()->getProducts($this->invoiceSource->getId());
    }
}
