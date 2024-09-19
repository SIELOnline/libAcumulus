<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;
use VirtueMartModelOrders;
use VmModel;

/**
 * Creates a raw version of the Acumulus invoice from a virtueMart {@see Source}.
 *
 * Notes:
 * - Calculation rules used, e.g, to give a certain customer group, a discount
 *   (fixed amount or percentage) should always have "price modifier before tax"
 *   or "price modifier before tax per bill" for the Type of Arithmetic
 *   Operation. Otherwise, the VAT computations won't comply with Dutch
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
    protected VirtueMartModelOrders $orderModel;

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
    protected array $order;

    /**
     * Array with fields from the virtuemart_invoices table:
     * - virtuemart_invoice_id
     * - invoice_number
     * - order_status
     * - xhtml
     * - + others
     *
     * @var array
     */
    protected array $shopInvoice = [];

    /**
     * Precision of amounts stored in VM. In VM, you can enter either the price
     * inc or ex vat. The other amount will be calculated and stored with 4
     * digits precision. So 0.001 is on the pessimistic side.
     *
     * @var float
     */
    protected float $precision = 0.001;

    /**
     * {@inheritdoc}
     *
     * This override also initializes VM specific properties related to the
     * source.
     */
    protected function setInvoiceSource(Source $invoiceSource): void
    {
        parent::setInvoiceSource($invoiceSource);
        $this->order = $this->invoiceSource->getSource();
        $this->orderModel = VmModel::getModel('orders');

        // @todo: do we use the shop invoice?
        /** @var \TableInvoices $invoicesTable */
//        $invoicesTable = $this->orderModel->getTable('invoices');
//        if ($invoice = $invoicesTable->load($this->order['details']['BT']->virtuemart_order_id, 'virtuemart_order_id')) {
//            $this->shopInvoice = $invoice->getProperties();
//        }

// @todo: why did we copy the tax_exemption_number? Is it not always there?
//
//        if (!empty($this->order['details']['BT']->virtuemart_user_id)) {
//            /** @var \VirtueMartModelUser $userModel */
//            $userModel = VmModel::getModel('user');
//            $userModel->setId($this->order['details']['BT']->virtuemart_user_id);
//            $user = $userModel->getUser();
//
//            foreach ($user->userInfo as $userInfo) {
//                if ($userInfo->address_type === 'BT') {
//                    $this->order['details']['BT']->tax_exemption_number = $userInfo->tax_exemption_number;
//                }
//            }
//        }
    }

    protected function setPropertySources(): void
    {
        // As the source array does not contain scalar properties itself, only
        // sub arrays, we remove it as a property source.
        parent::setPropertySources();
        unset($this->propertySources['source']);
        $this->propertySources['BT'] = $this->order['details']['BT'];
        $this->propertySources['ST'] = $this->order['details']['ST'];
//        $this->propertySources['shopInvoice'] = $this->shopInvoice;
    }

    /**
     * {@inheritdoc}
     *
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function getPaymentFeeLine(): array
    {
        $result = [];
        if (!empty($this->order['details']['BT']->order_payment)) {
            $paymentEx = (float) $this->order['details']['BT']->order_payment;
            if (!Number::isZero($paymentEx)) {
                $paymentVat = (float) $this->order['details']['BT']->order_payment_tax;
                $result = [
                        Tag::Product => $this->t('payment_costs'),
                        Tag::UnitPrice => $paymentEx,
                        Tag::Quantity => 1,
                        Meta::VatAmount => $paymentVat,
                    ] + $this->getVatData('payment', $paymentEx, $paymentVat);
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
    protected function getCalcRule(string $calcKind, int $orderItemId = 0): ?object
    {
        foreach ($this->order['calc_rules'] as $calcRule) {
            if ($calcRule->calc_kind === $calcKind) {
                if (empty($orderItemId) || (int) $calcRule->virtuemart_order_item_id === $orderItemId) {
                    return $calcRule;
                }
            }
        }
        return null;
    }

    /**
     * Returns vat data and vat lookup metadata for the current order (item).
     *
     * @param string $calcRuleType
     *   Type of calc rule to search for: 'VatTax', 'shipment' or 'payment'.
     * @param int $orderItemId
     *   The order item to search the calc rule for, or search at the order
     *   level if left empty.
     *
     * @return array
     *   Vat data and vat lookup metadata to add to the Acumulus invoice line.
     */
    protected function getVatData(string $calcRuleType, float $amountEx, float $vatAmount, int $orderItemId = 0): array
    {
        $calcRule = $this->getCalcRule($calcRuleType, $orderItemId);
        if ($calcRule !== null && !empty($calcRule->calc_value)) {
            $vatInfo = [
                Tag::VatRate => (float) $calcRule->calc_value,
                Meta::VatRateSource => Number::isZero($vatAmount) ? VatRateSource::Exact0 : VatRateSource::Exact,
                Meta::VatClassId => $calcRule->virtuemart_calc_id,
                Meta::VatClassName => $calcRule->calc_rule_name,
            ];
        } elseif (Number::isZero($vatAmount)) {
            // No vat class assigned to payment or shipping fee.
            $vatInfo = [
                Tag::VatRate => Api::VatFree,
                Meta::VatRateSource => VatRateSource::Exact0,
                Meta::VatClassId => Config::VatClass_Null,
            ];
        } else {
            /** @noinspection PhpStaticAsDynamicMethodCallInspection */
            $vatInfo = $this->getVatRangeTags($vatAmount, $amountEx, $this->precision);
        }

        return $vatInfo;
    }
}
