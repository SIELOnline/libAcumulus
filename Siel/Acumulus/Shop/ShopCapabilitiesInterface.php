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
     * Returns a list with the shop specific tokens.
     *
     * @return string[][]
     *   An array with arrays of tokens keyed by the object name.
     */
    public function getTokenInfo();

    /**
     * Returns an array with shop specific configuration defaults.
     *
     * @return array
     */
    public function getShopDefaults();

    /**
     * Returns a list of invoice source types supported by this shop.
     *
     * The default implementation returns order and credit note. Override if the
     * specific shop supports other types or does not support credit notes.
     *
     * @return string[]
     *   The list of supported invoice source types. The keys are the internal
     *   constants, the values are translated labels.
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
     * Returns an option list of all shop invoice related events.
     *
     * This list represents the shop initiated events that may trigger the
     * sending of the invoice to Acumulus.
     *
     * @return array
     *   An array of all shop invoice related events, with the key being the ID
     *   for the dropdown item (a ConfigInterface::TriggerInvoiceEvent_...
     *   const) and the value being the label for the dropdown item.
     */
    public function getTriggerInvoiceEventOptions();

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

    /**
     * Returns a link to the config form page.
     *
     * If the webshop adds a session token or something like that to
     * administrative links, the returned link will contain so as well.
     *
     * @param string $formType
     *   The form to get the link to.
     *
     * @return string
     *   The link to the requested form page.
     */
    public function getLink($formType);
}
