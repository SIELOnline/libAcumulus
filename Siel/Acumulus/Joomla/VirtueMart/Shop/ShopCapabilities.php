<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Shop;

use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Shop\ShopCapabilities as ShopCapabilitiesBase;
use VirtueMartModelOrderstatus;
use VmModel;

/**
 * Defines the VirtueMart webshop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    public function getShopDefaults()
    {
        return array(
            'contactYourId' => '[virtuemart_user_id]', // Order BT
            'companyName1' => '[company]', // Order BT
            'fullName' => '[first_name+middle_name+last_name]', // Order BT
            'address1' => '[address_1]', // Order BT
            'address2' => '[address_2]', // Order BT
            'postalCode' => '[zip]', // Order BT
            'city' => '[city]', // Order BT
            'vatNumber' => '[tax_exemption_number]', // Order BT
            'telephone' => '[phone_1|phone_2]', // Order BT
            'fax' => '[fax]', // Order BT
            'email' => '[email]', // Order BT
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
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses()
    {
        /** @var VirtueMartModelOrderstatus $orderStatusModel */
        $orderStatusModel = VmModel::getModel('orderstatus');
        /** @var array[] $orderStates Method getOrderStatusNames() has an incorrect @return type ... */
        $orderStates = $orderStatusModel->getOrderStatusNames();
        foreach ($orderStates as $code => &$value) {
            $value = \JText::_($value['order_status_name']);
        }
        return $orderStates;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods()
    {
        $result = array();
        /** @var \VirtueMartModelPaymentmethod $model */
        $model = VmModel::getModel('paymentmethod');
        $paymentMethods = $model->getPayments(true);
        foreach ($paymentMethods as $paymentMethod) {
            $result[$paymentMethod->virtuemart_paymentmethod_id] = $paymentMethod->payment_name;
        }
        return $result;
    }
}
