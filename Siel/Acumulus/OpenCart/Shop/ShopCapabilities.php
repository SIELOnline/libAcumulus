<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the OpenCart 1 and 2 webshop specific capabilities.
 */
abstract class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     *
     * This default implementation returns order and credit note. Override if
     * the specific shop supports other types or does not support credit notes.
     */
    public function getSupportedInvoiceSourceTypes()
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        Registry::getInstance()->load->model('localisation/order_status');
        $states = Registry::getInstance()->model_localisation_order_status->getOrderStatuses();
        $result = array();
        foreach ($states as $state) {
            list($optionValue, $optionText) = array_values($state);
            $result[$optionValue] = $optionText;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice number' option as OpenCart
     * does not have invoices.
     *
     * @todo: What does OC actually have? (or: is the above correct)
     */
    public function getInvoiceNrSourceOptions()
    {
        $result = parent::getInvoiceNrSourceOptions();
        unset($result[InvoiceConfigInterface::InvoiceNrSource_ShopInvoice]);
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice number' option as OpenCart
     * does not have invoices.
     *
     * @todo: What does OC actually have? (or: is the above correct)
     */
    public function getDateToUseOptions()
    {
        $result = parent::getDateToUseOptions();
        unset($result[InvoiceConfigInterface::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * Turns the list into a translated list of options for a select.
     *
     * @param array $extensions
     *
     * @return array
     *   an array with the extensions as key and their translated name as value.
     */
    protected function paymentMethodToOptions(array $extensions)
    {
        $results = array();
        foreach ($extensions as $extension) {
            Registry::getInstance()->language->load('payment/' . $extension);
            $results[$extension] = Registry::getInstance()->language->get('heading_title');
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        Registry::getInstance()->load->model('extension/extension');
        $extensions = Registry::getInstance()->model_extension_extension->getInstalled('payment');
        $extensions = array_filter($extensions, function($extension) {
            return (bool) Registry::getInstance()->config->get($extension . '_status');
        });
        return $this->paymentMethodToOptions($extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($formType)
    {
        $registry = Registry::getInstance();
        switch ($formType) {
            case 'config':
                return $registry->url->link('module/acumulus', 'token=' . $registry->session->data['token'], true);
            case 'advanced':
                return $registry->url->link('module/acumulus/advanced', 'token=' . $registry->session->data['token'], true);
            case 'batch':
                return $registry->url->link('module/acumulus/batch', 'token=' . $registry->session->data['token'], true);
        }
        return parent::getLink($formType);
    }
}
