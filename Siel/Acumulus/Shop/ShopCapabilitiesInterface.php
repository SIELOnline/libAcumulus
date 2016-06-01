<?php
namespace Siel\Acumulus\Shop;

/**
 * Defines an interface to access the shop specific capabilities, like:
 * - The list of defined order states.
 * - The supported invoice source types (order, refund).
 * - Etc.
 */
interface ShopCapabilitiesInterface
{
    /**
     * Returns a list of invoice source types supported by this shop.
     *
     * The default implementation returns order and credit note. Override if the
     * specific shop supports other types or does not support credit notes.
     *
     * @return string[]
     *   The list of supported invoice source types. The keys are the internal
     *   const, the values are translated labels.
     */
    public function getSupportedInvoiceSourceTypes();

    /**
     * Returns an option list of all shop order statuses.
     *
     * @return array
     *   An array of all shop order statuses, with the key being the ID for
     *   the dropdown item and the value being the label for the dropdown item.
     */
    public function getShopOrderStatuses();

    /**
     * Returns a list of events that can trigger the automatic sending of an
     * invoice.
     *
     * This may differ per shop as not all shops define events for all moments
     * that can be used to trigger the sending of an invoice.
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation. The return array may be empty or only contain
     * ConfigInterface::TriggerInvoiceSendEvent_None, to indicate that no
     * automatic sending is possible (shop does not define any event like
     * model).
     *
     * @return array
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    public function getTriggerInvoiceSendEventOptions();

    /**
     * Returns a list of valid sources that can be used as invoice number.
     *
     * This may differ per shop as not all shops support invoices as a separate
     * entity.
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation, but including at least
     * ConfigInterface::InvoiceNrSource_Acumulus.
     *
     * @return array
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    public function getInvoiceNrSourceOptions();

    /**
     * Returns a list of valid date sources that can be used as invoice date.
     *
     * This may differ per shop as not all shops support invoices as a separate
     * entity.
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation, but including at least
     * ConfigInterface::InvoiceDate_Transfer.
     *
     * @return array
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    public function getDateToUseOptions();

    /**
     * Returns an option list of active payment methods.
     *
     * @return array
     *   An array of active payment methods. with the key being the ID (internal
     *   name) for the dropdown item and the value being the label for the
     *   dropdown item.
     */
    public function getPaymentMethods();
}
