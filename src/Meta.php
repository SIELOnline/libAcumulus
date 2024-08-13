<?php
/**
 * Not all constants may have actual usages, in that case they are here for
 * completeness and future use/auto-completion.
 *
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace Siel\Acumulus;

/**
 * Meta defines string constants for meta tags used in Acumulus API messages.
 *
 * Metadata can be added to the Acumulus invoice structure for reasons of
 * support or to pass additional information from the creator phase to the
 * completor phase. In the latter case, some metadata is expected to be present
 * though perhaps only under certain conditions,  e.g. when a vat rate is
 * missing.
 *
 * Meta tags start with 'meta-', except the tags 'unitpriceinc' and 'vatamount',
 * for which one could imagine that one day the Acumulus API would accept these
 * instead of 'unitprice' and 'vatrate'. With either choice, Acumulus can
 * correctly process the invoice line.
 */
interface Meta
{
    /**
     * Set of json_encode flags we use to improve readability of metadata in xml messages.
     * Also see {@see \Siel\Acumulus\Helpers\Log::JsonFlags}.
     */
    public const JsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION;
    public const JsonFlagsLooseType = Meta::JsonFlags | JSON_NUMERIC_CHECK;

    // Tags that may be used on multiple levels.
    /**
     * The internal id of the level it is part of:
     * - Addresses (if available separately)
     * - Order line (Creator->Event: WooCommerce and Magento only).
     * - Customer (available via contactYourId)
     */
    public const Id = 'meta-id';
    /**
     * Messages can be added at any level and are placed at the level it applies to
     * (order, order line, ...). It tells that the code discovered a problem (warning) or
     * just gives some more info on what it did or how it obtained some value (info or
     * notice). Warnings are often the result of a failing sanity check (a check that
     * should result in true but didn't).
     */
    public const Error = 'meta-error';
    public const Warning = 'meta-warning';
    public const Notice = 'meta-notice';
    public const Info = 'meta-info';

    // Customer + addresses:
    public const MainAddressType = 'meta-main-address-type';
    public const ShopVatBasedOn = 'meta-shop-vat-based-on';
    public const ShopCountryId = 'meta-shop-country-code';
    public const ShopCountryName = 'meta-shop-country-name';
    /**
     * An {@see \Siel\Acumulus\Data\Address} object itself does not know which
     * type of address it represents.
     */
    public const AddressType = 'meta-address-type';

    // Invoice
    public const SourceType = 'meta-source-type';
    public const SourceId = 'meta-source-id';
    public const SourceReference = 'meta-source-reference';
    public const SourceStatus = 'meta-source-status';
    public const SourceDate = 'meta-source-date';
    public const PricesIncludeVat = 'meta-prices-include-vat';
    public const PrecisionPrice = 'meta-precision-price';
    public const PrecisionVat = 'meta-precision-vat';

    // Shop invoice linked to this source (if existing).
    public const ShopInvoiceId = 'meta-shop-invoice-id';
    public const ShopInvoiceReference = 'meta-shop-invoice-reference';
    public const ShopInvoiceDate = 'meta-shop-invoice-date';
    /**
     * Support: the payment method used for this order or refund.
     */
    public const PaymentMethod = 'meta-payment-method';

    // Invoice: Currency related meta tags.
    /**
     * Creator->Completor: Currency code the amounts are expressed in: ISO4217,
     * ISO 3166-1
     */
    public const Currency = 'meta-currency';
    /**
     * Creator->Completor: Conversion rate from the used currency to the shop's
     * default currency:
     *   amount in shop currency = CurrencyRate * amount in other currency
     * @deprecated We store a Currency object in meta-currency.
     */
    public const CurrencyRate = 'meta-currency-rate';
    /**
     * Creator->Completor: true if we should use the above meta info to convert
     * amounts, false if the amounts are already in the shop's default currency
     * (which should be euro) and this info is thus purely informational.
     * @deprecated We store a Currency object in meta-currency.
     */
    public const CurrencyDoConvert = 'meta-currency-do-convert';
    /**
     * Completor: Whether the currency rate
     *   should be inverted (shop default is not EUR, but order is in EUR).
     *   AND has been inverted (we invert of course only once).
     * @deprecated We store a Currency object in meta-currency.
     */
    public const CurrencyRateInverted = 'meta-currency-rate-inverted';

    /**
     * Invoice: Source totals meta tags.
     */
    public const Totals = 'meta-totals';
    /**
     * Invoice: Source vat breakdown (usage: Opencart?, ???).
     */
    public const VatBreakdown = 'meta-vat-breakdown';
    /**
     * Creator -> Completor: the total amount ex vat of the invoice.
     */
    public const InvoiceAmount = 'meta-total-amount';
    /**
     * Creator -> Completor: the total amount inc vat of the invoice.
     */
    public const InvoiceAmountInc = 'meta-total-amountinc';
    /**
     * Creator -> Completor: the total vat amount of the invoice.
     */
    public const InvoiceVatAmount = 'meta-total-vatamount';

    // Settings:
    public const AddEmailAsPdfSection = 'meta-add-email-as-pdf-section';

    // Line: Price and vat related meta tags.
    /**
     * Creator -> Completor/Strategy: Unit price inc vat (in addition to
     * unitpriceinc).
     */
    public const UnitPriceInc = 'unitpriceinc';
    /**
     * Creator -> Completor/Strategy: Amount of vat (per unit) (in addition to
     * vatrate).
     */
    public const VatAmount = 'vatamount';
    /**
     * Creator -> Completor/Strategy: How we got the vatrate (which source, or
     * how we computed it).
     */
    public const VatRateSource = 'meta-vatrate-source';
    /**
     * The minimum vat rate in case we have to compute it using 2 non-exact
     * amounts.
     */
    public const VatRateMin = 'meta-vatrate-min';
    /**
     * The maximum vat rate in case we have to compute it using 2 non-exact
     * amounts.
     */
    public const VatRateMax = 'meta-vatrate-max';
    /**
     * Debug: what fields have been calculated (as opposed to fetched from the
     * webshop.
     */
    public const FieldsCalculated = 'meta-fields-calculated';
    /**
     * Instruction: if and what price to recalculate when the exact vat rate is known.
     * Can be 'unitprice' or 'unitpriceinc' or not set.
     */
    public const RecalculatePrice = 'meta-recalculate-price';
    /**
     * Support: whether the unitprice(inc) has been recalculated.
     */
    public const RecalculatedPrice = 'meta-did-recalculate';
    /**
     * Support: unitprice(inc) as it was before being recalculated.
     */
    public const RecalculateOldPrice = 'meta-recalculate-old-price';
    /**
     * Creator -> Completor: (current) vat rate(s) looked up from e.g. the
     * product or shipping settings.
     */
    public const VatRateLookup = 'meta-vatrate-lookup';
    /**
     * Support: (current) name(s) of the looked up vat rate(s).
     */
    public const VatRateLookupLabel = 'meta-vatrate-lookup-label';
    /**
     * Support: source of the looked up vat rate.
     */
    public const VatRateLookupSource = 'meta-vatrate-lookup-source';
    /**
     * Support: matches of the looked up vat rate(s) with possible vat rates.
     */
    public const VatRateLookupMatches = 'meta-vatrate-lookup-matches';
    /**
     * Completor -> Strategy: Possible vat rates that lie in the VatRateMin to
     * VatRateMax range.
     */
    public const VatRateRangeMatches = 'meta-vatrate-range-matches';
    /**
     * Creator -> Completor: (current) vat class id looked up from e.g. the
     * product or shipping settings. Can be used to differentiate between vat
     * type 1 and 6 when a foreign vat rate equals that of NL.
     */
    public const VatClassId = 'meta-vatclass-id';
    /**
     * Support: (current) vat class name looked up from e.g. the
     * product or shipping settings.
     */
    public const VatClassName = 'meta-vatclass-name';

    // Line: Line amounts related meta tags.
    /**
     * Support: line amount ex vat, equals quantity * unitprice, but can have a
     * higher precision.
     */
    public const LineAmount = 'meta-line-amount';
    /**
     * Support: line amount inc vat, equals quantity * unitpriceinc, but can
     * have a higher precision.
     */
    public const LineAmountInc = 'meta-line-amountinc';
    /**
     * Support: line vat amount, equals quantity * vatamount, but can have a
     * higher precision.
     */
    public const LineVatAmount = 'meta-line-vatamount';
    /**
     * Creator->Strategy: the discount amount ex vat that was applied to this
     * line (used by the SplitKnownDiscountLine strategy, Magento only).
     */
    public const LineDiscountAmount = 'meta-line-discount-amount';
    /**
     * Creator->Strategy: the discount amount inc vat that was applied to this
     * line (used by the SplitKnownDiscountLine strategy, Magento only).
     */
    public const LineDiscountAmountInc = 'meta-line-discount-amountinc';
    public const LineDiscountAmountIncCorrected = 'meta-line-discount-amountinc-corrected';
    /**
     * Creator -> Strategy: the discount vat amount that was applied to this
     * line (used by the SplitKnownDiscountLine strategy, Magento only).
     */
    public const LineDiscountVatAmount = 'meta-line-discount-vatamount';

    // Line: Precision related meta tags.
    /** Support: the precision of the unit price ex vat. */
    public const PrecisionUnitPrice = 'meta-unitprice-precision';
    /**
     * Support: the precision of the unit price inc. vat.
     */
    public const PrecisionUnitPriceInc = 'meta-unitpriceinc-precision';
    /**
     * Support: the precision of the cost price.
     */
    public const PrecisionCostPrice = 'meta-costprice-precision';
    /**
     * Support: the precision of the vat amount.
     */
    public const PrecisionVatAmount = 'meta-vatamount-precision';

    // Invoice: Lines totals meta tags.
    /** Completor: the total amount ex vat of the invoice lines. */
    public const LinesAmount = 'meta-lines-amount';
    /**
     * Completor: the total amount inc vat of the invoice lines.
     */
    public const LinesAmountInc = 'meta-lines-amountinc';
    /**
     * Completor: the total vat amount of the invoice lines.
     */
    public const LinesVatAmount = 'meta-lines-vatamount';
    /**
     * Completor: which of the line totals are incomplete because that amount is
     * not (yet) known for all lines.
     */
    public const LinesIncomplete = 'meta-lines-incomplete';

    // Invoice: Vat type related meta tags.
    /**
     * Completor: Possible vat types for this line.
     */
    public const VatTypesPossible = 'meta-vattypes-possible';
    /**
     * Completor: Possible vat types for this invoice (and shop settings).
     */
    public const VatTypesPossibleInvoice = 'meta-vattypes-possible-invoice';
    /**
     * Completor: Union of possible vat types for the invoice lines.
     */
    public const VatTypesPossibleInvoiceLinesUnion = 'meta-vattypes-possible-lines-union';
    /**
     * Completor: Intersection of possible vat types for the invoice lines.
     */
    public const VatTypesPossibleInvoiceLinesIntersection = 'meta-vattypes-possible-lines-intersection';
    /**
     * Completor: Where was the choice for the vat type made?
     */
    public const VatTypeSource = 'meta-vattype-source';

    // Line: Parent - Children related meta tags.
    /**
     * Creator->Completor: the children lines.
     */
    public const ChildrenLines = 'children';
    /**
     * Support: the index of the parent line this child belonged to before
     * flattening.
     */
    public const ParentIndex = 'meta-parent-index';
    /**
     * Support: the number of child lines this parent line had before
     * flattening.
     */
    public const NumberOfChildren = 'meta-children-count';
    /**
     * Support: the number of child lines this parent line had before
     * flattening but which are no longer shown.
     */
    public const ChildrenNotShown = 'meta-children-not-shown';
    /**
     * Support: the number of child lines this parent line had before
     * flattening and which are merged into it.
     */
    public const ChildrenMerged = 'meta-children-merged';
    /**
     * Support: the index of a parent, referred to by the meta info ParentIndex
     * above.
     */
    public const Parent = 'meta-parent';

    // Line: WooCommerce bundle products plugin support.
    public const BundleId = 'meta-bundle-id';
    public const BundleParentId = 'meta-bundle-parent-id';
    public const BundleVisible = 'meta-bundle-visible';
    public const BundleChildrenLineAmount = 'meta-bundle-children-line-amount';
    public const PrecisionBundleChildrenLineAmount = 'meta-bundle-children-line-amount-precision';
    public const BundleChildrenLineAmountInc = 'meta-bundle-children-line-amountinc';
    public const PrecisionBundleChildrenLineAmountInc = 'meta-bundle-children-line-amountinc-precision';

    // Line: Other meta tags.

    public const ProductId = 'meta-product-id';
    /**
     * Creator->Event: the internal product type of the order item line product
     * (Magento only).
     */
    public const ProductType = 'meta-product-type';
    /**
     * Creator->Completor: the type of line: product, shipping, payment fee,
     * discount line, etc. Defined with the Creator::LineType_... constants.
     */
    public const LineType = 'meta-line-type';
    /**
     * Creator->Strategy: boolean that indicates if this line may be split into
     * multiple lines to divide it over multiple vat rates during the strategy
     * phase.
     */
    public const StrategySplit = 'meta-strategy-split';

    // Invoice: Other meta tags.
    /**
     * Support: prefix for an entry that describes the parameters used in 1 try
     * of a strategy.
     */
    public const CompletorStrategy = 'meta-completor-strategy-';
    /**
     * Support: the input to the strategy phase.
     */
    public const CompletorStrategyInput = 'meta-completor-strategy-input';
    /**
     * Support: the name(s) of the strategy(ies) that were successful in
     * completing this line.
     */
    public const CompletorStrategyUsed = 'meta-completor-strategy-used';
    /**
     * Support: the names of the strategies that were not tried because their
     * preconditions failed.
     */
    public const CompletorStrategyPreconditionFailed = 'meta-completor-strategy-precondition-failed';
}
