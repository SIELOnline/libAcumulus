<?php
namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor as BaseCompletor;

/**
 * Class Completor
 */
class Completor extends BaseCompletor
{
    /**
     * {@inheritdoc}
     *
     * !Magento bug!
     * In credit memos, the discount amount from discount lines may differ from
     * the summed discount amounts per line. This occurs when because refunded
     * shipping costs do not advertise any discount amount.
     *
     * So, if the comparison fails, we correct the discount amount on the shipping
     * line so that SplitKnownDiscountLine::checkPreconditions() will pass.
     */
    protected function completeLineTotals()
    {
        parent::completeLineTotals();

        if ($this->source->getType() === Source::CreditNote) {
            $discountAmountInc = 0.0;
            $discountLineAmountInc = 0.0;

            $invoiceLines = $this->invoice['customer']['invoice']['line'];
            foreach ($invoiceLines as $line) {
                if (isset($line['meta-line-discount-amountinc'])) {
                    $discountAmountInc += $line['meta-line-discount-amountinc'];
                }

                if ($line['meta-line-type'] === Creator::LineType_Discount) {
                    if (isset($line['meta-line-priceinc'])) {
                        $discountLineAmountInc += $line['meta-line-priceinc'];
                    } else if (isset($line['unitpriceinc'])) {
                        $discountLineAmountInc += $line['quantity'] * $line['unitpriceinc'];
                    }
                }
            }

            if (!Number::floatsAreEqual($discountAmountInc, $discountLineAmountInc)) {
                foreach ($invoiceLines as $line) {
                    if ($line['meta-line-type'] === Creator::LineType_Shipping && isset($line['meta-line-discount-amountinc'])) {
                        $line['meta-line-discount-amountinc'] += $discountLineAmountInc - $discountAmountInc;
                    }
                }
            }
        }
    }
}
