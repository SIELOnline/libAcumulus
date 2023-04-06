<?php
/**
 * @noinspection PhpClassConstantAccessedViaChildClassInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Invoice;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Meta;

use Throwable;

use function count;
use function in_array;
use function strlen;

/**
 * Wraps a Magento order or credit memo in an invoice source object.
 *
 * @method Order|Creditmemo getSource()
 */
class Source extends BaseSource
{
    protected function setId(): void
    {
        $this->id = (int) $this->getSource()->getId();
    }

    /**
     * Loads an Order source for the set id.
     *
     * @noinspection PhpUnused  Called via setShopSource().
     */
    protected function setShopSourceOrder(): void
    {
        $this->shopSource = Registry::getInstance()->create(Order::class);
        /** @var \Magento\Sales\Model\ResourceModel\Order $loader */
        $loader = Registry::getInstance()->get(\Magento\Sales\Model\ResourceModel\Order::class);
        $loader->load($this->shopSource, $this->getId());
    }

    /**
     * Loads a Credit memo source for the set id.
     *
     * @noinspection PhpUnused  Called via setShopSource().
     */
    protected function setShopSourceCreditNote(): void
    {
        $this->shopSource = Registry::getInstance()->create(Creditmemo::class);
        /** @var \Magento\Sales\Model\ResourceModel\Order $loader */
        $loader = Registry::getInstance()->get(\Magento\Sales\Model\ResourceModel\Order\Creditmemo::class);
        $loader->load($this->shopSource, $this->getId());
    }

    public function getReference()
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the order reference.
     */
    protected function getReferenceOrder(): string
    {
        return $this->getSource()->getIncrementId();
    }

    /**
     * Returns the credit note reference.
     */
    protected function getReferenceCreditNote(): string
    {
        return 'CM' . $this->getSource()->getIncrementId();
    }

    public function getDate(): string
    {
        // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
        return substr($this->getSource()->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }

    /**
     * Returns the status of this order.
     */
    protected function getStatusOrder(): string
    {
        return $this->getSource()->getStatus();
    }

    /**
     * Returns the status of this order.
     *
     * @return int
     *   1 of
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN     = 1;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED = 2;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED = 3;
     */
    protected function getStatusCreditNote(): int
    {
        return $this->getSource()->getState();
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the internal method name of the chosen payment
     * method.
     *
     * @noinspection BadExceptionsProcessingInspection
     */
    public function getPaymentMethod()
    {
        try {
            return $this->getOrder()->shopSource->getPayment()->getMethod();
        } catch (Throwable $e) {
            return parent::getPaymentMethod();
        }
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStatusOrder(): int
    {
        return Number::isZero($this->getSource()->getBaseTotalDue())
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    /**
     * Returns whether the credit memo has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStatusCreditNote(): int
    {
        return $this->getSource()->getState() === Creditmemo::STATE_REFUNDED
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    /**
     * Returns whether the order is in a status that makes it considered paid.
     * This method is NOT used to determine the paid status, but is used to
     * determine the paid date by looking for these statuses in the
     * StatusHistoryCollection.
     */
    protected function isPaidStatus(string $status): bool
    {
        return in_array($status, ['processing', 'closed', 'complete']);
    }

    /**
     * Returns the payment date for the order.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the order has not been paid
     *   yet.
     */
    protected function getPaymentDateOrder(): ?string
    {
        // Take date of last payment as payment date.
        $paymentDate = null;
        foreach ($this->getSource()->getStatusHistoryCollection() as $statusChange) {
            /** @var \Magento\Sales\Model\Order\Status\History $statusChange */
            if (!$paymentDate || $this->isPaidStatus($statusChange->getStatus())) {
                $createdAt = substr($statusChange->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
                if (!$paymentDate || $createdAt < $paymentDate) {
                    $paymentDate = $createdAt;
                }
            }
        }
        return $paymentDate;
    }

    /**
     * Returns the payment date for the credit memo.
     *
     * @return string|null
     *   The payment date (yyyy-mm-dd) or null if the credit memo has not been
     *   paid yet.
     */
    protected function getPaymentDateCreditNote(): ?string
    {
        return substr($this->getSource()->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }

    public function getCurrency(): Currency
    {
        return new Currency($this->getSource()->getOrderCurrencyCode(), (float) $this->getSource()->getBaseToOrderRate());
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values 'meta-invoice-amountinc' and
     * 'meta-invoice-vatamount'.
     */
    public function getTotals(): Totals
    {
        $sign = $this->getSign();
        return new Totals($sign * $this->getSource()->getBaseGrandTotal(), $sign * $this->getSource()->getBaseTaxAmount());
    }

    protected function setInvoice(): void
    {
        parent::setInvoice();
        if ($this->getType() === Source::Order) {
            $shopInvoices = $this->getSource()->getInvoiceCollection();
            if (count($shopInvoices) > 0) {
                $this->invoice = $shopInvoices->getFirstItem();
            }
        }
    }

    /**
     * {@see Source::getInvoiceId()}
     */
    public function getInvoiceIdOrder()
    {
        return $this->getInvoice() !== null ? $this->getInvoice()->getId() : null;
    }

    /**
     * {@see Source::getInvoiceReference()}
     */
    public function getInvoiceReferenceOrder()
    {
        // A credit note is to be considered an invoice on its own.
        return $this->getInvoice() !== null ? $this->getInvoice()->getIncrementId() : null;
    }

    /**
     * {@see Source::getInvoiceDate()}
     */
    public function getInvoiceDateOrder()
    {
        return $this->getInvoice() !== null ? substr($this->getInvoice()->getCreatedAt(), 0, strlen('2000-01-01')) : null;
    }

    protected function getShopOrderOrId()
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->shopSource;
        return $creditmemo->getOrderId();
    }

    protected function getShopCreditNotesOrIds()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->shopSource;
        return $order->getCreditmemosCollection();
    }

    public function getCountryCode(): string
    {
        return $this->getSource()->getBillingAddress()->getCountryId();
    }
}
