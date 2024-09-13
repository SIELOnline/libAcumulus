<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Source as BaseSource;

/**
 * OC4 specific code for an OpenCart {@see \Siel\Acumulus\OpenCart\Invoice\Source}.
 */
class Source extends BaseSource
{
    public function getOrderTotalLines(): array
    {
        if (!isset($this->orderTotalLines)) {
            $this->orderTotalLines = $this->getOrderModel()->getTotals($this->shopObject['order_id']);
        }
        return $this->orderTotalLines;
    }

    protected function getOrderProducts(): array
    {
        return $this->getOrderModel()->getProducts($this->getId());
    }
}
