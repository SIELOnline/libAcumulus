<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Context;
use Module;
use OrderState;
use PaymentModule;
use Siel\Acumulus\Shop\ShopCapabilities as ShopCapabilitiesBase;

/**
 * Defines the PrestaShop webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
  /**
   * {@inheritdoc}
   */
  public function getShopDefaults()
  {
      return array(
          'contactYourId' => '[id]', // Customer
          'companyName1' => '[company]', // InvoiceAddress
          'fullName' => '[first_name] [last_name]', // InvoiceAddress
          'address1' => '[address1]', // InvoiceAddress
          'address2' => '[address2]', // InvoiceAddress
          'postalCode' => '[postcode]', // InvoiceAddress
          'city' => '[city]', // InvoiceAddress
          'vatNumber' => '[vat_number]', // InvoiceAddress
          'telephone' => '[phone|phone_mobile]', // InvoiceAddress
          'email' => '[email]', // Customer
      );
  }

  /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        $states = OrderState::getOrderStates((int) Context::getContext()->language->id);
        $result = array();
        foreach ($states as $state) {
            $result[$state['id_order_state']] = $state['name'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $paymentModules = PaymentModule::getInstalledPaymentModules();
        $result = array();
        foreach($paymentModules as $paymentModule)
        {
            $module = Module::getInstanceById($paymentModule['id_module']);
            $result[$module->name] = $module->displayName;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($formType)
    {
        switch ($formType) {
            case 'config':
                return Context::getContext()->link->getAdminLink('AdminModules', true) . '&module_name=acumulus&tab_module=billing_invoicing&configure=acumulus';
            case 'advanced':
                return Context::getContext()->link->getAdminLink('AdminAcumulusAdvanced', true);
            case 'batch':
                return Context::getContext()->link->getAdminLink('AdminAcumulusBatch', true);
        }
        return parent::getLink($formType);
    }
}
