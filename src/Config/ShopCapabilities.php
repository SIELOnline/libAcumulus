<?php
namespace Siel\Acumulus\Config;

use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\PluginConfig;

/**
 * Defines an interface to access the shop specific's capabilities.
 */
abstract class ShopCapabilities implements ShopCapabilitiesInterface
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
     * @inheritDoc
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
     * {@inheritdoc}
     *
     * This default implementation returns order and credit note. Override if
     * the specific shop supports other types or does not support credit notes.
     */
    public function getSupportedInvoiceSourceTypes()
    {
        return array(
            Source::Order => ucfirst($this->t(Source::Order)),
            Source::CreditNote => ucfirst($this->t(Source::CreditNote)),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTriggerInvoiceEventOptions()
    {
        return array(
            PluginConfig::TriggerInvoiceEvent_None => $this->t('option_triggerInvoiceEvent_0'),
        );
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function getLink($formType)
    {
        $this->log->error('ShopCapabilities::getLink("%s"): not defined for or unknown form type', $formType);
        return '#';
    }
}
