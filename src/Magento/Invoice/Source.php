<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Invoice;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo\Collection as CreditmemoCollection;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Magento\Helpers\Registry;
use Throwable;

use function count;
use function in_array;
use function sprintf;
use function strlen;

/**
 * Wraps a Magento order or credit memo in an invoice source object.
 *
 * @method Order|Creditmemo getShopObject()
 * @property Order|Creditmemo $shopObject
 */
class Source extends BaseSource
{
    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()->getId();
    }

    protected function setShopObject(): void
    {
        if ($this->getType() === Source::Order) {
            $this->setSourceOrder();
        } else {
            $this->setSourceCreditNote();
        }
    }

    /**
     * Loads an Order source for the set id.
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}.
     */
    protected function setSourceOrder(): void
    {
        $this->shopObject = Registry::getInstance()->create(Order::class);
        /** @var \Magento\Sales\Model\ResourceModel\Order $loader */
        $loader = Registry::getInstance()->get(\Magento\Sales\Model\ResourceModel\Order::class);
        $loader->load($this->shopObject, $this->getId());
        if ((int) $this->shopObject->getId() !== $this->getId()) {
            throw new RuntimeException(sprintf('Not a valid source id (%s %d)', $this->getType(), $this->getId()));
        }
    }

    /**
     * Loads a Credit memo source for the set id.
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}.
     */
    protected function setSourceCreditNote(): void
    {
        $this->shopObject = Registry::getInstance()->create(Creditmemo::class);
        /** @var \Magento\Sales\Model\ResourceModel\Order $loader */
        $loader = Registry::getInstance()->get(\Magento\Sales\Model\ResourceModel\Order\Creditmemo::class);
        $loader->load($this->shopObject, $this->getId());
        if ((int) $this->shopObject->getId() !== $this->getId()) {
            throw new RuntimeException(sprintf('Not a valid source id (%s %d)', $this->type, $this->id));
        }
    }

    public function getReference(): string
    {
        return $this->callTypeSpecificMethod(__FUNCTION__);
    }

    /**
     * Returns the order reference (something like 00000123).
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getReferenceOrder(): string
    {
        return $this->getShopObject()->getIncrementId();
    }

    /**
     * Returns the credit note reference (something like CM00000123).
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getReferenceCreditNote(): string
    {
        return 'CM' . $this->getShopObject()->getIncrementId();
    }

    public function getDate(): string
    {
        // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
        return substr($this->getShopObject()->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }

    /**
     * Returns the status of this order.
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getStatusOrder(): string
    {
        return $this->getShopObject()->getStatus();
    }

    /**
     * Returns the status of this order.
     *
     * @return int
     *   1 of
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_OPEN     = 1;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED = 2;
     *   \Magento\Sales\Model\Order\Creditmemo::STATE_CANCELED = 3;
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getStatusCreditNote(): int
    {
        return (int) $this->getShopObject()->getState();
    }

    /**
     * {@inheritdoc}
     *
     * @return ?int
     *   This override returns the internal method name of the chosen payment method.
     *
     * @noinspection BadExceptionsProcessingInspection
     */
    public function getPaymentMethod(): ?string
    {
        try {
            return $this->getOrder()->getShopObject()->getPayment()->getMethod();
        } catch (Throwable) {
            return parent::getPaymentMethod();
        }
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getPaymentStatusOrder(): int
    {
        return Number::isZero($this->getShopObject()->getBaseTotalDue())
            ? Api::PaymentStatus_Paid
            : Api::PaymentStatus_Due;
    }

    /**
     * Returns whether the credit memo has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getPaymentStatusCreditNote(): int
    {
        // @todo: how and when does a credit note get the state refunded?
        return (int) $this->getShopObject()->getState() === Creditmemo::STATE_REFUNDED
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
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getPaymentDateOrder(): ?string
    {
        // Take date of last payment as payment date.
        $paymentDate = null;
        // @error: the history collection is largely empty in our test database??!
        $statusHistoryCollection = $this->getShopObject()->getStatusHistoryCollection();
        foreach ($statusHistoryCollection as $statusChange) {
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
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getPaymentDateCreditNote(): ?string
    {
        // @todo: how and when does a credit note get the state refunded? when we know,
        //   createdAt will probably no longer be correct.
        return (int) $this->getShopObject()->getState() === Creditmemo::STATE_REFUNDED
            ? substr($this->getShopObject()->getCreatedAt(), 0, strlen('yyyy-mm-dd'))
            : null;
    }

    public function getCurrency(): Currency
    {
        return new Currency($this->getShopObject()->getOrderCurrencyCode(), (float) $this->getShopObject()->getBaseToOrderRate());
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
        return new Totals($sign * $this->getShopObject()->getBaseGrandTotal(), $sign * $this->getShopObject()->getBaseTaxAmount());
    }

    protected function setInvoice(): void
    {
        parent::setInvoice();
        if ($this->getType() === Source::Order) {
            $shopInvoices = $this->getShopObject()->getInvoiceCollection();
            if (count($shopInvoices) > 0) {
                $this->invoice = $shopInvoices->getFirstItem();
            }
        }
    }

    /**
     * @noinspection PhpUnused
     *    Called by {@see Source::getInvoiceId()} via {@see Source::callTypeSpecificMethod()}.
     */
    protected function getInvoiceIdOrder(): ?int
    {
        return $this->getInvoice() !== null ? (int) $this->getInvoice()->getId() : null;
    }

    /**
     * {@see Source::getInvoiceReference()}
     *
     * @return string|null
     *   The increment id (with padding 0's, thus a string) or null if not yet set
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getInvoiceReferenceOrder(): ?string
    {
        return $this->getInvoice() !== null ? $this->getInvoice()->getIncrementId() : null;
    }

    /**
     * {@see Source::getInvoiceDate()}
     *
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function getInvoiceDateOrder(): ?string
    {
        return $this->getInvoice() !== null ? substr($this->getInvoice()->getCreatedAt(), 0, strlen('2000-01-01')) : null;
    }

    protected function getShopOrderOrId(): int
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $creditmemo = $this->shopObject;
        /**
         * @noinspection PhpCastIsUnnecessaryInspection  despite the documented return
         *   type, id is returned as a string.
         */
        return (int) $creditmemo->getOrderId();
    }

    protected function getShopCreditNotesOrIds(): CreditmemoCollection
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->shopObject;
        return $order->getCreditmemosCollection();
    }

    public function getCountryCode(): string
    {
        return $this->getShopObject()->getBillingAddress()->getCountryId();
    }

    /**
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function createItemsOrder(): array
    {
        $result = [];
        // Items may be composed, so start with all "visible" items.
        foreach ($this->getShopObject()->getAllVisibleItems() as $item) {
            $result[] = $this->getContainer()->createItem($item, $this);
        }
        return $result;
    }

    /**
     * @noinspection PhpUnused  Called via {@see Source::callTypeSpecificMethod()}
     */
    protected function createItemsCreditNote(): array
    {
        $result = [];
        // A CreditMemo does not have the method getAllVisibleItems().
        foreach ($this->getShopObject()->getAllItems() as $item) {
            // Only items for which row total is set, are refunded.
            if (!Number::isZero($item->getRowTotal())) {
                $result[] = $this->getContainer()->createItem($item, $this);
            }
        }
        return $result;
    }

    public function getShippingLineInfos(): array
    {
        return  $this->getType() === Source::Order || !Number::isZero($this->getShopObject()->getBaseShippingAmount()) ? [$this] : [];
    }

    public function getDiscountLineInfos(): array
    {
        return !Number::isZero($this->getShopObject()->getBaseDiscountAmount()) ? [$this] : [];
    }

    public function getManualLineInfos(): array
    {
        return $this->getType() === Source::CreditNote && !Number::isZero($this->getShopObject()->getBaseAdjustment()) ? [$this] : [];
    }
}
