<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Source as BaseSource;

/**
 * OC3 specific code for an OpenCart {@see \Siel\Acumulus\OpenCart\Invoice\Source}.
 */
class Source extends BaseSource
{
    protected function _getOrderTotalLines(): array
    {
        return $this->getOrderModel()->getOrderTotals($this->shopObject['order_id']);
    }

    protected function getOrderProducts(): array
    {
        return $this->getOrderModel()->getOrderProducts($this->getId());
    }
}
