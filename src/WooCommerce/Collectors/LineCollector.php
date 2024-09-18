<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Collectors;

use Siel\Acumulus\collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Meta;
use WC_Product;
use WC_Tax;

/**
 * ItemLineCollector contains WooCommerce specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class LineCollector extends BaseLineCollector
{
    /**
     * Product price precision in WC3: one of the prices is entered by the
     * administrator and may be assumed exact. The computed one is based on the
     * subtraction/addition of 2 amounts, so has a precision that may be twice
     * as worse. WC tended to round amounts to the cent, but does not seem to
     * any longer do so.
     *
     * However, we still get reports of missed vat rates because they are out of
     * range, so we remain on the safe side and only use higher precision when
     * possible.
     */
    protected float $precision = 0.01;

    /**
     * Looks up and returns vat rate metadata for product lines.
     * A product has a tax class. A tax class can have multiple tax rates,
     * depending on the region of the customer. We use the customers address in
     * the raw invoice that is being created, to get the possible rates.
     *
     * @param string|null $taxClassId
     *   The tax class of the product. For the default tax class it can be
     *   'standard' or the empty string. For no tax class at all, it will be
     *   PluginConfig::VatClass_Null.
     *   @todo: Can it be null?
     */
    protected function addVatRateLookupMetadataByTaxClass(Line $line, ?string $taxClassId, Invoice $invoice): void
    {
        if ($taxClassId === null) {
            $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
        } else {
            // '' denotes the 'standard' tax class, use 'standard' in metadata,
            // '' when searching.
            if ($taxClassId === '') {
                $taxClassId = 'standard';
            }
            $line->metadataSet(Meta::VatClassId,sanitize_title($taxClassId));
            if ($taxClassId === 'standard') {
                $taxClassId = '';
            }
            // Vat class name is the non-sanitized version of the id
            // and thus does not convey more information: don't add.
            $line->metadataAdd(Meta::VatRateLookup, null, true);
            $line->metadataAdd(Meta::VatRateLookupLabel, null, true);

            // Find applicable vat rates. We use WC_Tax::find_rates() to find
            // them.
            /** @noinspection NullPointerExceptionInspection  getCustomer() will NOT return null */
            $args = [
                'tax_class' => $taxClassId,
                'country' => $invoice->getCustomer()->getFiscalAddress()->countryCode,
                'city' => $invoice->getCustomer()->getFiscalAddress()->city ?? '',
                'postcode' => $invoice->getCustomer()->getFiscalAddress()->postalCode ?? '',
            ];
            $taxRates = WC_Tax::find_rates($args);
            foreach ($taxRates as $taxRate) {
                // Set here to overwrite the defaults above, add later.
                $line->metadataAdd(Meta::VatRateLookup, $taxRate['rate']);
                $line->metadataAdd(Meta::VatRateLookupLabel, $taxRate['label']);
            }
        }
    }

    /**
     * Returns whether the prices entered by an admin include taxes or not.
     *
     * @return bool
     *   True if the prices as entered by an admin include VAT, false if they are
     *   entered ex VAT.
     */
    protected function productPricesIncludeTax(): bool
    {
        /** @noinspection PhpUndefinedFunctionInspection   false positive */
        return wc_prices_include_tax();
    }

    /**
     * Returns whether the price inc can be considered realistic.
     * Precondition: product prices as entered by the shop manager include tax
     *  and thus can be considered to be expressed in cents.
     * If a price inc is not considered realistic, we should not recalculate the
     * product price ex based on the product price inc after we have obtained a
     * corrected vat rate.
     *
     * @param float $productPriceInc
     *   The product price including vat found on an item line, this includes
     *   any discount.
     * @param float[] $taxes
     *   May be passed as strings.
     * @param \WC_Product|null $product
     *   The product that has the given price inc and taxes.
     *
     * @return string
     *   true if the price inc can be considered realistic, false otherwise.
     */
    protected function isPriceIncRealistic(float $productPriceInc, array $taxes, ?WC_Product $product): string
    {
        $reason = '';
        // Given the precondition that product prices as entered include vat, we
        // consider a price in cents realistic.
        if ((Number::isRounded($productPriceInc, 2))) {
            $reason = "price inc is rounded: $productPriceInc";
        }
        // If the price equals the actual product price, we consider it
        // realistic. Note that there may be valid reasons that the price differs
        // from the actual price, e.g. a price change since the order was placed,
        // or a discount that has been applied to the item line.
        if ($product !== null) {
            $productPriceOrg = $product->get_price();
            if (Number::floatsAreEqual($productPriceInc, $productPriceOrg, 0.000051)) {
                $reason = "item price inc ($productPriceInc) = product price inc ($productPriceOrg)";
            }
        }
        if (!Number::areRounded($taxes, 2)) {
            $reason = sprintf('not all taxes are rounded => taxes are realistic (%s)',
                str_replace('"', "'", json_encode($taxes, Meta::JsonFlags)));
        }
        return $reason;
    }
}
