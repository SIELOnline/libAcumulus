<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * VatRateSource defines the possible ways of how the vat rate has been
 * obtained.
 *
 * PHP8.1: enumeration.
 */
interface VatRateSource
{
    public const Exact = 'exact';
    public const Exact0 = 'exact-0';
    public const Calculated = 'calculated';
    public const Completor = 'completor';
    public const Strategy = 'strategy';
    public const Parent = 'parent';
    public const Child = 'child';
    public const Creator_Lookup = 'creator-lookup';
    public const Creator_Missing_Amount = 'creator-missing-amount';
    public const Completor_Range = 'completor-range';
    public const Completor_Lookup = 'completor-lookup';
    public const Completor_Range_Lookup = 'completor-range-lookup';
    public const Completor_Range_Lookup_Foreign = 'completor-range-lookup-foreign';
    public const Completor_Max_Appearing = 'completor-max-appearing';
    public const Strategy_Completed = 'strategy-completed';
    public const Copied_From_Children = 'copied-from-children';
    public const Copied_From_Parent = 'copied-from-parent';
    public const Corrected_NoVat = 'corrected-no-vat';
}
