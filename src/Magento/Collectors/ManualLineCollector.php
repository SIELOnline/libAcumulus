<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * ManualLineCollector contains Magento specific {@see LineType::Manual} collecting
 * logic.
 *
 * Manual lines in Magento are stored at the creditmemo level.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ManualLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A manual line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectManualLine($acumulusObject);
    }

    /**
     * Collects the discount line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A manual line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectManualLine(Line $line): void
    {
        /** @var \Siel\Acumulus\Magento\Invoice\Source $source */
        $source = $this->getPropertySource('source');

        $line->product = $this->t('refund_adjustment');
        $line->unitPrice = $source->getShopObject()->getBaseAdjustment();
        $line->quantity = 1;
        // It is not possible to specify a vat amount/rate for a manually entered amount,
        // so we cannot do otherwise then treat it as vat free.
        $line->vatRate = -1;
    }
}
