<?php
namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use DOMDocument;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use stdClass;
use VirtueMartModelCustomfields;
use VmModel;

/**
 * Allows to create arrays in the Acumulus invoice structure from a VirtueMart
 * order
 *
 * Notes:
 * - Calculation rules used, e.g, to give a certain customer group, a discount
 *   (fixed amount or percentage) should always have "price modifier before tax"
 *   or "price modifier before tax per bill" for the Type of Arithmetic
 *   Operation. Otherwise the VAT computations won't comply with Dutch
 *   regulations.
 * - "price modifier before tax per bill" will show normal product prices with a
 *   separate discount line indicating the name of the discount rule.
 * - "price modifier before tax per bill" will show discounted product prices
 *   without a separate discount line and thus also no mention of the applied
 *   discount. In general this option will be a bit more accurate, but IMO that
 *   does not weigh up against the loss of information on the invoice.
 * - The VMInvoice extension offers credit notes, but for now we do not support
 *   this.
 */
class Creator extends BaseCreator
{
    /** @var \VirtueMartModelOrders */
    protected $orderModel;

    /**
     * Array with keys:
     * [details]
     *   [BT]: stdClass (BillTo details)
     *   [ST]: stdClass (ShipTo details) (if available, copy of BT otherwise)
     * [history]
     *   [0]: stdClass (virtuemart_order_histories table record)
     *   ...
     * [items]
     *   [0]: stdClass (virtuemart_order_items table record)
     *   ...
     * [calc_rules]
     *   [0]: stdClass (virtuemart_order_calc_rules table record)
     *   ...
     *
     * @var array
     */
    protected $order;

    /**
     * Array with fields from the virtuemart_invoices table:
     * - virtuemart_invoice_id
     * - invoice_number
     * - order_status
     * - xhtml
     * - + others
     *
     * @var array */
    protected $shopInvoice = array();

    /**
     * Precision of amounts stored in VM. In VM you can enter either the price
     * inc or ex vat. The other amount will be calculated and stored with 4
     * digits precision. So 0.0001 is on the pessimistic side.
     *
     * @var float
     */
    protected $precision = 0.001;

    /**
     * {@inheritdoc}
     *
     * This override also initializes VM specific properties related to the
     * source.
     */
    protected function setInvoiceSource($source)
    {
        parent::setInvoiceSource($source);
        $this->order = $this->invoiceSource->getSource();
        /** @var \VirtueMartModelOrders orderModel */
        $this->orderModel = VmModel::getModel('orders');
        /** @var \TableInvoices $invoicesTable */
        $invoicesTable = $this->orderModel->getTable('invoices');
        if ($invoice = $invoicesTable->load($this->order['details']['BT']->virtuemart_order_id, 'virtuemart_order_id')) {
            $this->shopInvoice = $invoice->getProperties();
        }

        if (!empty($this->order['details']['BT']->virtuemart_user_id)) {
            /** @var \VirtueMartModelUser $userModel */
            $userModel = VmModel::getModel('user');
            $userModel->setId($this->order['details']['BT']->virtuemart_user_id);
            $user = $userModel->getUser();

            foreach ($user->userInfo as $userInfo) {
                if ($userInfo->address_type === 'BT') {
                    $this->order['details']['BT']->tax_exemption_number = $userInfo->tax_exemption_number;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        // As the source array does not contain scalar properties itself, only
        // sub arrays, we remove it as a property source.
        parent::setPropertySources();
        unset($this->propertySources['source']);
        $this->propertySources['BT'] = $this->order['details']['BT'];
        $this->propertySources['ST'] = $this->order['details']['ST'];
        $this->propertySources['shopInvoice'] = $this->shopInvoice;
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array_map(array($this, 'getItemLine'), $this->order['items']);
        return $result;
    }

    /**
     * Returns 1 item line for 1 product line.
     *
     * @param stdClass $item
     *
     * @return array
     */
    protected function getItemLine(stdClass $item)
    {
        $result = array();
        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);

        $productPriceEx = (float) $item->product_discountedPriceWithoutTax;
        $productPriceInc = (float) $item->product_final_price;
        $productVat = (float) $item->product_tax;

        $calcRule = $this->getCalcRule('VatTax', $item->virtuemart_order_item_id);
        if (!empty($calcRule->calc_value)) {
            $vatInfo = array(
                Tag::VatRate => (float) $calcRule->calc_value,
                Meta::VatRateSource => static::VatRateSource_Exact,
                Meta::VatClassId => $calcRule->virtuemart_calc_id,
                Meta::VatClassName => $calcRule->calc_rule_name,
            );
        } else {
            $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx, $this->precision, $this->precision);
        }

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as unitprice.
            // - But still send the VAT rate to Acumulus.
            $result[Tag::UnitPrice] = $productPriceInc;
        } else {
            $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::VatAmount => $productVat,
            );
        }
        $result[Tag::Quantity] = $item->product_quantity;
        $result += $vatInfo;

        // Add variant info.
        $children = $this->getVariantLines($item, $result[Tag::Quantity], $vatInfo);
        if (!empty($children)) {
            $result[Meta::ChildrenLines] = $children;
        }

        $this->removePropertySource('item');

        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * @param stdClass $item
     * @param int $parentQuantity
     * @param array $vatRangeTags
     *
     * @return array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines(stdClass $item, $parentQuantity, $vatRangeTags)
    {
        $result = array();

        // It is not possible (other then by copying a lot of awful code) to get
        // a list of separate attribute and value values. So we stick with
        // calling some code that prints the attributes on an order and
        // "disassemble" that code...
        if (!class_exists('VirtueMartModelCustomfields')) {
            /** @noinspection PhpIncludeInspection */
            require(VMPATH_ADMIN . '/models/customfields.php');
        }
        $product_attribute = VirtueMartModelCustomfields::CustomsFieldOrderDisplay($item, 'FE');
        if (!empty($product_attribute)) {
            $document = new DOMDocument();
            $document->loadHTML($product_attribute);
            $spans = $document->getElementsByTagName('span');
            /** @var \DOMElement $span */
            foreach ($spans as $span) {
                // There tends to be a span around the list of spans containing
                // the actual text, ignore it and only process the lowest level
                // spans.
                if ($span->getElementsByTagName('span')->length === 0) {
                    $text[] = $span->textContent;
                    $result[] = array(
                        Tag::Product => $span->textContent,
                        Tag::UnitPrice => 0,
                        Tag::Quantity => $parentQuantity,
                      ) + $vatRangeTags;
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        $result = array();
        // We are checking on empty, assuming that a null value will be used to
        // indicate no shipping at all (downloadable product) and that free
        // shipping will be represented as the string '0.00' which is not
        // considered empty.
        if (!empty($this->order['details']['BT']->order_shipment)) {
            $shippingEx = (float) $this->order['details']['BT']->order_shipment;
            $shippingVat = (float) $this->order['details']['BT']->order_shipment_tax;

            $calcRule = $this->getCalcRule('shipment');
            if (!empty($calcRule->calc_value)) {
                $vatInfo = array(
                    Tag::VatRate => (float) $calcRule->calc_value,
                    Meta::VatRateSource => static::VatRateSource_Exact,
                    Meta::VatClassId => $calcRule->virtuemart_calc_id,
                    Meta::VatClassName => $calcRule->calc_rule_name,
                );
            } else {
                $vatInfo = $this->getVatRangeTags($shippingVat, $shippingEx, $this->precision, 0.01);
            }

            $result = array(
                    Tag::Product => $this->getShippingMethodName(),
                    Tag::UnitPrice => $shippingEx,
                    Tag::Quantity => 1,
                    Meta::VatAmount => $shippingVat,
                ) + $vatInfo;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingMethodName()
    {
        /** @var \VirtueMartModelShipmentmethod $shipmentMethodsModel */
        $shipmentMethodsModel = VmModel::getModel('shipmentmethod');
        $shipmentMethod = $shipmentMethodsModel->getShipment($this->order['details']['BT']->virtuemart_shipmentmethod_id);
        if (!empty($shipmentMethod) && isset($shipmentMethod->shipment_name)) {
            return $shipmentMethod->shipment_name;
        }
        return parent::getShippingMethodName();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDiscountLines()
    {
        $result = array();

        // We do have several discount related fields in the order details:
        // - order_billDiscountAmount
        // - order_discountAmount
        // - coupon_discount
        // - order_discount
        // However, these fields seem to be totals based on applied non-tax
        // calculation rules. So it is better to add a line per calc rule with a
        // negative amount: this gives us descriptions of the discounts as well.
        $result = array_merge($result, array_map(array($this, 'getCalcRuleDiscountLine'),
            array_filter($this->order['calc_rules'], array($this, 'isDiscountCalcRule'))));

        // Coupon codes are not stored in a calc rules, so handle them separately.
        if (!Number::isZero($this->order['details']['BT']->coupon_discount)) {
            $result[] = $this->getCouponCodeDiscountLine();
        }

        return $result;
    }

    /**
     * Returns whether the calculation rule is a discount rule.
     *
     * @param \stdClass $calcRule
     *
     * @return bool
     *   True if the calculation rule is a discount rule.
     */
    protected function isDiscountCalcRule(stdClass $calcRule)
    {
        return $calcRule->calc_amount < 0.0
        && !in_array($calcRule->calc_kind, array('VatTax', 'shipment', 'payment'));
    }

    /*
     * Returns a discount item line for the discount calculation rule.
     *
     * The returned line will only contain a discount amount including tax.
     * The completor will have to divide this amount over vat rates that are used
     * in this invoice.
     *
     * @param \stdClass $calcRule
     *
     * @return array
     *   An item line for the invoice.
     */
    protected function getCalcRuleDiscountLine(stdClass $calcRule)
    {
        $result = array(
            Tag::Product => $calcRule->calc_rule_name,
            Tag::Quantity => 1,
            Tag::UnitPrice => null,
            Meta::UnitPriceInc => (float) $calcRule->calc_amount,
            Tag::VatRate => null,
            Meta::VatRateSource => static::VatRateSource_Strategy,
            Meta::StrategySplit => true,
        );

        return $result;
    }

    /**
     *  Returns an item line for the coupon code discount on this order.
     *
     * @return array
     *   An item line array.
     */
    protected function getCouponCodeDiscountLine()
    {
        $result = array(
            Tag::ItemNumber => $this->order['details']['BT']->coupon_code,
            Tag::Product => $this->t('discount'),
            Tag::Quantity => 1,
            Meta::UnitPriceInc => (float) $this->order['details']['BT']->coupon_discount,
            Tag::VatRate => null,
            Meta::VatRateSource => static::VatRateSource_Strategy,
            Meta::StrategySplit => true,
        );

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentFeeLine()
    {
        $result = array();
        if (!empty($this->order['details']['BT']->order_payment)) {
            $paymentEx = (float) $this->order['details']['BT']->order_payment;
            if (!Number::isZero($paymentEx)) {
                $paymentVat = (float) $this->order['details']['BT']->order_payment_tax;

                $calcRule = $this->getCalcRule('payment');
                if (!empty($calcRule->calc_value)) {
                    $vatInfo = array(
                        Tag::VatRate => (float) $calcRule->calc_value,
                        Meta::VatRateSource => static::VatRateSource_Exact,
                        Meta::VatClassId => $calcRule->virtuemart_calc_id,
                        Meta::VatClassName => $calcRule->calc_rule_name,
                    );
                } else {
                    $vatInfo = $this->getVatRangeTags($paymentVat, $paymentEx, $this->precision, 0.01);
                }

                $result = array(
                        Tag::Product => $this->t('payment_costs'),
                        Tag::UnitPrice => $paymentEx,
                        Tag::Quantity => 1,
                        Meta::VatAmount => $paymentVat,
                    ) + $vatInfo;
            }
        }
        return $result;
    }

    /**
     * Returns a calculation rule identified by the given reference
     *
     * @param string $calcKind
     *   The value for the kind of calc rule.
     * @param int $orderItemId
     *   The value for the order item id, or 0 for special lines.
     *
     * @return null|object
     *   The (1st) calculation rule for the given reference, or null if none
     *   found.
     */
    protected function getCalcRule($calcKind, $orderItemId = 0)
    {
        foreach ($this->order['calc_rules'] as $calcRule) {
            if ($calcRule->calc_kind == $calcKind) {
                if (empty($orderItemId) || $calcRule->virtuemart_order_item_id == $orderItemId) {
                    return $calcRule;
                }
            }
        }
        return null;
    }
}
