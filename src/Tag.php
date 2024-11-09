<?php
/**
 * Not all constants may have actual usages, in that case they are here for
 * completeness and future use/auto-completion.
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace Siel\Acumulus;

/**
 * Tag defines string constants for the tags used in the Acumulus API messages.
 *
 * Mainly the tags used in the invoice-add and signup api call are added here.
 *
 * @error: update Tag::... to Fld::...
 *   - Legacy code: as array keys
 *   - Metadata (Meta::RecalculatePrice), Meta::FieldsCalculated
 *   - Tests (including testdata)
 *   - ...  other uses
 *   - Currently 114 uses of static member access
 */
interface Tag
{
    public const ContractCode = 'contractcode';
    public const UserName = 'username';
    public const EmailOnError = 'emailonerror';
    public const VatRate = 'vatrate';
    public const VatType = 'vattype';
    public const CountryRegion = 'countryregion';
}
