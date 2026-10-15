<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Collectors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Collectors\InvoiceCollector as BaseInvoiceCollector;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Whmcs\Helpers\LocalApiTrait;

/**
 * InvoiceCollector for WHMCS.
 *
 * Some WHMCS peculiarities:
 * - The price stored in the 'amount' column of the 'tblinvoiceitems' table is the amount
 *   as entered by the admin (which is either excluding or including vat). To determine if
 *   it is the amount ex or inc vat, you can either look at:
 *   - The 'TaxType' config setting, but if that setting was ever changed, you're in
 *     trouble.
 *   - Whether the 'subtotal' or 'total' column of 'tblinvoices' equals the 'amount'
 *     totals of 'tblinvoiceitems'. However, this may not be fool-proof due to the
 *     'credit' and 'tax2' columns in 'tblinvoices'.
 * - The 'amount' column is the only amount stored in rows of this table! The table has a
 *   boolean column 'taxed' indicating whether vat was applied on this item or not. If vat
 *   was applied, the vat rate can be found at the invoice level in the column 'taxrate'.
 *
 * @todo: find out when and how the columns 'credit' and 'tax2' are used, so we can
 *   correct the comparison.
 */
class InvoiceCollector extends BaseInvoiceCollector
{
    use LocalApiTrait;

    /**
     * The single amount stored can be considered to be exact, thus we assume a quite high
     * precision.
     */
    protected float $precision = 0.0001;

    /**
     * Returns whether the setting 'TaxType' equals 'Inclusive' (true) or 'Exclusive' (false).
     */
    protected function productPricesIncludeTax(): bool
    {
        return $this->localApi()->productPricesIncludeTax();
    }

    /**
     * {@inheritdoc}
     *
     * This override adds the metadata field Meta::PricesIncludeVat. In WHMCS this is
     * especially important as that decides what price is stored on invoice lines!
     * (IMO thereby, making it impossible to change that later on ...)
     * So, we must use it to correct collected lines and invoice totals before starting
     * the complete phase.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $acumulusObject->metadataSet(Meta::ProductPricesIncludeVat, $this->productPricesIncludeTax());
    }

    /**
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     *
     * We correct:
     * - Prices on all lines: WHMCS has only 1 amount field on invoice lines and whether
     *   that field contains the price ex or inc depends on the setting whether prices
     *   were entered ex or inc vat. So we may have to copy or move between the 2 Acumulus
     *   amount fields.
     * - VAT rate on tax free lines: A (single) tax rate can be set at the WHMCS invoice
     *   level, and that rate is set on all lines with a field specification. Remove it
     *   for tax free lines (based on the 'taxed' field from a WHMCS invoice item and set
     *   in our Meta::Taxed field).
     *
     * We add:
     * - Precision of the amount stored on each line.
     */
    protected function collectAfter(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        parent::collectAfter($acumulusObject, $propertySources);

        $pricesIncludeVat = $this->invoicePricesIncludeTax($acumulusObject);
        $acumulusObject->metadataSet(Meta::InvoicePricesIncludeVat, $pricesIncludeVat);
        foreach ($acumulusObject->getLines() as $line) {
            // Move amounts from Meta::UnitPriceInc to UnitPrice
            // Move amount to Meta::AmountInc if they are vat inclusive.
            if (!$line->metadataGet(Meta::Taxed)) {
                // Non-taxed line: price inc equals price ex: copy amount (if necessary).
                if (!$line->metadataExists(Meta::UnitPriceInc) && isset($line->unitPrice)) {
                    $line->metadataSet(Meta::UnitPriceInc, $line->unitPrice);
                } elseif (!isset($line->unitPrice) && $line->metadataExists(Meta::UnitPriceInc)) {
                    $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc);
                }

                // The VAT rate may have been set by the field specifications: unset it:
                $line->vatRate = Api::VatFree;
            } else {
                // Taxed line: move amount if the wrong amount was set.
                // We do not assume which of the 2 fields was set.
                if ($pricesIncludeVat) {
                    // Move value from unit-price to unit-price inc.
                    if (!$line->metadataExists(Meta::UnitPriceInc) && isset($line->unitPrice)) {
                        $line->metadataSet(Meta::UnitPriceInc, $line->unitPrice);
                        unset($line->unitPrice);
                    }
                } else {
                    // Move value from unit-price inc to unit-price.
                    if (!isset($line->unitPrice) && $line->metadataExists(Meta::UnitPriceInc)) {
                        $line->unitPrice = $line->metadataGet(Meta::UnitPriceInc);
                        $line->metadataRemove(Meta::UnitPriceInc);
                    }
                }
            }

            // Add precision.
            if (isset($line->unitPrice)) {
                $line->metadataSet(Meta::PrecisionUnitPrice, $this->precision);
            }
            if ($line->metadataExists(Meta::UnitPriceInc)) {
                $line->metadataSet(Meta::PrecisionUnitPriceInc, $this->precision);
            }
        }
    }

    /**
     * Returns whether the invoice item prices include tax or not.
     *
     * In WHMCS invoice items, the 'amount' column of the 'tblinvoiceitems' table is the
     * amount as entered by the admin (which was either excluding or including vat).
     * To determine if it is the amount ex or inc vat, we look at:
     *  - Whether the 'subtotal' or 'total' column of 'tblinvoices' equals the 'amount'
     *    totals of 'tblinvoiceitems'. However, this may not be fool-proof due to the
     *    'credit' and 'tax2' columns in 'tblinvoices'.
     * Therefore, if we cannot decide this based on the comparison above, we fall back to:
     * - The 'TaxType' config setting.
     * But, if that setting has been changed since the creation of the invoice at hand,
     * we're in trouble. However, we cannot detect this, but nor is this likely to have
     * happened, so the fall-back is pretty sure.
     */
    protected function invoicePricesIncludeTax(Invoice $invoice): bool
    {
        /** @var \Siel\Acumulus\Invoice\Totals|null $totals */
        $totals = $invoice->metadataGet(Meta::Totals);
        if ($totals !== null) {
            $linesTotal = 0.0;
            foreach ($invoice->getLines() as $line) {
                if (!empty($line->unitPrice)) {
                    $linesTotal += $line->unitPrice;
                }
                if ($line->metadataExists(Meta::UnitPriceInc)) {
                    $linesTotal += $line->metadataGet(Meta::UnitPriceInc);
                }
            }
            if (Number::floatsAreEqual($linesTotal, $totals->amountEx)) {
                return false;
            }
            if (Number::floatsAreEqual($linesTotal, $totals->amountInc)) {
                return true;
            }
        }
        return $this->productPricesIncludeTax();
    }

}
