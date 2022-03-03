<?php
/**
 * @noinspection PhpClassConstantAccessedViaChildClassInspection
 */

namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor as BaseCompletor;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

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
    protected function completeLineTotals()
    {
        parent::completeLineTotals();

        if ($this->source->getType() === Source::CreditNote) {
            $discountAmountInc = 0.0;
            $discountLineAmountInc = 0.0;

            $invoiceLines = $this->invoice[Tag::Customer][Tag::Invoice][Tag::Line];
            foreach ($invoiceLines as $line) {
                if (isset($line[Meta::LineDiscountAmountInc])) {
                    $discountAmountInc += $line[Meta::LineDiscountAmountInc];
                }

                if ($line[Meta::LineType] === Creator::LineType_Discount) {
                    if (isset($line[Meta::LineAmountInc])) {
                        $discountLineAmountInc += $line[Meta::LineAmountInc];
                    } elseif (isset($line[Meta::UnitPriceInc])) {
                        $discountLineAmountInc += $line[Tag::Quantity] * $line[Meta::UnitPriceInc];
                    }
                }
            }

            if (!Number::floatsAreEqual($discountAmountInc, $discountLineAmountInc)) {
                foreach ($invoiceLines as $line) {
                    if ($line[Meta::LineType] === Creator::LineType_Shipping && isset($line[Meta::LineDiscountAmountInc])) {
                        $line[Meta::LineDiscountAmountInc] += $discountLineAmountInc - $discountAmountInc;
                    }
                }
            }
        }
    }
}
