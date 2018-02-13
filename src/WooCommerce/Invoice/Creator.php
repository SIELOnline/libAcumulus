<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;
use WC_Abstract_Order;
use WC_Coupon;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Tax;

/**
 * Allows to create an Acumulus invoice from a WooCommerce order or refund.
 */
class Creator extends BaseCreator
{
    /** @var WC_Abstract_Order The order or refund that is sent to Acumulus. */
    protected $shopSource;

    /** @var WC_Order The order self or the order that got refunded. */
    protected $order;

    /** @var bool Whether the order has (non empty) item lines. */
    protected $hasItemLines;

    /**
     * Product price precision in WC3: one of the prices is entered by the
     * administrator but rounded to the cent by WC. The computed one is based
     * on the subtraction/addition of 2 amounts rounded to the cent, so has a
     * precision that may be a bit worse than 1 cent.
     *
     * values here.
     *
     * @var float
     */
    protected $precisionPriceEntered  = 0.01;
    protected $precisionPriceCalculated  = 0.02;
    protected $precisionVat  = 0.01;

    /**
     * {@inheritdoc}
     *
     * This override also initializes WooCommerce specific properties related to
     * the source.
     */
    protected function setInvoiceSource($invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);
        $this->shopSource = $this->invoiceSource->getSource();
        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->shopSource;
                break;
            case Source::CreditNote:
                $this->order = new WC_Order($this->shopSource->get_parent_id());
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setPropertySources()
    {
        parent::setPropertySources();
        if ($this->invoiceSource->getType() === Source::CreditNote) {
            $this->propertySources['order'] = $this->order;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getCountryCode()
    {
        return $this->order->get_billing_country();
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the id of a WC_Payment_Gateway.
     */
    protected function getPaymentMethod() {
        // Payment method is not stored for credit notes, so it is expected to
        // be the same as for its order.
        return $this->order->get_payment_method();
    }

    /**
     * Returns whether the order has been paid or not.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStateOrder()
    {
        return $this->order->needs_payment() ? Api::PaymentStatus_Due : Api::PaymentStatus_Paid;
    }

    /**
     * Returns whether the order refund has been paid or not.
     *
     * For now we assume that a refund is paid back on creation.
     *
     * @return int
     *   \Siel\Acumulus\Api::PaymentStatus_Paid or
     *   \Siel\Acumulus\Api::PaymentStatus_Due
     */
    protected function getPaymentStateCreditNote()
    {
        return Api::PaymentStatus_Paid;
    }

    /**
     * Returns the payment date of the order.
     *
     * @return string
     *   The payment date of the order (yyyy-mm-dd).
     */
    protected function getPaymentDateOrder()
    {
        // This returns a WC_DateTime but that class has a _toString() method.
        $string = $this->order->get_date_paid();
        return substr($string, 0, strlen('2000-01-01'));
    }

    /**
     * Returns the payment date of the order refund.
     *
     * We take the last modified date as pay date.
     *
     * @return string
     *   The payment date of the order refund (yyyy-mm-dd).
     */
    protected function getPaymentDateCreditNote()
    {
        // This returns a WC_DateTime but that class has a _toString() method.
        $string = $this->shopSource->get_date_modified();
        return substr($string, 0, strlen('2000-01-01'));
    }

    /**
     * {@inheritdoc}
     *
     * WooCommerce does not support multiple currencies, so the amounts are
     * always in the shop's default currency. Even if another plugin is used to
     * present another currency to the customer, the amounts stored will
     * (probably) still be in euro's. So, we will not have to convert the
     * amounts and this meta info is thus purely informative.
     */
    protected function addCurrency()
    {
        $result = array(
            Meta::Currency => 'EUR',
            Meta::CurrencyRate => 1.0,
            Meta::CurrencyDoConvert => false,
        );
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc and
     * meta-invoice-vatamount.
     */
    protected function getInvoiceTotals()
    {
        return array(
            Meta::InvoiceAmountInc => $this->shopSource->get_total(),
            Meta::InvoiceVatAmount => $this->shopSource->get_total_tax(),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array();
        /** @var WC_Order_Item_Product[] $items */
        $items = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item'));
        foreach ($items as $item) {
            $product = $item->get_product();
            $line = $this->getItemLine($item, $product);
            if ($line) {
                $result[] = $line;
            }
        }

        $this->hasItemLines = count($result) > 0;
        return $result;
    }

    /**
     * Returns 1 item line.
     *
     * @param WC_Order_Item_Product $item
     *   An array representing an order item line, meta values are already
     *   available under their own names and as an array under key 'item_meta'.
     * @param WC_Product|bool $product
     *   The product that was sold on this line, may also be a bool according to
     *   the WC3 php documentation. I guess it will be false if the product has
     *   been deleted since.
     *
     * @return array
     *   May be empty if the line should not be sent (e.g. qty = 0 on a refund).
     *
     * @throws \ReflectionException
     */
    protected function getItemLine(WC_Order_Item_Product $item, $product)
    {
        $result = array();

        // Qty = 0 can happen on refunds: products that are not returned are
        // still listed but have qty = 0.
        if (Number::isZero($item->get_quantity())) {
            return $result;
        }

        $creatorPluginSupport = $this->container->getInstance('CreatorPluginSupport', 'Invoice');
        $creatorPluginSupport->getItemLineBefore($this, $item, $product);
        // $product can be null if the product has been deleted.
        if ($product instanceof WC_Product) {
            $this->addPropertySource('product', $product);
        }
        $this->addPropertySource('item', $item);

        $invoiceSettings = $this->config->getInvoiceSettings();
        $this->addTokenDefault($result, Tag::ItemNumber, $invoiceSettings['itemNumber']);
        $this->addTokenDefault($result, Tag::Product, $invoiceSettings['productName']);
        $this->addTokenDefault($result, Tag::Nature, $invoiceSettings['nature']);
        $result[Meta::Id] = $item->get_id();


        // Add quantity: quantity is negative on refunds, the unit price will be
        // positive.
        $quantity = $item->get_quantity();
        $commonTags = array(Tag::Quantity => $quantity);
        $result += $commonTags;

        // Add price info. get_total() and get_total_tax() return line totals
        // after discount (and will be negative on refunds).
        $productPriceEx = $item->get_total() / $quantity;
        $productVat = $item->get_total_tax() / $quantity;
        $productPriceInc = $productPriceEx + $productVat;

        // Get precision info.
        if ($this->productPricesIncludeTax()) {
            $precisionEx = $this->precisionPriceCalculated;
            $precisionInc = $this->precisionPriceEntered;
            $recalculateUnitPrice = true;
        } else {
            $precisionEx = $this->precisionPriceEntered;
            $precisionInc = $this->precisionPriceCalculated;
            $recalculateUnitPrice = false;
        }

        // WooCommerce does not support the margin scheme. So in a standard
        // install this method will always return false. But if this method
        // happens to return true anyway (customisation, hook), the costprice
        // tag will trigger vattype = 5 for Acumulus.
        if ($this->allowMarginScheme() && !empty($invoiceSettings['costPrice'])) {
            $value = $this->getTokenizedValue($invoiceSettings['costPrice']);
            if (!empty($value)) {
                // Margin scheme:
                // - Do not put VAT on invoice: send price incl VAT as unitprice.
                // - But still send the VAT rate to Acumulus.
                // Costprice > 0 triggers the margin scheme in Acumulus.
                $result += array(
                    Tag::UnitPrice => $productPriceInc,
                    Meta::PrecisionUnitPrice => $this->precisionPriceEntered,
                    Tag::CostPrice => $value,
                    Meta::PrecisionCostPrice => $this->precisionPriceEntered,
                );
            }
        } else {
            $result += array(
                Tag::UnitPrice => $productPriceEx,
                Meta::PrecisionUnitPrice => $precisionEx,
                Meta::UnitPriceInc => $productPriceInc,
                Meta::PrecisionUnitPriceInc => $precisionInc,
                Meta::RecalculateUnitPrice => $recalculateUnitPrice,
            );

        }

        // Add tax info.
        $result += $this->getVatRangeTags($productVat, $productPriceEx, $this->precisionVat, $precisionEx);
        if ($product instanceof WC_Product) {
            $result += $this->getVatRateLookupMetadataByTaxClass($product->get_tax_class());
        }

        // Add variants/options.
        $commonTags[Meta::VatRateSource] = static::VatRateSource_Parent;
        if ($product instanceof WC_Product && $item->get_variation_id()) {
            $result[Meta::ChildrenLines] = $this->getVariantLines($item, $product, $commonTags);
        }

        $this->removePropertySource('product');
        $this->removePropertySource('item');

        $creatorPluginSupport->getItemLineAfter($this, $item, $product);

        return $result;
    }

    /**
     * Looks up and returns vat rate metadata for product lines.
     *
     * A product has a tax class. A tax class can have multiple tax rates,
     * depending on the region of the customer. As we don't have that data here,
     * this method will only return metadata if only 1 rate was found.
     *
     * @param string $taxClass
     *   The tax class of the product.
     *
     * @return array
     *   Either an array with keys Meta::VatRateLookup and
     *  Meta::VatRateLookupLabel or an empty array.
     */
    protected function getVatRateLookupMetadataByTaxClass($taxClass) {
        if ($taxClass === 'standard') {
            $taxClass = '';
        }
        $result = array();
        $taxRates = WC_Tax::get_rates($taxClass);
        if (count($taxRates) === 1) {
            $taxRate = reset($taxRates);
            $result = array(
                Meta::VatRateLookup => $taxRate['rate'],
                Meta::VatRateLookupLabel => $taxRate['label'],
            );
        }
        return $result;
    }

    /**
     * Returns an array of lines that describes this variant.
     *
     * This method supports the default WooCommerce variant functionality.
     *
     * @param \WC_Order_Item_Product $item
     * @param \WC_Product $product
     * @param array $commonTags
     *   An array of tags from the parent product to add to the child lines.
     *
     * @return array[]
     *   An array of lines that describes this variant.
     */
    protected function getVariantLines($item, WC_Product $product, array $commonTags)
    {
        $result = array();

        /**
         * An array of objects with properties id, key, and value.
         *
         * @var object[] $metadata
         */
        $metadata = $item->get_meta_data();
        if (!empty($metadata)) {
            // Define hidden core fields.
            $hiddenOrderItemMeta = apply_filters('woocommerce_hidden_order_itemmeta', array(
                '_qty',
                '_tax_class',
                '_product_id',
                '_variation_id',
                '_line_subtotal',
                '_line_subtotal_tax',
                '_line_total',
                '_line_tax',
            ));
            foreach ($metadata as $meta) {
                // Skip hidden core fields and serialized data (also hidden core
                // fields).
                if (in_array($meta->key, $hiddenOrderItemMeta) || is_serialized($meta->value)) {
                    continue;
                }

                // Get attribute data.
                if (taxonomy_exists(wc_sanitize_taxonomy_name($meta->key))) {
                    $term = get_term_by('slug', $meta->value, wc_sanitize_taxonomy_name($meta->key));
                    $variantLabel = wc_attribute_label(wc_sanitize_taxonomy_name($meta->key));
                    $variantValue = isset($term->name) ? $term->name : $meta->value;
                } else {
                    $variantLabel = apply_filters('woocommerce_attribute_label', wc_attribute_label($meta->key, $product), $meta->key);
                    $variantValue = $meta->value;
                }

                $result[] = array(
                        Tag::Product => $variantLabel . ': ' . rawurldecode($variantValue),
                        Tag::UnitPrice => 0,
                    ) + $commonTags;
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     *
     * WooCommerce has general fee lines, so we have to override this method to
     * add these general fees (type unknown to us)
     */
    protected function getFeeLines()
    {
        $result = parent::getFeeLines();

        // So far, all amounts found on refunds are negative, so we probably
        // don't need to correct the sign on these lines either: but this has
        // not been tested yet!.
        foreach ($this->shopSource->get_fees() as $feeLine) {
            $line = $this->getFeeLine($feeLine);
            $line[Meta::LineType] = static::LineType_Other;
            $result[] = $line;
        }
        return $result;
    }

  /**
   * @param \WC_Order_Item_Fee $item
   *
   * @return array
   */
    protected function getFeeLine($item)
    {
        $quantity = $item->get_quantity();
        $feeEx = $item->get_total() / $quantity;
        $feeVat = $item->get_total_tax() / $quantity;

        $result = array(
                Tag::Product => $this->t($item->get_name()),
                Tag::UnitPrice => $feeEx,
                Meta::PrecisionUnitPrice => 0.01,
                Tag::Quantity => $item->get_quantity(),
            ) + $this->getVatRangeTags($feeVat, $feeEx);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLines()
    {
        $result = array();
        // Get the shipping lines for this order.
        /** @var \WC_Order_Item_Shipping[] $shippingItems */
        $shippingItems = $this->shopSource->get_items(apply_filters('woocommerce_admin_order_item_types', 'shipping'));
        foreach ($shippingItems as $shippingItem) {
            $shippingLine = $this->getShippingLine($shippingItem);
            if ($shippingLine) {
                $result[] = $shippingLine;
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine()
    {
        /** @var \WC_Order_Item_Shipping $item */
        $item = func_get_arg(0);
        $taxes = $item->get_taxes();
        $vatLookupTags = $this->getShippingVatRateLookupMetadata($taxes);

        // Note: this info is WC3 specific.
        // Precision: shipping costs are entered ex VAT, so that may be very
        // precise, but it will be rounded to the cent by WC. The VAT is also
        // rounded to the cent.
        $shippingEx = $item->get_total();
        $shippingExPrecision = 0.01;

        // To avoid rounding errors, we try to get the non-formatted amount
        $methodId = $item->get_method_id();
        if (substr($methodId, 0, strlen('legacy_')) === 'legacy_') {
            $methodId = substr($methodId, strlen('legacy_'));
        }
        $options = get_option( 'woocommerce_' . $methodId . '_settings' );
        if (isset($options['cost']) && Number::floatsAreEqual($options['cost'], $shippingEx)) {
            $shippingEx = $options['cost'];
            $shippingExPrecision = 0.001;
        }
        $quantity = $item->get_quantity();
        $shippingEx /= $quantity;
        $shippingVat = $item->get_total_tax() / $quantity;
        $vatPrecision = 0.01;

        $result = array(
                Tag::Product => $item->get_name(),
                Tag::UnitPrice => $shippingEx,
                Meta::PrecisionUnitPrice => $shippingExPrecision,
                Tag::Quantity => $quantity,
            )
                  + $this->getVatRangeTags($shippingVat, $shippingEx, $vatPrecision, $shippingExPrecision)
                  + $vatLookupTags;

        return $result;
    }

    /**
     * Looks up and returns vat rate metadata for shipping lines.
     *
     * In WooCommerce, a shipping line can have multiple taxes. I am not sure if
     * that is possible for Dutch web shops, but if a shipping line does have
     * multiple taxes we fall back to the tax class setting for shipping
     * methods, that can have multiple tax rates itself (@see
     * getVatRateLookupMetadataByTaxClass()). Anyway, this method will only
     * return metadata if only 1 rate was found.
     *
     * @param array|null $taxes
     *   The taxes applied to a shipping line.
     *
     * @return array
     *   Either an array with keys Meta::VatRateLookup,
     *  Meta::VatRateLookupLabel, and Meta::VatRateLookupSource or an
     *   empty array.
     */
    protected function getShippingVatRateLookupMetadata($taxes)
    {
        $vatLookupTags = array();
        if (is_array($taxes)) {
            // Since ??? $taxes is indirected by a key 'total' ...
            if (!is_numeric(key($taxes))) {
                $taxes = current($taxes);
            }
            if (is_array($taxes) && count($taxes) === 1) {
                // @todo: $tax contains amount: can we use that?
                //$tax = reset($taxes);
                $vatLookupTags = array(
                    // Will contain a % at the end of the string.
                    Meta::VatRateLookup => substr(WC_Tax::get_rate_percent(key($taxes)), 0, -1),
                    Meta::VatRateLookupLabel => WC_Tax::get_rate_label(key($taxes)),
                    Meta::VatRateLookupSource => 'shipping line taxes',
                );
            }
        }
        if (empty($vatLookupTags)) {
            // Apparently we have free shipping (or a misconfigured shipment
            // method). Use a fall-back: WooCommerce only knows 1 tax rate
            // for all shipping methods, stored in config:
            $shipping_tax_class = get_option('woocommerce_shipping_tax_class');
            if (is_string($shipping_tax_class)) {
                $vatLookupTags = $this->getVatRateLookupMetadataByTaxClass($shipping_tax_class);
                if (!empty($vatLookupTags)) {
                    $vatLookupTags [Meta::VatRateLookupSource] = "get_option('woocommerce_shipping_tax_class')";
                }
            }
        }
        return $vatLookupTags;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDiscountLines()
    {
        $result = array();

        // For refunds without any articles (probably just a manual refund) we
        // don't need to know what discounts were applied on the original order.
        // So skip get_used_coupons() on refunds without articles.
        if ($this->invoiceSource->getType() !== Source::CreditNote || $this->hasItemLines) {
            // Add a line for all coupons applied. Coupons are only stored on
            // the order, not on refunds, so use the order property.
            $usedCoupons = $this->order->get_used_coupons();
            foreach ($usedCoupons as $code) {
                $coupon = new WC_Coupon($code);
                $result[] = $this->getDiscountLine($coupon);
            }
        }
        return $result;
    }

    /**
     * Returns 1 order discount line for 1 coupon usage.
     *
     * In WooCommerce, discounts are implemented with coupons. Multiple coupons
     * can be used per order. Coupons can:
     * - have a fixed amount or a percentage.
     * - be applied to the whole cart or only be used for a set of products.
     *
     * Discounts are already applied, add a descriptive line with 0 amount. The
     * VAT rate to categorize this line under should be determined by the
     * completor.
     *
     * Hooray:
     * As of WooCommerce 2.3, coupons can no longer be set as "apply after tax":
     * https://woocommerce.wordpress.com/2014/12/12/upcoming-coupon-changes-in-woocommerce-2-3/
     * WC_Coupon::apply_before_tax() now always returns true (and thus might be
     * deprecated and removed in the future): do no longer use.
     *
     * @param \WC_Coupon $coupon
     *
     * @return array
     */
    protected function getDiscountLine(WC_Coupon $coupon)
    {
        // Get a description for the value of this coupon. Entered discount
        // amounts follow the productPricesIncludeTax() setting. Use that info
        // in the description.
        if ($coupon->get_id()) {
            // Coupon still exists: extract info from coupon.
            $description = sprintf('%s %s: ', $this->t('discount_code'), $coupon->get_code());
            if (in_array($coupon->get_discount_type(), array('fixed_product', 'fixed_cart'))) {
                $amount = $this->getSign() * (float) $coupon->get_amount();
                if (!Number::isZero($amount)) {
                    $description .= sprintf('€%.2f (%s)', $amount, $this->productPricesIncludeTax() ? $this->t('inc_vat') : $this->t('ex_vat'));
                }
                if ($coupon->get_free_shipping()) {
                    if (!Number::isZero($amount)) {
                        $description .= ' + ';
                    }
                    $description .= $this->t('free_shipping');
                }
            } else {
                // Value may be entered with or without % sign at the end.
                // Remove it by converting to a float.
                $description .= ((float) $coupon->get_amount()) . '%';
                if ($coupon->get_free_shipping()) {
                    $description .= ' + ' . $this->t('free_shipping');
                }
            }
        } else {
            // Coupon no longer exists: use generic name.
            $description = $this->t('discount_code');
        }
        return array(
            Tag::ItemNumber => $coupon->get_code(),
            Tag::Product => $description,
            Tag::UnitPrice => 0,
            Meta::UnitPriceInc => 0,
            Tag::Quantity => 1,
            Tag::VatRate => null,
            Meta::VatAmount => 0,
            Meta::VatRateSource => static::VatRateSource_Completor,
        );
    }

    /**
     *
     *
     *
     * @return bool
     *
     */
    protected function productPricesIncludeTax() {
        return wc_prices_include_tax();
    }
}
