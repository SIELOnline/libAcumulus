<?php
namespace Siel\Acumulus\Joomla\HikaShop\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the HikaShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
    {
        return array(
            'contactYourId' => '[order_user_id]', // order
            'companyName1' => '[address_company]', // billing_address
            // @todo: hoe kan een klant dit (en vat#) invullen?
            'fullName' => '[address_firstname+address_middle_name+address_lastname|name]', // billing_address, customer
            'address1' => '[address_street]', // billing_address
            'address2' => '[address_Street2]', // billing_address
            'postalCode' => '[address_post_code]', // billing_address
            'city' => '[address_city]', // billing_address
            'vatNumber' => '[address_vat]', // billing_address
            'telephone' => '[address_telephone|address_telephone2]', // billing_address
            'fax' => '[address_telephone|address_fax]', // billing_address
            'email' => '[user_email|email]', // customer
        );
    }

    /**
     * {@inheritdoc}
     *
     * HikaShop does not know refunds.
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
        /** @var \hikashopCategoryClass $class */
        $class = hikashop_get('class.category');
        $statuses = $class->loadAllWithTrans('status');

        $orderStatuses = array();
        foreach ($statuses as $state) {
            $orderStatuses[$state->category_name] = $state->translation;
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     *
     * This override removes the 'Use shop invoice number' option as HikaShop
     * does not have invoices.
     */
    public function getDateToUseOptions()
    {
        $result = parent::getDateToUseOptions();
        unset($result[InvoiceConfigInterface::InvoiceDate_InvoiceCreate]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = array();
        /** @var \hikashopPluginsClass $pluginClass */
        $pluginClass = hikashop_get('class.plugins');
        $paymentPlugins = $pluginClass->getMethods('payment');
        foreach ($paymentPlugins as $paymentPlugin) {
            if (!empty($paymentPlugin->enabled) && !empty($paymentPlugin->payment_published)) {
                $result[$paymentPlugin->payment_id] = $paymentPlugin->payment_name;
            }
        }
        return $result;
    }
}
