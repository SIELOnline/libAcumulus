<?php
namespace Siel\Acumulus\Config;

/**
 * Defines an interface to retrieve shop specific configuration settings.
 *
 * Configuration is stored in the host environment, normally a web shop.
 * This interface abstracts from how a specific web shop does so.
 */
interface ConfigInterface
{
    const libraryVersion = '4.8.0-alpha2';

    // API result codes, internally I want an increasing order of worseness, so
    // these constants are not used internally but mapped to the status_...
    // constants below.
    const Api_Success = 0;
    const Api_Warnings = 2;
    const Api_Errors = 1;
    const Api_Exception = 3;

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

    // Web service API constants.
    const TestMode_Normal = 0;
    const TestMode_Test = 1;

    // Web service related defaults.
    const baseUri = 'https://api.sielsystems.nl/acumulus';
    //const baseUri = 'https://ng1.sielsystems.nl';
    const apiVersion = 'stable';
    const outputFormat = 'json';

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

    // Invoice send handling related constants. These can be combined with a
    // send Status_... const (bits 1 to 3).
    // Not sent: bit 4 always set.
    const Invoice_NotSent = 0x8;
    // Reason for not sending: bits 5 to 7.
    const Invoice_NotSent_EventInvoiceCreated = 0x18;
    const Invoice_NotSent_EventInvoiceCompleted = 0x28;
    const Invoice_NotSent_AlreadySent = 0x38;
    const Invoice_NotSent_WrongStatus = 0x48;
    const Invoice_NotSent_EmptyInvoice = 0x58;
    const Invoice_NotSent_TriggerInvoiceCreateNotEnabled = 0x68;
    const Invoice_NotSent_TriggerInvoiceSentNotEnabled = 0x78;
    const Invoice_NotSent_Mask = 0x78;
    // Reason for sending: bits 8 and 9
    const Invoice_Sent_New = 0x80;
    const Invoice_Sent_Forced = 0x100;
    const Invoice_Sent_TestMode = 0x180;
    const Invoice_Sent_Mask = 0x180;

    // Web shop configuration related constants.
    const TriggerInvoiceEvent_None = 0;
    const TriggerInvoiceEvent_Create = 1;
    const TriggerInvoiceEvent_Send = 2;

    /**
     * Returns the contract credentials to authenticate with the Acumulus API.
     *
     * @return array
     *   A keyed array with the keys:
     *   - contractcode
     *   - username
     *   - password
     *   - emailonerror
     *   - emailonwarning
     */
    public function getCredentials();

    /**
     * Returns information about the environment of this library.
     *
     * @return array
     *   A keyed array with information about the environment of this library:
     *   - baseUri
     *   - apiVersion
     *   - libraryVersion
     *   - moduleVersion
     *   - shopName
     *   - shopVersion
     *   - hostName
     *   - phpVersion
     *   - os
     *   - curlVersion
     *   - jsonVersion
     */
    public function getEnvironment();

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - debug
     *   - logLevel
     *   - outputFormat
     */
    public function getPluginSettings();

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

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - triggerOrderStatus
     *   - triggerInvoiceEvent
     *   - sendEmptyInvoice
     */
    public function getShopEventSettings();

    /**
     * Saves the configuration to the actual configuration provider.
     *
     * @param array $values
     *   A keyed array that contains the values to store, this may be a subset
     *   of the possible keys.
     *
     * @return bool
     *   Success.
     */
    public function save(array $values);

    /**
     * Returns a list of keys that are stored in the shop specific config store.
     *
     * @return array
     */
    public function getKeys();

    /**
     * Returns a list of defaults for the config keys.
     *
     * @return array
     */
    public function getDefaults();

    /**
     * Upgrade the datamodel to the given version.
     *
     * This method is only called when the module gets updated.
     *
     * @param string $currentVersion
     *   The current version of the module.
     *
     * @return bool
     *   Success.
     */
    public function upgrade($currentVersion);
}
