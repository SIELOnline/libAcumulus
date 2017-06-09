<?php
namespace Siel\Acumulus;

/**
 * Plugin defines the version and other plugin related constants.
 *
 * These constants are used as configuration settings or as result codes.
 */
interface Plugin
{
    const Version = '4.8.0-alpha3';

    // Web service configuration related constants.
    // Send status: bits 1, 2 and 3. Can be combined with an Invoice_Sent_...
    // const. Not necessarily a single bit per value, but the order should be by
    // increasing worseness.
    const Status_Success = 0;
    const Status_Warnings = 1;
    const Status_Errors = 2;
    const Status_Exception = 4;
    const Status_Mask = 7;

    const Debug_None = 1;
    const Debug_SendAndLog = 2;
    const Debug_TestMode = 3;

    // not an API constant.
    const Concept_Plugin = 2;

    const InvoiceNrSource_ShopInvoice = 1;
    const InvoiceNrSource_ShopOrder = 2;
    const InvoiceNrSource_Acumulus = 3;

    const InvoiceDate_InvoiceCreate = 1;
    const InvoiceDate_OrderCreate = 2;
    const InvoiceDate_Transfer = 3;

    const DigitalServices_Unknown = 0;
    const DigitalServices_Both = 1;
    const DigitalServices_No = 2;
    const DigitalServices_Only = 3;

    const VatFreeProducts_Unknown = 0;
    const VatFreeProducts_Both = 1;
    const VatFreeProducts_No = 2;
    const VatFreeProducts_Only = 3;

    const TriggerInvoiceEvent_None = 0;
    const TriggerInvoiceEvent_Create = 1;
    const TriggerInvoiceEvent_Send = 2;
}
