<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Source as BaseSource;

/**
 * OC3 specific code for an OpenCart {@see \Siel\Acumulus\OpenCart\Invoice\Source}.
 */
class Source extends BaseSource
{
    public function getOrderTotalLines(): array
    {
        if (!isset($this->orderTotalLines)) {
            $this->orderTotalLines = $this->getOrderModel()->getOrderTotals($this->shopObject['order_id']);
        }
        return $this->orderTotalLines;
    }

    protected function getOrderProducts(): array
    {
        return $this->getOrderModel()->getOrderProducts($this->getId());
    }
}
