<?php
namespace Siel\Acumulus\Invoice;

/**
 * InvoiceConfigInterface defines an interface to store and retrieve invoice
 * specific configuration values.
 *
 * Configuration is stored in the host environment (normally a web shop), this
 * interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface
{
    // Invoice API related constants.
    const PaymentStatus_Due = 1;
    const PaymentStatus_Paid = 2;

    const Concept_No = 0;
    const Concept_Yes = 1;
    // not an API constant.
    const Concept_Plugin = 2;

    const InvoiceNrSource_ShopInvoice = 1;
    const InvoiceNrSource_ShopOrder = 2;
    const InvoiceNrSource_Acumulus = 3;

    const InvoiceDate_InvoiceCreate = 1;
    const InvoiceDate_OrderCreate = 2;
    const InvoiceDate_Transfer = 3;

    const ContactStatus_Disabled = 0;
    const ContactStatus_Active = 1;

    const OverwriteIfExists_No = 0;
    const OverwriteIfExists_Yes = 1;

    const ConfirmReading_No = 0;
    const ConfirmReading_Yes = 1;

    const DigitalServices_Unknown = 0;
    const DigitalServices_Both = 1;
    const DigitalServices_No = 2;
    const DigitalServices_Only = 3;

    const VatFreeProducts_Unknown = 0;
    const VatFreeProducts_Both = 1;
    const VatFreeProducts_No = 2;
    const VatFreeProducts_Only = 3;

    const VatType_National = 1;
    const VatType_NationalReversed = 2;
    const VatType_EuReversed = 3;
    const VatType_RestOfWorld = 4;
    const VatType_MarginScheme = 5;
    const VatType_ForeignVat = 6;

    /**
     * Returns the set of settings related to the customer part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - sendCustomer
     *   - overwriteIfExists
     *   - defaultCustomerType
     *   - contactStatus
     *   - contactYourId
     *   - companyName1
     *   - companyName2
     *   - vatNumber
     *   - fullName
     *   - salutation
     *   - address1
     *   - address2
     *   - postalCode
     *   - city
     *   - telephone
     *   - fax
     *   - email
     *   - mark
     *   - genericCustomerEmail
     */
    public function getCustomerSettings();

    /**
     * Returns the set of settings related to the invoice part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - defaultAccountNumber
     *   - defaultCostCenter
     *   - defaultInvoiceTemplate
     *   - defaultInvoicePaidTemplate
     *   - paymentMethodAccountNumber
     *   - paymentMethodCostCenter
     *   - sendEmptyInvoice
     *   - sendEmptyShipping
     *   - description
     *   - descriptionText
     *   - invoiceNotes
     *   - useMargin
     *   - optionsShow
     *   - optionsAllOn1Line
     *   - optionsAllOnOwnLine
     *   - optionsMaxLength
     */
    public function getInvoiceSettings();

    /**
     * Returns the set of settings related to the shop characteristics that
     * influence the invoice creation and completion
     *
     * @return array
     *   A keyed array with the keys:
     *   - digitalServices
     *   - vatFreeProducts
     *   - invoiceNrSource
     *   - dateToUse
     */
    public function getShopSettings();

    /**
     * Returns the set of settings related to sending an email.
     *
     * @return array
     *   A keyed array with the keys:
     *   - emailAsPdf
     *   - emailBcc
     *   - emailFrom
     *   - subject
     *   - confirmReading
     */
    public function getEmailAsPdfSettings();
}
