<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\HikaShop\Collectors;

use Siel\Acumulus\Collectors\CollectorManager as BaseCollectorManager;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * CollectorManager contains HikaShop specific collecting code.
 */
class CollectorManager extends BaseCollectorManager
{
    /**
     * This OpenCart override adds shipping lines based on the order's total lines with
     * code = 'shipping'.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     * @param \Siel\Acumulus\Joomla\HikaShop\Invoice\Source $source
     */
    protected function collectShippingLines(Invoice $invoice, Source $source): void
    {
        $lineCollector = $this->getContainer()->getCollector(DataType::Line, LineType::Shipping);
        $lineMappings = $this->getMappings()->getFor(LineType::Shipping);

        $shippingInfos = $source->getOrderShippingInfos();
        foreach ($shippingInfos as $key => $shippingInfo) {
            $this->addPropertySource('shippingInfo', [$key => $shippingInfo]);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
            foreach ($line->getChildren() as $child) {
                $invoice->addLine($child);
            }
            $line->removeChildren();
            $this->removePropertySource('shippingInfo');
        }
    }
}
