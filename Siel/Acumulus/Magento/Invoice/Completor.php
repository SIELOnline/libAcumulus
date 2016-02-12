<?php
namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Completor as BaseCompletor;

/**
 * Class Completor
 */
class Completor extends BaseCompletor {

  /** @var null|array May point to the line to correct a discount amount. */
  protected $correctDiscountLine;

  protected function completeLineMetaData() {
    parent::completeLineMetaData();

    $invoiceLines = &$this->invoice['customer']['invoice']['line'];
    foreach($invoiceLines as &$line) {
      $calculatedFields = isset($line['meta-calculated-fields'])
        ? (is_array($line['meta-calculated-fields']) ? $line['meta-calculated-fields'] : explode(',', $line['meta-calculated-fields']))
        : array();

      if (in_array($line['meta-vatrate-source'], static::CorrectVatRateSources)) {

        if (isset($line['meta-line-discount-vatamount']) && !isset($line['meta-line-discount-amountinc'])) {
          $line['meta-line-discount-amountinc'] = $line['meta-line-discount-vatamount'] / $line['vatrate'] * (100 + $line['vatrate']);
          $calculatedFields[] = 'meta-line-discount-amountinc';
          $this->correctDiscountLine = &$line;
        }

        if (!empty($calculatedFields)) {
          $line['meta-calculated-fields'] = $calculatedFields;
        }
      }
    }
  }

  protected function completeLineTotals() {
    parent::completeLineTotals();

    // !Magento bug!
    // In credit memos, the DiscountAmount may differ from the summed discount
    // amounts per line, a.o. because refunded shipping costs do not advertise
    // any discount amount. Thus if the above comparison fails, we change the
    // discount line by "correcting" the discount amount.
    if ($this->source->getType() === Source::CreditNote) {
      $discountAmountInc = 0.0;
      $discountLineAmountInc = 0.0;

      $invoiceLines = $this->invoice['customer']['invoice']['line'];
      foreach ($invoiceLines as $line) {
        // Magento: we need the discount tax amounts on the separate lines to
        // correct the totals as at this point the discount line will not have
        // the unitprice set nor the vatamount/vatrate.
        if (isset($line['meta-line-discount-amountinc'])) {
          $discountAmountInc += $line['meta-line-discount-amountinc'];
        }

        if ($line['meta-line-type'] === Creator::LineType_Discount) {
          if (isset($line['meta-line-priceinc'])) {
            $discountLineAmountInc += $line['meta-line-priceinc'];
          }
          else if (isset($line['unitpriceinc'])) {
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
