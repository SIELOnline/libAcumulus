<?php
/**
 * @noinspection PhpUnused
 */

namespace Siel\Acumulus;

/**
 * Api defines constants for the values defined by the Acumulus web api.
 */
interface Api
{
    /**
     * Formats to use with date() and DateTime formatting methods when dates or
     * times are expected in the API.
     *
     * @var string
     */
    public const DateFormat_Iso = 'Y-m-d';
    public const Format_TimeStamp = 'Y-m-d H:i:s';

    // API role ids
    public const RoleManager = 1;
    public const RoleUser = 2;
    public const RoleCreator = 3;
    public const RoleApiManager = 4;
    public const RoleApiUser = 5;
    public const RoleApiCreator = 6;

    // API result codes. Note that internally I want an increasing order of
    // worseness, so these constants are not used internally but mapped to the
    // Severity::... constants.
    public const Status_Success = 0;
    public const Status_Warnings = 2;
    public const Status_Errors = 1;
    public const Status_Exception = 3;

    // ApiClient service related defaults.
    public const baseUri = 'https://api.sielsystems.nl/acumulus';
    public const apiVersion = 'stable';
    public const apiVersionTest = 'dev';
    public const outputFormat = 'json';

    // API related constants.
    public const TestMode_Normal = 0;
    public const TestMode_Test = 1;

    public const ContactStatus_Disabled = 0;
    public const ContactStatus_Active = 1;

    public const OverwriteIfExists_No = 0;
    public const OverwriteIfExists_Yes = 1;

    public const CustomerType_Debtor = 1;
    public const CustomerType_Creditor = 2;
    public const CustomerType_Both = 3;

    public const VatTypeId_Private = 1; // or vat exempt business.
    public const VatTypeId_Business = 2;

    public const AutoName_No  = 0;
    public const AutoName_OnlyForeign  = 1;
    public const AutoName_Yes  = 2;

    public const DisableDuplicates_No  = 0;
    public const DisableDuplicates_Yes  = 1;

    public const Region_NotSet = 0;
    public const Region_Netherlands = 1;
    public const Region_EU = 2;
    public const Region_World = 3;

    public const Concept_No = 0;
    public const Concept_Yes = 1;

    public const PaymentStatus_Due = 1;
    public const PaymentStatus_Paid = 2;

    public const VatType_National = 1;
    public const VatType_NationalReversed = 2;
    public const VatType_EuReversed = 3;
    public const VatType_RestOfWorld = 4;
    public const VatType_MarginScheme = 5;
    public const VatType_EuVat = 6;
    public const VatType_OtherForeignVat = 7;

    public const VatFree = -1;

    public const Nature_Product = 'Product';
    public const Nature_Service = 'Service';

    public const Entry_Delete = 1;
    public const Entry_UnDelete = 0;

    public const Email_Normal = 0;
    public const Email_Reminder = 1;

    public const ConfirmReading_No = 0;
    public const ConfirmReading_Yes = 1;

    public const UblInclude_No = 0;
    public const UblInclude_Yes = 1;

    public const Gender_Female = 'F';
    public const Gender_Male = 'M';
    public const Gender_Neutral = 'X';

    public const CreateApiUser_No = 0;
    public const CreateApiUser_Yes = 1;
}
