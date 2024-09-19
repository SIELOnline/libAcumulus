<?php
/**
 * Although we would like to use strict equality, i.e. including type equality,
 * unconditionally changing each comparison in this file will lead to problems
 * - API responses return each value as string, even if it is an int or float.
 * - The shop environment may be lax in its typing by, e.g. using strings for
 *   each value coming from the database.
 * - Our own config object is type aware, but, e.g, uses string for a vat class
 *   regardless the type for vat class ids as used by the shop itself.
 * So for now, we will ignore the warnings about non strictly typed comparisons
 * in this code, and we won't use strict_types=1.
 * @noinspection TypeUnsafeComparisonInspection
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection DuplicatedCode  This is a copy of the old Creator.
 */

namespace Siel\Acumulus\Magento\Invoice;

use Magento\Customer\Model\Customer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Tag;

/**
 * Allows creating arrays in the Acumulus invoice structure from a Magento
 * order or credit memo.
 *
 * @property \Siel\Acumulus\Magento\Invoice\Source $invoiceSource
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Creator extends BaseCreator
{
    protected Order $order;
    protected ?Creditmemo $creditNote;

    /**
     * {@inheritdoc}
     *
     * This override also initializes Magento specific properties related to the
     * source.
     */
    protected function setInvoiceSource(Source $invoiceSource): void
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
    }

    protected function setPropertySources(): void
    {
        parent::setPropertySources();

        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $source */
        $source = $this->invoiceSource->getSource();
        $this->propertySources['customer'] = $this->getRegistry()->create(Customer::class)->load($source->getCustomerId());
    }

    /**
     * {@inheritdoc}
     *
     * This implementation may return a manual line for a credit memo.
     *
     * @noinspection PhpMissingParentCallCommonInspection Empty base method.
     */
    protected function getManualLines(): array
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

    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
