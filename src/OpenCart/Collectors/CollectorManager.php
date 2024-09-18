<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * CollectorManager contains OpenCart specific collecting code.
 */
class CollectorManager extends \Siel\Acumulus\Collectors\CollectorManager
{
    public const LineTypeToCode = [
        LineType::Shipping => 'shipping',
        LineType::Discount => 'coupon',
        LineType::Voucher => 'voucher',
    ];

    /**
     * This OpenCart override adds shipping lines based on the order's total lines with
     * code = 'shipping'.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     * @param \Siel\Acumulus\OpenCart\Invoice\Source $source
     */
    protected function collectShippingLines(Invoice $invoice, Source $source): void
    {
        $shippingTotalLines = $source->getOrderTotalLines(static::LineTypeToCode[LineType::Shipping]);
        foreach ($shippingTotalLines as $totalLine) {
            $lineCollector = $this->getContainer()->getCollector(DataType::Line, LineType::Shipping);
            $lineMappings = $this->getMappings()->getFor(LineType::Shipping);
            $this->addPropertySource('totalLine', $totalLine);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
            $this->removePropertySource('totalLine');
        }
    }

}
