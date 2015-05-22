<?php
namespace Siel\Acumulus\Shop\Magento;

use Mage;
use Mage_Core_Model_Resource_Db_Collection_Abstract;
use Mage_Customer_Model_Customer;
use Mage_Sales_Model_Order;
use Mage_Sales_Model_Order_Creditmemo;
use Mage_Sales_Model_Order_Creditmemo_Item;
use Mage_Sales_Model_Order_Invoice;
use Mage_Sales_Model_Order_Item;
use Mage_Sales_Model_Order_Payment;
use Mage_Tax_Model_Config;
use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Shop\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;

/**
 * Allows to create arrays in the Acumulus invoice structure from a Magento
 * order or credit memo.
 *
 * @todo: multi currency: use base values (default store currency) or values
 *   without base in their names (selected store currency). Other fields
 *   involved:
 *   - base_currency_code
 *   - store_to_base_rate
 *   - store_to_order_rate
 *   - order_currency_code
 */
class Creator extends BaseCreator {

  /** @var Mage_Sales_Model_Order */
  protected $order;

  /** @var Mage_Sales_Model_Order_Creditmemo */
  protected $creditNote;

  /** @var Mage_Core_Model_Resource_Db_Collection_Abstract */
  protected $shopInvoices;

  /** @var Mage_Sales_Model_Order_Invoice */
  protected $shopInvoice;

  /**
   * {@inheritdoc}
   *
   * This override also initializes Magento specific properties related to the
   * source.
   */
  protected function setSource($source) {
    parent::setSource($source);
    switch ($this->source->getType()) {
      case Source::Order:
        $this->order = $this->source->getSource();
        $this->creditNote = NULL;
        break;
      case Source::CreditNote:
        $this->creditNote = $this->source->getSource();
        $this->order = $this->creditNote->getOrder();
        break;
      default:
        $this->config->getLog()->log(Log::Error, 'Creator::setSource(): unknown source type %s', array($this->source->getType()));
        break;
    }
    $this->shopInvoices = $this->order->getInvoiceCollection();
    $this->shopInvoice = count($this->shopInvoices) > 0 ? $this->shopInvoices->getFirstItem() : null;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCustomer() {
    $result = array();

    /** @var Mage_Sales_Model_Order|Mage_Sales_Model_Order_Creditmemo $order */
    $order = $this->creditNote !== null ? $this->creditNote : $this->order;

    if ($order->getCustomerId()) {
      /** @var Mage_Customer_Model_Customer $customer */
      $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
      $result['contactyourid'] = $customer->getId();
      $this->addIfNotEmpty($result, 'contactyourid', $customer->getIncrementId());
    }

    $invoiceAddress = $order->getBillingAddress();
    $this->addEmpty($result, 'companyname1', $invoiceAddress->getCompany());
    $result['companyname2'] = '';
    $result['fullname'] = $invoiceAddress->getFirstname() . ' ' . $invoiceAddress->getLastname();
    $this->addEmpty($result, 'address1', $invoiceAddress->getStreet(1));
    $this->addEmpty($result, 'address2', $invoiceAddress->getStreet(2));
    $this->addEmpty($result, 'postalcode', $invoiceAddress->getPostcode());
    $this->addEmpty($result, 'city', $invoiceAddress->getCity());
    if ($invoiceAddress->getCountryId()) {
      $result['countrycode'] = $invoiceAddress->getCountry();
    }
    // Magento has 2 VAT numbers:
    // http://magento.stackexchange.com/questions/42164/there-are-2-vat-fields-in-onepage-checkout-which-one-should-i-be-using
    $this->addIfNotEmpty($result, 'vatnumber', $order->getCustomerTaxvat());
    $this->addIfNotEmpty($result, 'vatnumber', $invoiceAddress->getVatId());
    $this->addIfNotEmpty($result, 'telephone', $invoiceAddress->getTelephone());
    $this->addIfNotEmpty($result, 'fax', $invoiceAddress->getFax());
    $result['email'] = $invoiceAddress->getEmail();

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceNumber($invoiceNumberSource) {
    $result = $this->source->getReference();
    if ($invoiceNumberSource == ConfigInterface::InvoiceNrSource_ShopInvoice && $this->source->getType() === Source::Order && $this->shopInvoice !== NULL) {
      $result = $this->shopInvoice->getIncrementId();
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInvoiceDate($dateToUse) {
    // createdAt returns yyyy-mm-dd hh:mm:ss, take date part.
    $result = substr($this->source->getSource()->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    // A credit note is its own invoice
    if ($dateToUse == ConfigInterface::InvoiceDate_InvoiceCreate && $this->source->getType() === Source::Order && $this->shopInvoice !== NULL) {
      $result = substr($this->shopInvoice->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentState() {
    return $this->callTypeSpecificMethod(__FUNCTION__);
  }

  protected function getPaymentStateOrder() {
    return $this->isZero($this->order->getBaseTotalDue())
      ? InvoiceConfigInterface::PaymentStatus_Paid
      : InvoiceConfigInterface::PaymentStatus_Due;
  }

  protected function getPaymentStateCreditNote() {
    return $this->creditNote->getState() == Mage_Sales_Model_Order_Creditmemo::STATE_REFUNDED
      ? InvoiceConfigInterface::PaymentStatus_Paid
      : InvoiceConfigInterface::PaymentStatus_Due;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentDate() {
    return $this->callTypeSpecificMethod(__FUNCTION__);
  }

  protected function getPaymentDateOrder() {
    // Take date of last payment as payment date.
    $paymentDate = null;
    foreach($this->order->getAllPayments() as $payment) {
      /** @var Mage_Sales_Model_Order_Payment $payment */
      if (!$paymentDate || substr($payment->getUpdatedAt(), 0, strlen('yyyy-mm-dd')) > $paymentDate) {
        $paymentDate = substr($payment->getUpdatedAt(), 0, strlen('yyyy-mm-dd'));
      }
    }
    return $paymentDate;
  }

  protected function getPaymentDateCreditNote() {
    return substr($this->creditNote->getCreatedAt(), 0, strlen('yyyy-mm-dd'));
  }

  /**
   * {@inheritdoc}
   *
   * This override provides the values meta-invoiceamountinc and
   * meta-invoicevatamount.
   */
  protected function getInvoiceTotals() {
    $sign = $this->source->getType() === Source::CreditNote ? -1.0 : 1.0;
    return array(
      'meta-invoiceamountinc' => $sign * $this->source->getSource()->getBaseGrandTotal(),
      'meta-invoicevatamount' => $sign * $this->source->getSource()->getBaseTaxAmount(),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLines() {
    return $this->callTypeSpecificMethod(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLinesOrder() {
    $result = array();
    // Items may be composed, so start with all "visible" items.
    foreach ($this->order->getAllVisibleItems() as $item) {
      $result = array_merge($result, $this->getItemLineOrder($item));
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getItemLinesCreditNote() {
    $result = array();
    // Items may be composed, so start with all "visible" items.
    foreach ($this->creditNote->getAllItems() as $item) {
      // Only items for which row total is set, are refunded
      /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
      if ((float) $item->getRowTotal() > 0.0) {
        $result[] = $this->getItemLineCreditNote($item);
      }
    }
    return $result;
  }

  /**
   * Returns 1 or more item lines for 1 main product line.
   *
   * @param Mage_Sales_Model_Order_Item $item
   *
   * @return array
   */
  protected function getItemLineOrder(Mage_Sales_Model_Order_Item $item) {
    $result = array();
    $childLines = array();

    $vatRate = (float) $item->getTaxPercent();
    $metaVatRateSource = static::VatRateSource_Exact;
    $productPriceInc = (float) $item->getPriceInclTax();
    // For higher precision of the unit price, we use the prices as entered by
    // the admin.
    $productPriceEx = $this->productPricesIncludeTax() ? (float) $productPriceInc / (100 + $vatRate) * 100 : (float) $item->getPrice();
    // Tax amount = VAT over discounted product price.
    // Hidden tax amount = VAT over discount.
    // But as discounts get their own lines and the product lines are showing
    // the normal (not discounted) price we add these 2.
    $lineVat = (float) $item->getTaxAmount() + (float) $item->getHiddenTaxAmount();

    // Simple products (products without children): add as 1 line.
    $this->addIfNotEmpty($result, 'itemnumber', $item->getSku());
    $result += array(
      'product' => $item->getName(),
      'unitprice' => $productPriceEx,
      'unitpriceinc' => $productPriceInc,
      'vatrate' => $vatRate,
      'meta-linevatamount' => $lineVat,
      'quantity' => $item->getQtyOrdered(),
      'meta-vatrate-source' => $metaVatRateSource,
    );
    if ($this->isAmount($item->getDiscountAmount())) {
      // Store discount on this item to be able to get correct discount lines.
      $result['meta-linediscountamountinc'] = -$item->getDiscountAmount();
    }

    // Also add child lines for composed products, a.o. to be able to print a
    // packing slip in Acumulus.
    foreach($item->getChildrenItems() as $child) {
      $childLine = $this->getItemLineOrder($child);
      $childLines = array_merge($childLines, $childLine);
    }

    // If:
    // - there is exactly 1 child line
    // - for the same item number, and quantity
    // - with no price info on the child
    // We seem to be processing a configurable product that for some reason
    // appears twice: do not add the child, but copy the product description to
    // the result as it contains more chose option descriptions.
    // @todo: refine this: when to add just 1 line, when multiple lines.
    if (count($childLines) === 1
      && $result['itemnumber'] === $childLines[0]['itemnumber']
      && $childLines[0]['unitprice'] == 0
      && $result['quantity'] === $childLines[0]['quantity']) {
      $result['product'] = $childLines[0]['product'];
      $childLines = array();
    }
    // keep price info on bundle level or child level?
    if (count($childLines) > 0) {
      if ($item->getPriceInclTax() > 0.0 && ($item->getTaxPercent() > 0 || $item->getTaxAmount() == 0.0)) {
        // If the bundle line contains valid price and tax info, we remove that
        // info from all child lines (to prevent accounting amounts twice).
        foreach ($childLines as &$childLine) {
          $childLine['unitprice'] = 0;
          $childLine['vatrate'] = $result['vatrate'];
        }
      }
      else {
        // Do all children have the same vat?
        $vatRate = null;
        foreach ($childLines as $childLine) {
          // Check if this is not an empty price/vat line.
          if ($childLine['unitprice'] != 0 && $childLine['vatrate'] !== -1) {
            // Same vat?
            if ($vatRate === null || $childLine['vatrate'] === $vatRate) {
              $vatRate = $childLine['vatrate'];
            }
            else {
              $vatRate = null;
              break;
            }
          }
        }

        if ($vatRate !== null && $vatRate == $result['vatrate'] && $productPriceEx != 0.0) {
          // Bundle has price info and same vat as ALL children: use price and
          // vat info from bundle line and remove it from child lines to prevent
          // accounting amounts twice.
          foreach ($childLines as &$childLine) {
            $childLine['unitprice'] = 0;
            $childLine['vatrate'] = $result['vatrate'];
          }
        }
        else {
          // All price and vat info is/remains on the child lines.
          // Make sure no price and vat info is left on the bundle line.
          $result['unitprice'] = 0;
          $result['vatrate'] = -1;
        }
      }
    }

    $result = array_merge(array($result), $childLines);
    return $result;
  }

  /**
   * Returns 1 item line for 1 credit line.
   *
   * @param Mage_Sales_Model_Order_Creditmemo_Item $item
   *
   * @return array
   */
  protected function getItemLineCreditNote(Mage_Sales_Model_Order_Creditmemo_Item $item) {
    $result = array();

    $lineVat = -((float) $item->getTaxAmount() + (float) $item->getHiddenTaxAmount());
    $productPriceEx = -((float) $item->getPrice());
    if ($this->isAmount($productPriceEx)) {
      $result += $this->getVatRangeTags($lineVat / $item->getQty(), $productPriceEx, 0.02, 0.02);
    }
    else {
      // Free products should get a "normal" tax rate. We leave that to the
      // completor to determine.
      $result += array(
        'vatrate' => NULL,
        'meta-vatrate-source' => static::VatRateSource_Completor,
      );
    }

    // On a credit note we only have single lines, no compound lines.
    $this->addIfNotEmpty($result, 'itemnumber', $item->getSku());
    $result += array(
      'product' => $item->getName(),
      'unitprice' => $productPriceEx,
      'meta-linevatamount' => $lineVat,
      'quantity' => $item->getQty(),
    );
    if ($this->isAmount($item->getDiscountAmount())) {
      // Credit note: discounts are cancelled, thus amount is positive.
      $result['meta-linediscountamountinc'] = $item->getDiscountAmount();
    }
    if ($this->productPricesIncludeTax()) {
      $productPriceInc = -((float) $item->getPriceInclTax());
      $result['unitpriceinc'] = $productPriceInc;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getShippingLine() {
    $result = array();

    // What do the following methods return:
    // - getShippingAmount():         shipping costs excl VAT excl any discount
    // - getShippingInclTax():        shipping costs incl VAT excl any discount
    // - getShippingTaxAmount():      VAT on shipping costs incl discount
    // - getShippingDiscountAmount(): discount on shipping incl VAT
    $magentoSource = $this->source->getSource();
    if ($this->isAmount($magentoSource->getShippingAmount())) {
      // We have 2 ways of calculating the vat rate: first one is based on tax
      // amount and normal shipping costs corrected with any discount (as the
      // tax amount is including any discount):
      // $vatRate1 = $magentoSource->getShippingTaxAmount() / ($magentoSource->getShippingInclTax() - $magentoSource->getShippingDiscountAmount() - $magentoSource->getShippingTaxAmount());
      // However, we will use the 2nd way as that seems to be more precise and,
      // thus generally leads to a smaller range:
      // Get range based on normal shipping costs incl and excl VAT.
      $shippingVat = $this->getSign() * $magentoSource->getShippingTaxAmount();
      if ($this->source->getType() === Source::Order && $this->isAmount($magentoSource->getShippingDiscountAmount())) {
        $shippingInc = $this->getSign() * ($magentoSource->getShippingInclTax() - $magentoSource->getShippingDiscountAmount());
        $shippingEx = $shippingInc - $shippingVat;
      }
      else {
        $shippingInc = $this->getSign() * $magentoSource->getShippingInclTax();
        $shippingEx = $this->getSign() * $magentoSource->getShippingAmount();
      }

      // !Magento bug!
      // In credit memos, the ShippingTaxAmount may differ from the difference
      // between the shipping costs including VAT and excluding VAT. As that
      // difference seems to lead to "correct" VAT rates, we use that as the
      // VAT amount.
      if (!$this->floatsAreEqual($shippingVat, $shippingInc - $shippingEx, 0.01)) {
        $result['meta-magento-bug'] = sprintf('ShippingTaxAmount = %f', $shippingVat);
        $shippingVat = $shippingInc - $shippingEx;
      }
      // !End of Magento bug!

      $result += $this->getVatRangeTags($shippingVat, $shippingEx, 0.02, 0.04);
      $result += array(
        'unitprice' => $shippingEx,
        'unitpriceinc' => $shippingInc,
      );
      if ($this->isAmount($magentoSource->getShippingDiscountAmount())) {
        $result['meta-linediscountamountinc'] = $this->getSign() * -$magentoSource->getShippingDiscountAmount();
      }
    }
    // Only add a free shipping line on an order, not on a credit note: free
    // shipping is never refunded...
    else if ($this->source->getType() === Source::Order) {
      // Free shipping should get a "normal" tax rate. We leave that to the
      // completor to determine.
      $result += array(
        'vatrate' => NULL,
        'meta-vatrate-source' => static::VatRateSource_Completor,
        'unitprice' => 0,
      );
    }

    // Include discount in the unit price.
    $shippingDescription = $this->order->getShippingDescription();
    $result += array(
      'itemnumber' => '',
      'product' => !empty($shippingDescription) ? $shippingDescription : $this->t('shipping_costs'),
      'quantity' => 1,
    );

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscountLines() {
    $result = array();
    if ($this->isAmount($this->source->getSource()->getDiscountAmount())) {
      $result = array(
        'itemnumber' => '',
        'product' => $this->getDiscountDescription(),
        'vatrate' => NULL,
        'meta-vatrate-source' => static::VatRateSource_Strategy,
        'meta-strategy-split' => TRUE,
        'quantity' => 1,
      );
      // Product prices incl. VAT => discount amount is also incl. VAT
      if ($this->productPricesIncludeTax()) {
        $result['unitpriceinc'] = $this->getSign() * $this->source->getSource()->getDiscountAmount();
      }
      else {
        $result['unitprice'] = $this->getSign() * $this->source->getSource()->getDiscountAmount();
      }
    }
    return array($result);
  }

  /**
   * {@inheritdoc}
   *
   * This implementation may return a manual line for a credit memo.
   */
  protected function getManualLines() {
    $result = array();

    if (isset($this->creditNote) && $this->isAmount($this->creditNote->getAdjustment())) {
      $line['product'] = $this->t('refund_adjustment');
      $line['unitprice'] = -$this->creditNote->getAdjustment();
      $line['quantity'] = 1;
      $line['vatrate'] = 0;
      $result[] = $line;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * This override returns an empty array: Magento does not know gift wrapping
   * lines.
   */
  protected function getGiftWrappingLine() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaymentFeeLine() {
    $result = array();
    $paymentFeeAmount = $this->getSign() * (float) $this->source->getSource()->getPaymentchargeAmount();
    if ($this->isAmount($paymentFeeAmount)) {
      $paymentFeeTax = $this->getSign() * $this->source->getSource()->getPaymentchargeTaxAmount();
      if ($this->productPricesIncludeTax()) {
        // Product prices incl. VAT => payment charges are also incl. VAT.
        $paymentFeeAmount -= $paymentFeeTax;
      }
      $result = array(
        'itemnumber' => '',
        'product' => $this->t('payment_costs'),
        'unitprice' => $paymentFeeAmount,
        'quantity' => 1,
      ) + $this->getVatRangeTags($paymentFeeTax, $paymentFeeAmount, 0.01, $this->productPricesIncludeTax() ? 0.02 : 0.01);
    }
    return $result;
  }

  /**
   * @return string
   */
  protected function getDiscountDescription() {
    if ($this->order->getDiscountDescription()) {
      $description = $this->t('discount_code') . ' ' . $this->order->getDiscountDescription();
    }
    else if ($this->order->getCouponCode()) {
      $description = $this->t('discount_code') . ' ' . $this->order->getCouponCode();
    }
    else {
      $description = $this->t('discount');
    }
    return $description;
  }

  /**
   * Returns if the prices for the products are entered with or without tax.
   *
   * @return bool
   *   Whether the prices for the products are entered with or without tax.
   */
  protected function productPricesIncludeTax() {
    /** @var Mage_Tax_Model_Config $taxConfig */
    $taxConfig = Mage::getModel('tax/config');
    return $taxConfig->priceIncludesTax();
  }

  /**
   * Returns the sign to use for amounts that are always defined as a positive
   * number, also on credit notes.
   *
   * @return float
   *   1 for orders, -1 for credit notes.
   */
  protected function getSign() {
    return (float) ($this->source->getType() === Source::Order ? 1 : -1);
  }

  /**
   * Calls a method that typically depends on the type of invoice source.
   *
   * @param string $method
   * @param array $args
   *
   * @return mixed|void
   */
  protected function callTypeSpecificMethod($method, $args = array()) {
    $method .= $this->source->getType();
    return call_user_func_array(array($this, $method), $args);
  }

}
