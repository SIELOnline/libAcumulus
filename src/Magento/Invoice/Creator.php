<?php
namespace Siel\Acumulus\Magento\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Allows to create arrays in the Acumulus invoice structure from a Magento
 * order or credit memo.
 */
abstract class Creator extends BaseCreator
{
    /** @var \Magento\Sales\Model\Order */
    protected $order;

    /** @var \Magento\Sales\Model\Order\Creditmemo */
    protected $creditNote;

    /** @var \Magento\Sales\Model\ResourceModel\Order\Invoice\Collection */
    protected $shopInvoices;

    /** @var \Magento\Sales\Model\Order\Invoice */
    protected $shopInvoice;

    /**
     * {@inheritdoc}
     *
     * This override also initializes Magento specific properties related to the
     * source.
     */
    protected function setInvoiceSource($invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                $this->creditNote = null;
                break;
            case Source::CreditNote:
                $this->creditNote = $this->invoiceSource->getSource();
                $this->order = $this->creditNote->getOrder();
                break;
        }
        $this->shopInvoices = $this->order->getInvoiceCollection();
        $this->shopInvoice = count($this->shopInvoices) > 0 ? $this->shopInvoices->getFirstItem() : null;
    }

    /**
     * Returns the item lines for an order.
     */
    protected function getItemLinesOrder()
    {
        $result = [];
        // Items may be composed, so start with all "visible" items.
        foreach ($this->order->getAllVisibleItems() as $item) {
            $item = $this->getItemLineOrder($item);
            if ($item !== null) {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * Returns an item line for 1 main product line.
     *
     * @param $item
     * @param bool $isChild
     *
     * @return array
     */
    abstract protected function getItemLineOrder($item, $isChild = false);

    /**
     * {@inheritdoc}
     */
    protected function getShippingMethodName()
    {
        $name = $this->order->getShippingDescription();
        if (!empty($name)) {
            return $name;
        }
        return parent::getShippingMethodName();
    }

    /**
     * {@inheritdoc}
     */
    protected function getDiscountLines()
    {
        $result = [];

        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $source */
        $source = $this->invoiceSource->getSource();
        if (!Number::isZero($source->getBaseDiscountAmount())) {
            $line = [
                Tag::ItemNumber => '',
                Tag::Product => $this->getDiscountDescription(),
                Tag::VatRate => null,
                Meta::VatRateSource => static::VatRateSource_Strategy,
                Meta::StrategySplit => true,
                Tag::Quantity => 1,
            ];
            // Product prices incl. VAT => discount amount is also incl. VAT
            if ($this->productPricesIncludeTax()) {
                $line[Meta::UnitPriceInc] = $this->invoiceSource->getSign() * $source->getBaseDiscountAmount();
            } else {
                $line[Tag::UnitPrice] = $this->invoiceSource->getSign() * $source->getBaseDiscountAmount();
            }
            $result[] = $line;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This implementation may return a manual line for a credit memo.
     */
    protected function getManualLines()
    {
        $result = [];

        if (isset($this->creditNote) && !Number::isZero($this->creditNote->getBaseAdjustment())) {
            $line = [
                Tag::Product => $this->t('refund_adjustment'),
                Tag::UnitPrice => -$this->creditNote->getBaseAdjustment(),
                Tag::Quantity => 1,
                Tag::VatRate => 0,
            ];
            $result[] = $line;
        }
        return $result;
    }

    /**
     * @return string
     */
    protected function getDiscountDescription()
    {
        if ($this->order->getDiscountDescription()) {
            $description = $this->t('discount_code') . ' ' . $this->order->getDiscountDescription();
        } elseif ($this->order->getCouponCode()) {
            $description = $this->t('discount_code') . ' ' . $this->order->getCouponCode();
        } else {
            $description = $this->t('discount');
        }
        return $description;
    }

    /**
     * Returns if the prices for the products are entered with or without tax.
     *
     * @return bool
     *   Whether the prices for the products are entered with or without tax.
     *
     * @nth: can we generalize this?
     */
    abstract protected function productPricesIncludeTax();
}
