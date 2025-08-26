<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Line;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Meta;

/**
 * CompleteMarginScheme changes some values for item lines with margin products.
 *
 * Margin scheme:
 * - Product should have a cost price and that must have been set on this line.
 * - VAT is only calculated over the margin, i.e. the unit price minus the cost price.
 * - Do not put VAT on invoice: send price incl VAT as unit price.
 * - But still send the VAT rate to Acumulus.
 *   (@todo: how can we get that rate if we only have a vat amount?)
 *
 * @todo: should we do this at the end, if the vat rate vs vat amount indicates that this
 *   indeed is a margin line.
 */
class CompleteMarginProducts extends BaseCompletorTask
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $this->completeMarginScheme($acumulusObject);
    }

    /**
     * Check for cost-price and margin-scheme.
     *
     * This is just a copy of old creator code that - quite early - acts on the fact that
     * a cost price was set and the configuration indicates that the margin scheme may be
     * used.
     */
    protected function completeMarginScheme(Line $line): void
    {
        if (!empty($line->costPrice) && $line->getType() === LineType::Item) {
            $marginProducts = match ($this->getMarginProducts()) {
                Config::MarginProducts_No, Config::MarginProducts_Unknown => false,
                Config::MarginProducts_Both => null,
                Config::MarginProducts_Only => true,
            };
            $line->metadataSet(Meta::MarginLine, $marginProducts);
            if ($marginProducts !== false) {
                if (isset($line->unitPrice)) {
                    $line->metadataSet(Meta::MarginLineOldUnitPrice, $line->unitPrice);
                }
                $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc);
                if ($line->metadataExists(Meta::PrecisionUnitPriceInc)) {
                    $line->metadataSet(Meta::PrecisionUnitPrice, $line->metadataGet(Meta::PrecisionUnitPriceInc));
                }
            }
        }
    }

    /**
     * Description.
     *
     * @return mixed
     *   Description.
     */
    private function getMarginProducts(): mixed
    {
        return $this->getContainer()->getConfig()->get('marginProducts');
    }
}
