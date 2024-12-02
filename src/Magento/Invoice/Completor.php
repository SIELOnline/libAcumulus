<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor as BaseCompletor;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

/**
 * Class Completor
 */
class Completor extends BaseCompletor
{
    /**
     * {@inheritdoc}
     *
     * @todo: Still the same in Magento 2?
     * !Magento bug!
     * In credit memos, the discount amount from discount lines may differ from
     * the summed discount amounts per line. This occurs because refunded
     * shipping costs do not advertise any discount amount.
     *
     * So, if the comparison fails, we correct the discount amount on the shipping
     * line so that SplitKnownDiscountLine::checkPreconditions() will pass.
     */
    protected function completeLineTotals(): void
    {
        parent::completeLineTotals();

        if ($this->source->getType() === Source::CreditNote) {
            $discountAmountInc = 0.0;
            $discountLineAmountInc = 0.0;

            $invoiceLines = $this->invoice->getLines();
            foreach ($invoiceLines as $line) {
                if ($line->metadataExists(Meta::LineDiscountAmountInc)) {
                    $discountAmountInc += $line->metadataGet(Meta::LineDiscountAmountInc);
                }

                if ($line->metadataGet(Meta::SubType) === LineType::Discount) {
                    if ($line->metadataExists(Meta::LineAmountInc)) {
                        $discountLineAmountInc += $line->metadataGet(Meta::LineAmountInc);
                    } elseif ($line->metadataExists(Meta::UnitPriceInc)) {
                        $discountLineAmountInc += $line->quantity * $line->metadataGet(Meta::UnitPriceInc);
                    }
                }
            }

            if (!Number::floatsAreEqual($discountAmountInc, $discountLineAmountInc)) {
                foreach ($invoiceLines as $line) {
                    if ($line->metadataGet(Meta::SubType) === LineType::Shipping && $line->metadataExists(Meta::LineDiscountAmountInc)) {
                        $line->metadataSet(
                            Meta::LineDiscountAmountInc,
                            $line->metadataGet(Meta::LineDiscountAmountInc) + $discountLineAmountInc - $discountAmountInc
                        );
                        $line->metadataSet(Meta::LineDiscountAmountIncCorrected, true);
                    }
                }
            }
        }
    }
}
