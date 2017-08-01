<?php

namespace Siel\Acumulus;

/**
 * Meta defines string constants for meta tags used in Acumulus API messages.
 */
interface Meta {
    // Vat related meta tags.
    const UnitPriceInc = 'unitpriceinc';
    const UnitPriceOld = 'meta-unitprice-old';
    const VatAmount = 'vatamount';
    const VatRateSource = 'meta-vatrate-source';
    const VatRateMin = 'meta-vatrate-min';
    const VatRateMax = 'meta-vatrate-max';
    const FieldsCalculated = 'meta-fields-calculated';
    const RecalculateUnitPrice = 'meta-unitprice-recalculate';
    const RecalculatedUnitPrice = 'meta-unitprice-recalculated';
    const VatRateLookup = 'meta-vatrate-lookup';
    const VatRateLookupLabel = 'meta-vatrate-lookup-label';
    const VatRateLookupSource = 'meta-vatrate-lookup-source';
    const VatRateMatches = 'meta-vatrate-matches';
    const VatTypesPossible = 'meta-vattypes-possible';

    // Line amounts related meta tags.
    const LineAmount = 'meta-line-amount';
    const LineAmountInc = 'meta-line-amountinc';
    const LineVatAmount = 'meta-line-vatamount';
    const LineDiscountAmountInc = 'meta-line-discount-amountinc';
    const LineDiscountVatAmount = 'meta-line-discount-vatamount';

    // Precision related meta tags.
    const PrecisionUnitPrice = 'meta-unitprice-precision';
    const PrecisionUnitPriceInc = 'meta-unitpriceinc-precision';
    const PrecisionCostPrice = 'meta-costprice-precision';
    const PrecisionVatAmount = 'meta-vatamount-precision';

    // Invoice totals meta tags.
    const InvoiceAmount = 'meta-invoice-amount';
    const InvoiceAmountInc = 'meta-invoice-amountinc';
    const InvoiceVatAmount = 'meta-invoice-vatamount';
    const InvoiceVat = 'meta-invoice-vat';
    const InvoiceCalculated = 'meta-invoice-calculated';

    // Line totals meta tags.
    const LinesAmount = 'meta-lines-amount';
    const LinesAmountInc = 'meta-lines-amountinc';
    const LinesVatAmount = 'meta-lines-vatamount';
    const LinesIncomplete = 'meta-lines-incomplete';

    const MissingAmount = 'meta-missing-amount';

    // Parent - Children related meta tags.
    const ChildrenLines = 'children';
    const ParentIndex = 'meta-parent-index';
    const NumberOfChildren = 'meta-children';
    const ChildrenNotShown = 'meta-children-not-shown';
    const ChildrenMerged = 'meta-children-merged';
    const Parent = 'meta-parent';

    const BundleId = 'meta-bundle-id';
    const BundleParentId = 'meta-bundle-parent-id';
    const BundleVisible = 'meta-bundle-visible';
    const BundleChildrenLineAmount = 'meta-bundle-children-line-amount';
    const PrecisionBundleChildrenLineAmount = 'meta-bundle-children-line-amount-precision';
    const BundleChildrenLineAmountInc = 'meta-bundle-children-line-amountinc';
    const PrecisionBundleChildrenLineAmountInc = 'meta-bundle-children-line-amountinc-precision';

    // Other meta tags.
    const LineType = 'meta-line-type';
    const StrategySplit = 'meta-strategy-split';
    const StrategyCompletor = 'meta-strategy-completor-';
    const StrategyCompletorInput = 'meta-strategy-completor-input';
    const StrategyCompletorUsed = 'meta-completor-strategy-used';
    const StrategyCompletorPreconditionFailed = 'meta-strategy-completor-precondition-failed';
    const paymentMethod = 'meta-payment-method';

}
