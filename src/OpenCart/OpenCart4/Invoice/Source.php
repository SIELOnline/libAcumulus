<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Invoice;

use Siel\Acumulus\OpenCart\Invoice\Source as BaseSource;

/**
 * OC4 specific code for an OpenCart
 * {@see \Siel\Acumulus\OpenCart\Invoice\Source}.
 */
class Source extends BaseSource
{
    /**
     * Returns a list of OpenCart order total records.
     *
     * These are shipment, other fee, tax, and discount lines.
     *
     * @return array[]
     *   The set of order total lines for this order. This set is ordered by
     *   sort_order, meaning that lines before the tax line are amounts ex vat
     *   and lines after are inc vat.
     */
    public function getOrderTotalLines(): array
    {
        if (!isset($this->orderTotalLines)) {
            $this->orderTotalLines = $this->getOrderModel()->getTotals($this->source['order_id']);
        }
        return $this->orderTotalLines;
    }
}
