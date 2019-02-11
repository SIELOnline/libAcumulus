<?php
namespace Siel\Acumulus\Config;

use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\PluginConfig;

/**
 * Defines an interface to access the shop specific's capabilities.
 */
abstract class ShopCapabilities
{
    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var string */
    protected $shopName;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /**
     * @param string $shopNamespace
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct($shopNamespace, Translator $translator, Log $log)
    {
        $this->log = $log;
        $this->translator = $translator;
        $pos = strrpos($shopNamespace, '\\');
        $this->shopName = $pos !== false ? substr($shopNamespace, $pos + 1) : $shopNamespace;
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t($key)
    {
        return $this->translator->get($key);
    }

    /**
     * Returns an array with shop specific environment settings.
     *
     * @return array
     *   An array with keys:
     *   - moduleVersion: Version of the module (not the library, though it may
     *       simply follow the library).
     *   - shopName: Name of the shop to use in support requests.
     *   - shopVersion: Version of the webshop software. If embedded in a CMS,
     *       it should also contain the name and version of the CMS between
     *       brackets.
     */
    abstract public function getShopEnvironment();

    /**
     * Returns an array with shop specific configuration defaults.
     *
     * Any key defined in {@see ShopCapabilities::getKeyInfo()} that can be
     * given a more logical value given that the library is running in a given
     * webshop software, should be returned here with that more logical default.
     *
     * This base method is abstract, because at least these keys that allow
     * tokens (veldverwijzingen) to get customer, invoice and invoice line fields
     * should be returned.
     * * See {@see \Siel\Acumulus\Invoice\Creator::getCustomer()} to get a list
     *   of fields at the customer level that use tokens.
     * * See {@see \Siel\Acumulus\Invoice\Creator::getInvoice()} to get a list
     *   of fields at the invoice level that use tokens.
     * * At the item line level, the fields itemnumber, product, nature, and
     *   costprice may use tokens.
     *
     * See{@see \Siel\Acumulus\Helpers\Token} or the help text under key
     * 'desc_tokens' in siel/acumulus/src/Shop/ConfigFormTranslations.php for
     * more info about the possible options to define combinations or a
     * selection of various tokens.
     *
     * @return array
     */
    abstract public function getShopDefaults();

    /**
     * Returns a list with the shop specific token info.
     *
     * Many fields of an Acumulus invoice can be filled with user configured and
     * dynamically looked up values of properties or method return values from
     * webshop objects that are made available when creating the invoice. The
     * advanced settings form gives the user an overview of what objects,
     * properties and methods are available. This overview is based on the info
     * that this method returns.
     *
     * This base implementation returns the info that is available in all
     * webshops. Overriding methods should add the webshop specific info, which
     * must at least include the "property source" 'source', being the webshop's
     * order or refund object or array.
     *
     * This method returns an array of token infos keyed by the "property
     * source" name. A token info is an array that can have the following keys:
     * * more-info (string, optional): free text to tell the use where to look
     *   for more info.
     * * class (string|string[], optional): class or array of class names where
     *   the properties come from.
     * * file (string|string[], optional): file or array of file names where
     *   the properties come from.
     * * table (string|string[], optional): database table or array of table names
     *   where the properties come from.
     * * additional-info (string, optional): free text to give the user
     *   additional info.
     * * properties (string[], required): array of property and method names
     *   that can be used as token.
     * * properties-more: bool indicating if not all properties were listed and
     *   a message indicating where to look for more properties should be shown.
     * It is expected that 1 of the keys class, file, or table is defined. If
     * class is defined, file may also be defined.
     *
     * @return array[]
     *   An array of token infos keyed by the "property source" name.
     */
    public function getTokenInfo()
    {
        $result = array(
            'invoiceSource' => array(
                'more-info' => ucfirst($this->t('invoice_source')),
                'class' => '\Siel\Acumulus\Invoice\Source',
                'properties' => array(
                    'type (' . $this->t(Source::Order) . ' ' . $this->t('or') . ' ' . $this->t(Source::CreditNote) . ')',
                    'id (' . $this->t('internal_id') . ')',
                    'reference (' . $this->t('external_id') . ')',
                    'date',
                    'status (' . $this->t('internal_not_label') . ')',
                    'paymentMethod (' . $this->t('internal_not_label') . ')',
                    'paymentStatus (1: ' . $this->t('payment_status_1') . '; 2: ' . $this->t('payment_status_2') . ')',
                    'paymentDate',
                    'countryCode',
                    'currency',
                    'invoiceReference (' . $this->t('external_id') . ')',
                    'invoiceDate',
                ),
                'properties-more' => false,
            ),
            'source' => array_merge(array('more-info' => ucfirst($this->t('order_or_refund'))), $this->getTokenInfoSource()),
        );
        if (array_key_exists(Source::CreditNote, $this->getSupportedInvoiceSourceTypes())) {
            $result += array(
                'refund' => array_merge(array('more-info' => ucfirst($this->t('refund_only'))), $this->getTokenInfoRefund()),
                'order' => array_merge(array('more-info' => ucfirst($this->t('original_order_for_refund'))), $this->getTokenInfoOrder()),
            );
        }
        $result += $this->getTokenInfoShopProperties();

        return $result;
    }

    /**
     * Returns shop specific token info for the 'source' property.
     *
     * @return array
     *   An array with shop specific token info for the 'source' property.
     */
    abstract protected function getTokenInfoSource();

    /**
     * Returns shop specific token info for the 'refund' property.
     *
     * Override if your shop supports refunds.
     *
     * @return array
     *   An array with shop specific token info for the 'refund' property.
     */
    protected function getTokenInfoRefund()
    {
        return array();
    }

    /**
     * Returns shop specific token info for the 'order' property.
     *
     * Override if your shop supports refunds.
     *
     * @return array
     *   An array with shop specific token info for the 'order' property.
     */
    protected function getTokenInfoOrder() {
        return array();
    }

    /**
     * Returns token info for shop specific properties.
     *
     * @return array
     *   An array with token info for shop specific properties.
     */
    abstract protected function getTokenInfoShopProperties();

    /**
     * Returns an option list of all shop order statuses.
     *
     * Note that the IDs are the values that are stored in the config and are
     * later on compared with the order status when a webshop event occurrs
     * that may lead to sending the invoice to Acumulus.
     *
     * @return string[]
     *   An array of all shop order statuses, with the key being the ID for
     *   the dropdown item and the value being the label for the dropdown item.
     */
    abstract public function getShopOrderStatuses();

    /**
     * Returns a list of invoice source types supported by this shop.
     *
     * The default implementation returns order and credit note. Override if the
     * specific shop does not support credit notes (or supports other types).
     *
     * @return string[]
     *   The list of supported invoice source types. The keys are the internal
     *   {@see \Siel\Acumulus\Invoice\Source} constants, the values are
     *   translated labels.
     */
    public function getSupportedInvoiceSourceTypes()
    {
        return array(
            Source::Order => ucfirst($this->t(Source::Order)),
            Source::CreditNote => ucfirst($this->t(Source::CreditNote)),
        );
    }

    /**
     * Returns an option list of all shop invoice related events.
     *
     * This list represents the shop initiated events that may trigger the
     * sending of the invoice to Acumulus.
     *
     * @return string[]
     *   An array of all shop invoice related events, with the key being the ID
     *   for the dropdown item, 1 of the {@see \Siel\Acumulus\PluginConfig}
     *   TriggerInvoiceEvent_... constants, and the value being the label for
     *   the dropdown item.
     */
    public function getTriggerInvoiceEventOptions()
    {
        return array(
            PluginConfig::TriggerInvoiceEvent_None => $this->t('option_triggerInvoiceEvent_0'),
        );
    }

    /**
     * Returns a list of valid sources that can be used as invoice number.
     *
     * This may differ per shop as not all shops support invoices as a separate
     * entity.
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation, but including at least
     * {@see \Siel\Acumulus\PluginConfig::InvoiceNrSource_Acumulus}.
     *
     * @return string[]
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    public function getInvoiceNrSourceOptions()
    {
        return array(
            PluginConfig::InvoiceNrSource_ShopInvoice => $this->t('option_invoiceNrSource_1'),
            PluginConfig::InvoiceNrSource_ShopOrder => $this->t('option_invoiceNrSource_2'),
            PluginConfig::InvoiceNrSource_Acumulus => $this->t('option_invoiceNrSource_3'),
        );
    }

    /**
     * Returns a list of valid date sources that can be used as invoice date.
     *
     * This may differ per shop as not all shops support invoices as a separate
     * entity.
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation, but including at least
     * {@see \Siel\Acumulus\PluginConfig::InvoiceDate_Transfer}.
     *
     * @return string[]
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    public function getDateToUseOptions()
    {
        return array(
            PluginConfig::InvoiceDate_InvoiceCreate => $this->t('option_dateToUse_1'),
            PluginConfig::InvoiceDate_OrderCreate => $this->t('option_dateToUse_2'),
            PluginConfig::InvoiceDate_Transfer => $this->t('option_dateToUse_3'),
        );
    }

    /**
     * Returns an option list of active payment methods.
     *
     * The ids returned are later on used to compare with an order's payment
     * method, so the appropriate template and account can be chosen.
     *
     * @return string[]
     *   An array of active payment methods, with the key being the id (internal
     *   name) for the dropdown item and the value being the label for the
     *   dropdown item.
     */
    abstract public function getPaymentMethods();

    /**
     * Returns an option list of tax classes.
     *
     * @return string[]
     *   An array of tax classes, with the key being the tax class id, to be
     *   used as id for the dropdown item and the value being the tax class
     *   name, to be used as the label for the dropdown item.
     */
    abstract public function getVatClasses();

    /**
     * Returns a link to a form page.
     *
     * If the webshop adds a session token or something like that to
     * administrative links, the returned link should contain so as well.
     *
     * @param string $formType
     *   The form to get the link to.
     *
     * @return string
     *   The link to the requested form page.
     */
    public function getLink($formType)
    {
        $this->log->error('ShopCapabilities::getLink("%s"): not defined for or unknown form type', $formType);
        return '#';
    }
}
