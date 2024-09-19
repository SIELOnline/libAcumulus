<?php
/**
 * Although we would like to use strict equality, i.e. including type equality,
 * unconditionally changing each comparison in this file will lead to problems
 * - API responses return each value as string, even if it is an int or float.
 * - The shop environment may be lax in its typing by, e.g. using strings for
 *   each value coming from the database.
 * - Our own config object is type aware, but, e.g, uses string for a vat class
 *   regardless the type for vat class ids as used by the shop itself.
 * So for now, we will ignore the warnings about non strictly typed comparisons
 * in this code, and we won't use strict_types=1.
 *
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode  This is a copy of the old Creator.
 */

namespace Siel\Acumulus\PrestaShop\Invoice;

use Address;
use Configuration;
use Exception;
use Order;
use OrderSlip;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use TaxManagerFactory;
use TaxRulesGroup;

/**
 * Creates a raw version of the Acumulus invoice from a PrestaShop {@see \Siel\Acumulus\PrestaShop\Invoice\Source}.
 *
 * Notes:
 * - If needed, PrestaShop allows us to get tax rates by querying the tax table
 *   because as soon as an existing tax rate gets updated it will get a new id,
 *   so old order details still point to a tax record with the tax rate as was
 *   used at the moment the order was placed.
 * - Credit notes can get a correction line. They get one if the total amount
 *   does not match the sum of the lines added so far. This can happen if an
 *   amount was entered manually, or if discount(s) applied during the sale were
 *   subtracted from the credit amount, but we could not find which discounts
 *   this were. However:
 *   - amount is excl. vat if not manually entered.
 *   - amount is incl. vat if manually entered (assuming administrators enter
 *     amounts incl. tax) and this is what gets listed on the credit PDF.
 *   - Manually entered amounts do not have vat defined, so users should try not
 *     to use them.
 *   - shipping_cost_amount is excl. vat.
 *   So this is never going to work in all situations!!!
 *
 * @property \Siel\Acumulus\PrestaShop\Invoice\Source $invoiceSource
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Creator extends BaseCreator
{
    protected Order $order;
    protected OrderSlip $creditSlip;
    /**
     * Precision: 1 of the amounts, probably the prince incl tax, is entered by
     * the admin and can thus be considered exact. The other is calculated by
     * the system and not rounded and can thus be considered to have a precision
     * better than 0.0001.
     *
     * However, we have had a support call where the precision, for a credit
     * note, turned out to be only 0.002. This was, apparently, with a price
     * entered excl. vat: 34,22; incl: 41,40378; (computed) vat: 7,18378.
     * The max-vat rate was just below 21%, so no match was made.
     */
    protected float $precision = 0.01;

    /**
     * {@inheritdoc}
     *
     * This override also initializes PrestaShop specific properties related to
     * the source.
     */
    protected function setInvoiceSource(Source $invoiceSource): void
    {
        parent::setInvoiceSource($invoiceSource);
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                break;
            case Source::CreditNote:
                $this->creditSlip = $this->invoiceSource->getSource();
                $this->order = $this->invoiceSource->getOrder()->getSource();
                break;
        }
    }

    protected function setPropertySources(): void
    {
        parent::setPropertySources();
        $this->propertySources['address_invoice'] = new Address($this->order->id_address_invoice);
        $this->propertySources['address_delivery'] = new Address($this->order->id_address_delivery);
        $this->propertySources['customer'] = $this->order->getCustomer();
    }

    /**
     * {@inheritdoc}
     *
     * This override returns can return an invoice line for orders. Credit slips
     * cannot have a wrapping line.
     */
    protected function getGiftWrappingLine(): array
    {
        // total_wrapping_tax_excl is not very precise (rounded to the cent) and
        // can easily lead to 1 cent off invoices in Acumulus (assuming that the
        // amount entered is based on a nicely rounded amount incl tax). So we
        // recalculate this ourselves by looking up the tax rate.
        $result = [];

        if ($this->invoiceSource->getType() === Source::Order && $this->order->gift && !Number::isZero($this->order->total_wrapping_tax_incl)) {
            /** @var string[] $metaCalculatedFields */
            $metaCalculatedFields = [];
            $wrappingEx = $this->order->total_wrapping_tax_excl;
            $wrappingExLookedUp = (float) Configuration::get('PS_GIFT_WRAPPING_PRICE');
            // Increase precision if possible.
            if (Number::floatsAreEqual($wrappingEx, $wrappingExLookedUp, 0.005)) {
                $wrappingEx = $wrappingExLookedUp;
                $metaCalculatedFields[] = Tag::UnitPrice;
                $precision = $this->precision;
            } else {
                $precision = 0.01;
            }
            $wrappingInc = $this->order->total_wrapping_tax_incl;
            $wrappingVat = $wrappingInc - $wrappingEx;
            $metaCalculatedFields[] = Meta::VatAmount;

            $vatLookupTags = $this->getVatRateLookupMetadata($this->order->id_address_invoice, (int) Configuration::get('PS_GIFT_WRAPPING_TAX_RULES_GROUP'));
            $result = [
                    Tag::Product => $this->t('gift_wrapping'),
                    Tag::UnitPrice => $wrappingEx,
                    Meta::UnitPriceInc => $wrappingInc,
                    Tag::Quantity => 1,
                      ] + $this->getVatRangeTags($wrappingVat, $wrappingEx, 0.01 + $precision, $precision)
                      + $vatLookupTags;
            $result[Meta::FieldsCalculated] = $metaCalculatedFields;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override checks if the fields 'payment_fee' and 'payment_fee_rate'
     * are set and, if so, uses them to add a payment fee line.
     *
     * These fields are set by the PayPal with a fee module but seem generic
     * enough to also be used by other modules that allow for payment fees.
     *
     * For now, only orders can have a payment fee, so $sign is superfluous,
     * but if in future versions payment fees can appear on order slips as well
     * the code can already handle that.
     */
    protected function getPaymentFeeLine(): array
    {
        /** @var Order|OrderSlip $source */
        $source = $this->invoiceSource->getSource();
        /** @noinspection MissingIssetImplementationInspection */
        if (isset($source->payment_fee, $source->payment_fee_rate) && (float) $source->payment_fee !== 0.0) {
            $sign = $this->invoiceSource->getSign();
            $paymentInc = $sign * $source->payment_fee;
            $paymentVatRate = (float) $source->payment_fee_rate;
            $paymentEx = $paymentInc / (100.0 + $paymentVatRate) * 100;
            $paymentVat = $paymentInc - $paymentEx;
            $result = [
              Tag::Product => $this->t('payment_costs'),
              Tag::Quantity => 1,
              Tag::UnitPrice => $paymentEx,
              Meta::UnitPriceInc => $paymentInc,
              Tag::VatRate => $paymentVatRate,
              Meta::VatRateSource => VatRateSource::Exact,
              Meta::VatAmount => $paymentVat,
              Meta::FieldsCalculated => [Tag::UnitPrice, Meta::VatAmount],
            ];

            $this->invoice[Tag::Customer][Tag::Invoice][Meta::Totals]->add($paymentInc, null, $paymentEx);
            return $result;
        }
        return parent::getPaymentFeeLine();
    }

    /**
     * Looks up and returns vat rate metadata.
     *
     * @param int $addressId
     * @param int $taxRulesGroupId
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId: int
     *   - Meta::VatClassName: string
     *   - Meta::VatRateLookup: float
     *   - Meta::VatRateLookupLabel: string
     */
    protected function getVatRateLookupMetadata(int $addressId, int $taxRulesGroupId): array
    {
        try {
            if (!empty($taxRulesGroupId)) {
                $taxRulesGroup = new TaxRulesGroup($taxRulesGroupId);
                $address = new Address($addressId);
                $taxManager = TaxManagerFactory::getManager($address, $taxRulesGroupId);
                $taxCalculator = $taxManager->getTaxCalculator();
                $result = [
                    Meta::VatClassId => $taxRulesGroup->id,
                    Meta::VatClassName => $taxRulesGroup->name,
                    Meta::VatRateLookup => $taxCalculator->getTotalRate(),
                    Meta::VatRateLookupLabel => $taxCalculator->getTaxesName(),
                ];
            } else {
                $result = [
                    Meta::VatClassId => Config::VatClass_Null,
                ];
            }
        } catch (Exception) {
            $result = [];
        }
        return $result;
    }
}
