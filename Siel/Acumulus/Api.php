<?php
namespace Siel\Acumulus;

/**
 * Api defines constants for the values defined by the Acumulus web api.
 */
interface Api
{
    // API result codes. Note that internally I want an increasing order of
    // worseness, so these constants are not used internally but mapped to the
    // Plugin::status_... constants.
    const Success = 0;
    const Warnings = 2;
    const Errors = 1;
    const Exception = 3;

    // Web service API constants.
    const TestMode_Normal = 0;
    const TestMode_Test = 1;

    // Web service related defaults.
    const baseUri = 'https://api.sielsystems.nl/acumulus';
    const testUri = 'https://ng1.sielsystems.nl';
    const apiVersion = 'stable';
    const outputFormat = 'json';

    // Invoice API related constants.
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
}
