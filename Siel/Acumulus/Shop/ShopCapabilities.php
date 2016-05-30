<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Invoice\Source;

/**
 * Defines an interface to access the shop specific's capabilities.
 */
abstract class ShopCapabilities implements ShopCapabilitiesInterface
{
    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /**
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
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
    abstract public function getShopOrderStatuses();

    /**
     * {@inheritdoc}
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation. The return array may be empty or only contain
     * ConfigInterface::TriggerInvoiceSendEvent_None, to indicate that no
     * automatic sending is possible (shop does not define any event like
     * feature).
     */
    public function getTriggerInvoiceSendEventOptions()
    {
        return array(
            ConfigInterface::TriggerInvoiceSendEvent_None => $this->t('option_triggerInvoiceSendEvent_0'),
            ConfigInterface::TriggerInvoiceSendEvent_OrderStatus => $this->t('option_triggerInvoiceSendEvent_1'),
            ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate => $this->t('option_triggerInvoiceSendEvent_2'),
        );
    }

    /**
     * {@inheritdoc}
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation that should at least include
     * ConfigInterface::InvoiceNrSource_Acumulus.
     */
    public function getInvoiceNrSourceOptions()
    {
        return array(
            InvoiceConfigInterface::InvoiceNrSource_ShopInvoice => $this->t('option_invoiceNrSource_1'),
            InvoiceConfigInterface::InvoiceNrSource_ShopOrder => $this->t('option_invoiceNrSource_2'),
            InvoiceConfigInterface::InvoiceNrSource_Acumulus => $this->t('option_invoiceNrSource_3'),
        );
    }

    /**
     * {@inheritdoc}
     *
     * Overrides should typically return a subset of the constants defined in
     * this base implementation that should at least include     * ConfigInterface::InvoiceDate_Transfer.
     */
    public function getDateToUseOptions()
    {
        return array(
            InvoiceConfigInterface::InvoiceDate_InvoiceCreate => $this->t('option_dateToUse_1'),
            InvoiceConfigInterface::InvoiceDate_OrderCreate => $this->t('option_dateToUse_2'),
            InvoiceConfigInterface::InvoiceDate_Transfer => $this->t('option_dateToUse_3'),
        );
    }

    /**
     * {@inheritdoc}
     */
    abstract public function getPaymentMethods();
}
