<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Config;

use Joomla\CMS\Uri\Uri;
use JText;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Joomla\Config\ShopCapabilities as ShopCapabilitiesBase;
use VirtueMartModelCalc;
use VirtueMartModelOrderstatus;
use VmModel;

/**
 * Defines the VirtueMart web shop specific capabilities.
 */
class ShopCapabilities extends ShopCapabilitiesBase
{
    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoSource(): array
    {
        return [
            'more-info' => $this->t('see_properties_below'),
            'properties' => [],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenInfoShopProperties(): array
    {
        return [
            'BT' => [
                'table' => ['virtuemart_orders', 'virtuemart_order_userinfos'],
                'properties' => [
                    // Table virtuemart_orders.
                    'virtuemart_order_id',
                    'virtuemart_vendor_id',
                    'order_number',
                    'customer_number',
                    'order_total',
                    'order_salesPrice',
                    'order_billTaxAmount',
                    'order_billTax',
                    'order_billDiscountAmount',
                    'order_discountAmount',
                    'order_subtotal',
                    'order_tax',
                    'order_shipment',
                    'order_shipment_tax',
                    'order_payment',
                    'order_payment_tax',
                    'coupon_discount',
                    'coupon_code',
                    'order_discount',
                    'order_currency',
                    'order_status',
                    // 1 field from table virtuemart_orderstates.
                    'order_status_name',
                    'user_currency_id',
                    'user_currency_rate',
                    'payment_currency_id',
                    'payment_currency_rate',
                    'virtuemart_paymentmethod_id',
                    'virtuemart_shipmentmethod_id',
                    'delivery_date',
                    'order_language',
                    'ip_address',
                    // Table virtuemart_order_userinfos.
                    'virtuemart_order_userinfo_id',
                    'virtuemart_order_id',
                    'virtuemart_user_id',
                    'address_type',
                    'address_type_name',
                    'company',
                    'title',
                    'last_name',
                    'first_name',
                    'middle_name',
                    'phone_1',
                    'phone_2',
                    'fax',
                    'address_1',
                    'address_2',
                    'city',
                    'virtuemart_state_id',
                    'virtuemart_country_id',
                    'zip',
                    'email',
                    'agreed',
                    'tos',
                    'customer_note',
                    // 1 constructed field.
                    'order_name',
                    'phone_1',
                    'phone_2',
                    'fax',
                    'address_1',
                    'address_2',
                    'city',
                    'virtuemart_state_id',
                    'virtuemart_country_id',
                    'zip',
                    'agreed',
                    'tos',
                    'customer_note',
                    // 1 added field from virtuemart_userinfos.
                    'tax_exemption_number',
                ],
                'properties-more' => true,
            ],
            'ST' => [
                'more-info' => $this->t('see_bt'),
                'properties' => [
                    $this->t('see_above'),
                ],
                'properties-more' => false,
            ],
            'shopInvoice' => [
                'table' => 'virtuemart_invoices',
                'properties' => [
                    'virtuemart_invoice_id',
                    'invoice_number',
                    'order_status',
                    'xhtml',
                ],
                'properties-more' => true,
            ],
            'item' => [
                'table' => 'virtuemart_order_items',
                'additional-info' => $this->t('invoice_lines_only'),
                'properties' => [
                    'virtuemart_order_item_id',
                    'virtuemart_vendor_id',
                    'virtuemart_product_id',
                    'order_item_sku',
                    'order_item_name',
                    'product_quantity',
                    'product_item_price',
                    'product_priceWithoutTax',
                    'product_tax',
                    'product_basePriceWithTax',
                    'product_discountedPriceWithoutTax',
                    'product_final_price',
                    'product_subtotal_discount',
                    'product_subtotal_with_tax',
                    'order_item_currency',
                    'order_status',
                    'product_attribute',
                    'delivery_date',
                ],
                'properties-more' => true,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultShopConfig(): array
    {
        return [
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

            // Invoice lines defaults.
            'itemNumber' => '[order_item_sku]',
            'productName' => '[order_item_name]',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This default implementation returns order and credit note. Override if
     * the specific shop supports other types or does not support credit notes.
     */
    public function getSupportedInvoiceSourceTypes(): array
    {
        $result = parent::getSupportedInvoiceSourceTypes();
        unset($result[Source::CreditNote]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getShopOrderStatuses(): array
    {
        /** @var VirtueMartModelOrderstatus $orderStatusModel */
        $orderStatusModel = VmModel::getModel('orderstatus');
        /** @var array[] $orderStatuses Method getOrderStatusNames() has an incorrect @return type ... */
        $orderStatuses = $orderStatusModel::getOrderStatusNames();
        foreach ($orderStatuses as &$value) {
            $value = JText::_($value['order_status_name']);
        }
        return $orderStatuses;
    }

    /**
     * {@inheritdoc}
     *
     * VirtueMart does not group collections of tax rates into a tax class, but
     * can assign a different tax rate (calc rules) to a product - user group
     * combination. I guess that's how to implement EU vat goods in
     * VirtueMart. This means that users should define different tax calc rules
     * for each country - rate combination, even if the rates are the same,
     * otherwise this plugin might still not be able to distinguish between
     * Dutch and Belgium 21% vat.
     */
    public function getVatClasses(): array
    {
        $result = [];
        /** @var \TableCalcs[] $taxes */
        $taxes = VirtueMartModelCalc::getTaxes();
        foreach ($taxes as $tax) {
            $result[$tax->virtuemart_calc_id] = $tax->calc_name;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethods(): array
    {
        $result = [];
        /** @var \VirtueMartModelPaymentmethod $model */
        $model = VmModel::getModel('paymentmethod');
        $paymentMethods = $model->getPayments(true);
        foreach ($paymentMethods as $paymentMethod) {
            $result[$paymentMethod->virtuemart_paymentmethod_id] = $paymentMethod->payment_name;
        }
        return $result;
    }

    public function getLink(string $linkType): string
    {
        switch ($linkType) {
            case 'pro-support-image':
                return Uri::root(true) . '/administrator/components/com_acumulus/media/pro-support-virtuemart.png';
            case 'pro-support-link':
                return 'https://pay.siel.nl/?p=t7jYwPSWYgFJdWQuWVJmC0R6d6LWHKmNVsNUlgtv82TIhgNS';
        }
        return parent::getLink($linkType);
    }
}
