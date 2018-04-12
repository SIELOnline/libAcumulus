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
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param string $shopNamespace
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Translator $translator, $shopNamespace, Log $log)
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
     *   - moduleVersion
     *   - shopName
     *   - shopVersion
     */
    abstract public function getShopEnvironment();

    /**
     * Returns an array with shop specific configuration defaults.
     *
     * @return array
     */
    abstract public function getShopDefaults();

    /**
     * Returns a list with the shop specific tokens.
     *
     * @return string[][]
     *   An array with arrays of tokens keyed by the object name.
     */
    public function getTokenInfo()
    {
        return array(
            'invoiceSource' => array(
                'class' => '\Siel\Acumulus\Invoice\Source',
                'properties' => array(
                    'type (' . $this->t(Source::Order) . ' ' . $this->t('or') . ' ' . $this->t(Source::CreditNote) . ')',
                    'id (' . $this->t('internal_id') . ')',
                    'reference (' . $this->t('external_id') . ')',
                    'getInvoiceReference (' . $this->t('external_id') . ')',
                    'getInvoiceDate',
                    'status (' . $this->t('internal_not_label') . ')',
                ),
                'properties-more' => false,
            ),
            'originalInvoiceSource' => array(
                'more-info' => ucfirst($this->t('refund_only')) . '!',
                'properties' => array(
                    $this->t('see_above'),
                ),
                'properties-more' => false,
            ),
        );
    }

    /**
     * Returns an option list of all shop order statuses.
     *
     * @return array
     *   An array of all shop order statuses, with the key being the ID for
     *   the dropdown item and the value being the label for the dropdown item.
     */
    abstract public function getShopOrderStatuses();

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
     * @return array
     *   An array of all shop invoice related events, with the key being the ID
     *   for the dropdown item (a Plugin::TriggerInvoiceEvent_...
     *   const) and the value being the label for the dropdown item.
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
     * Plugin::InvoiceNrSource_Acumulus.
     *
     * @return array
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
     * Plugin::InvoiceDate_Transfer.
     *
     * @return array
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
     * @return array
     *   An array of active payment methods. with the key being the ID (internal
     *   name) for the dropdown item and the value being the label for the
     *   dropdown item.
     */
    abstract public function getPaymentMethods();

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
    public function getLink($formType)
    {
        $this->log->error('ShopCapabilities::getLink("%s"): not defined for or unknown form type', $formType);
        return '#';
    }
}
