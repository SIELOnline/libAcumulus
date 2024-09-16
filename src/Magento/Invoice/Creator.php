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
use Magento\Tax\Model\ClassModel as TaxClass;
use Magento\Tax\Model\Config as MagentoTaxConfig;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Magento\Helpers\Registry;
use Siel\Acumulus\Meta;
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

    protected function getShippingLine(): array
    {
        $result = [];
        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $magentoSource */
        $magentoSource = $this->invoiceSource->getSource();
        // Only add a free shipping line on an order, not on a credit note:
        // free shipping is never refunded...
        if ($this->invoiceSource->getType() === Source::Order || !Number::isZero($magentoSource->getBaseShippingAmount())) {
            $result += [
                Tag::Product => $this->getShippingMethodName(),
                Tag::Quantity => 1,
            ];

            // What do the following methods return?
            // - getBaseShippingAmount(): shipping costs ex VAT ex any discount.
            // - getBaseShippingInclTax(): shipping costs inc VAT ex any discount.
            // - getBaseShippingTaxAmount(): VAT on shipping costs inc discount.
            // - getBaseShippingDiscountAmount(): discount on shipping inc VAT.
            if (!Number::isZero($magentoSource->getBaseShippingAmount())) {
                // We have 2 ways of calculating the vat rate: first one is
                // based on tax amount and normal shipping costs corrected with
                // any discount (as the tax amount is including any discount):
                // $vatRate1 = $magentoSource->getBaseShippingTaxAmount() / ($magentoSource->getBaseShippingInclTax()
                //   - $magentoSource->getBaseShippingDiscountAmount() - $magentoSource->getBaseShippingTaxAmount());
                // However, we will use the 2nd way as that seems to be more
                // precise and thus generally leads to a smaller range:
                // Get range based on normal shipping costs inc and ex VAT.
                $sign = $this->invoiceSource->getSign();
                $shippingInc = $sign * $magentoSource->getBaseShippingInclTax();
                $shippingEx = $sign * $magentoSource->getBaseShippingAmount();
                $shippingVat = $shippingInc - $shippingEx;
                $result += [
                        Tag::UnitPrice => $shippingEx,
                        Meta::UnitPriceInc => $shippingInc,
                        Meta::RecalculatePrice => $this->shippingPriceIncludeTax() ? Tag::UnitPrice : Meta::UnitPriceInc,
                    ] + self::getVatRangeTags($shippingVat, $shippingEx, 0.02, $this->shippingPriceIncludeTax() ? 0.02 : 0.01);
                $result[Meta::FieldsCalculated][] = Meta::VatAmount;

                // Add vat class meta data.
                $result += $this->getVatClassMetaData($this->getShippingTaxClassId());

                // getBaseShippingDiscountAmount() only exists on Orders.
                if ($this->invoiceSource->getType() === Source::Order && !Number::isZero($magentoSource->getBaseShippingDiscountAmount())) {
                    $tag = $this->discountIncludesTax() ? Meta::LineDiscountAmountInc : Meta::LineDiscountAmount;
                    $result[$tag] = -$sign * $magentoSource->getBaseShippingDiscountAmount();
                } elseif ($this->invoiceSource->getType() === Source::CreditNote
                    && !Number::floatsAreEqual($shippingVat, $magentoSource->getBaseShippingTaxAmount(), 0.02)) {
                    // On credit notes, the shipping discount amount is not
                    // stored but can be deduced via the shipping discount tax
                    // amount and the shipping vat rate. To get a more precise
                    // Meta::LineDiscountAmountInc, we compute that in the
                    // completor when we have corrected the vat rate.
                    $result[Meta::LineDiscountVatAmount] = $sign * ($shippingVat - $sign * $magentoSource->getBaseShippingTaxAmount());
                }
            } else {
                // Free shipping should get a "normal" tax rate. We leave that
                // to the completor to determine.
                $result += [
                    Tag::UnitPrice => 0,
                    Tag::VatRate => null,
                    Meta::VatRateSource => VatRateSource::Completor,
                ];
            }
        }
        return $result;
    }

    protected function getShippingMethodName(): string
    {
        $name = $this->order->getShippingDescription();
        if (!empty($name)) {
            return $name;
        }
        return parent::getShippingMethodName();
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Empty base method.
     */
    protected function getDiscountLines(): array
    {
        $result = [];

        /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo $source */
        $source = $this->invoiceSource->getSource();
        if (!Number::isZero($source->getBaseDiscountAmount())) {
            $line = [
                Tag::ItemNumber => '',
                Tag::Product => $this->getDiscountDescription(),
                Tag::VatRate => null,
                Meta::VatRateSource => VatRateSource::Strategy,
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

    protected function getDiscountDescription(): string
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
     * Returns metadata regarding the tax class.
     *
     * @param int|null $taxClassId
     *   The id of the tax class.
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId
     *   - Meta::VatClassName
     */
    protected function getVatClassMetaData(?int $taxClassId): array
    {
        $result = [];
        if ($taxClassId) {
            $result[Meta::VatClassId] = $taxClassId;
            /** @var TaxClass $taxClass */
            $taxClass = $this->getRegistry()->create(TaxClass::class);
            $this->getRegistry()->get($taxClass->getResourceName())->load($taxClass, $taxClassId);
            $result[Meta::VatClassName] = $taxClass->getClassName();
        } else {
            $result[Meta::VatClassId] = Config::VatClass_Null;
        }
        return $result;
    }

    /**
     * Returns whether shipping prices include tax.
     *
     * @return bool
     *   True if the prices for the products are entered with tax, false if the
     *   prices are entered without tax.
     */
    protected function productPricesIncludeTax(): bool
    {
        return $this->getTaxConfig()->priceIncludesTax();
    }

    /**
     * Returns whether shipping prices include tax.
     *
     * @return bool
     *   true if shipping prices include tax, false otherwise.
     */
    protected function shippingPriceIncludeTax(): bool
    {
        return $this->getTaxConfig()->shippingPriceIncludesTax();
    }

    /**
     * Returns the shipping tax class id.
     *
     * @return int
     *   The id of the tax class used for shipping.
     */
    protected function getShippingTaxClassId(): int
    {
        return $this->getTaxConfig()->getShippingTaxClass();
    }

    /**
     * Returns whether a discount amount includes tax.
     *
     * @return bool
     *   true if a discount is applied on the price including tax, false if a
     *   discount is applied on the price excluding tax.
     */
    protected function discountIncludesTax(): bool
    {
        return $this->getTaxConfig()->discountTax();
    }

    protected function getTaxConfig(): MagentoTaxConfig
    {
        return $this->getRegistry()->create(MagentoTaxConfig::class);
    }

    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
