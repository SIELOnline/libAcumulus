<?php
namespace Siel\Acumulus;

/**
 * Plugin defines the version and other plugin related constants.
 *
 * These constants are used as configuration settings.
 */
interface PluginConfig
{
    const Version = '5.0.0';

    const Send_SendAndMailOnError = 1;
    const Send_SendAndMail = 2;
    const Send_TestMode = 3;

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
