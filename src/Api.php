<?php
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
    const DateFormat_Iso = 'Y-m-d';
    const Format_TimeStamp = 'Y-m-d H:i:s';

    // API role ids
    const RoleManager = 1;
    const RoleUser = 2;
    const RoleCreator = 3;
    const RoleApiManager = 4;
    const RoleApiUser = 5;
    const RoleApiCreator = 6;

    // API result codes. Note that internally I want an increasing order of
    // worseness, so these constants are not used internally but mapped to the
    // Severity:: constants.
    const Status_Success = 0;
    const Status_Warnings = 2;
    const Status_Errors = 1;
    const Status_Exception = 3;

    // Web service related defaults.
    const baseUri = 'https://api.sielsystems.nl/acumulus';
    const testUri = 'https://ng1.sielsystems.nl';
    const apiVersion = 'stable';
    const outputFormat = 'json';

    // API related constants.
    const TestMode_Normal = 0;
    const TestMode_Test = 1;

    const PaymentStatus_Due = 1;
    const PaymentStatus_Paid = 2;

    const Concept_No = 0;
    const Concept_Yes = 1;

    const ContactStatus_Disabled = 0;
    const ContactStatus_Active = 1;

    const OverwriteIfExists_No = 0;
    const OverwriteIfExists_Yes = 1;

    const ConfirmReading_No = 0;
    const ConfirmReading_Yes = 1;

    const VatType_National = 1;
    const VatType_NationalReversed = 2;
    const VatType_EuReversed = 3;
    const VatType_RestOfWorld = 4;
    const VatType_MarginScheme = 5;
    const VatType_ForeignVat = 6;

    const VatFree = -1;

    const Nature_Product = 'Product';
    const Nature_Service = 'Service';

    const Entry_Delete = 1;
    const Entry_UnDelete = 0;

    const Email_Normal = 0;
    const Email_Reminder = 1;

    const Gender_Female = 'F';
    const Gender_Male = 'M';
    const Gender_Neutral = 'X';

    const CreateApiUser_No = 0;
    const CreateApiUser_Yes = 1;
}
