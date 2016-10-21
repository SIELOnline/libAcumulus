<?php
namespace Siel\Acumulus\OpenCart\Invoice;

use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\ConfigInterface;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Allows to create arrays in the Acumulus invoice structure from an OpenCart
 * order.
 */
class Creator extends BaseCreator
{
    // More specifically typed property.
    /** @var array */
    protected $order;

    /** @var array[] List of OpenCart order total records. */
    protected $orderTotalLines;

    /**
     * {@inheritdoc}
     *
     * This override also initializes WooCommerce specific properties related to
     * the source.
     */
    protected function setInvoiceSource($invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);

        // Load some models and properties we are going to use.
        Registry::getInstance()->load->model('catalog/product');
        $this->orderTotalLines = null;

        switch ($this->invoiceSource->getType()) {
            case Source::Order:
                $this->order = $this->invoiceSource->getSource();
                break;
            case Source::CreditNote:
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getCustomer()
    {
        $order = $this->order;
        $result = array();

        $this->addIfSetAndNotEmpty($result, 'contactyourid', $order, 'customer_id');
        $this->addEmpty($result, 'companyname1', $order['payment_company']);
        if (!empty($result['companyname1'])) {
            // @todo: Are there VAT number extensions?
            $this->addIfSetAndNotEmpty($result, 'vatnumber', $order, 'payment_tax_id');
        }
        $result['fullname'] = $order['firstname'] . ' ' . $order['lastname'];
        $this->addEmpty($result, 'address1', $order['payment_address_1']);
        $this->addEmpty($result, 'address2', $order['payment_address_2']);
        $this->addEmpty($result, 'postalcode', $order['payment_postcode']);
        $this->addEmpty($result, 'city', $order['payment_city']);
        if (!empty($order['payment_iso_code_2'])) {
            $result['countrycode'] = $order['payment_iso_code_2'];
        }
        $this->addIfSetAndNotEmpty($result, 'country', $order, 'payment_country');
        $this->addIfSetAndNotEmpty($result, 'telephone', $order, 'telephone');
        $this->addIfSetAndNotEmpty($result, 'fax', $order, 'fax');
        $result['email'] = $order['email'];

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceNumber($invoiceNumberSource)
    {
        $result = $this->invoiceSource->getReference();
        if ($invoiceNumberSource == ConfigInterface::InvoiceNrSource_ShopInvoice && !empty($this->order['invoice_no'])) {
            $result = $this->order['invoice_prefix'] . $this->order['invoice_no'];
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceDate($dateToUse)
    {
        $result = substr($this->order['date_added'], 0, strlen('2000-01-01'));
        // There doesn't seem to be an invoice date: stick with order create date,
        // also when $dateToUse === ConfigInterface::InvoiceDate_InvoiceCreate.
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the code of the selected payment method.
     */
    protected function getPaymentMethod()
    {
        if (isset($this->order['payment_code'])) {
            return $this->order['payment_code'];
        }
        return parent::getPaymentMethod();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentState()
    {
        // @todo: Can we determine this based on payment_code?
        $result = ConfigInterface::PaymentStatus_Paid;
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getPaymentDate()
    {
        // @todo: Can we determine this based on history (and optionally payment_code)?
        // Will default to the issue date.
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * This override provides the values meta-invoice-amountinc,
     * meta-invoice-vatamount and a vat breakdown in meta-invoice-vat.
     */
    protected function getInvoiceTotals()
    {
        $result = array(
            'meta-invoice-amountinc' => $this->order['total'],
            'meta-invoice-vatamount' => 0.0,
            'meta-invoice-vat' => array(),
        );

        $orderTotals = $this->getOrderTotalLines();
        foreach ($orderTotals as $totalLine) {
            if ($totalLine['code'] === 'tax') {
                $result['meta-invoice-vat'][] = $totalLine['title'] . ': ' . $totalLine['value'];
                $result['meta-invoice-vatamount'] += $totalLine['value'];
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getInvoiceLines()
    {
        $itemLines = $this->getItemLines();
        $itemLines = $this->addLineType($itemLines, static::LineType_Order);

        $totalLines = $this->getTotalLines();

        $result = array_merge($itemLines, $totalLines);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemLines()
    {
        $result = array();

        $orderProducts = $this->getOrderModel()->getOrderProducts($this->invoiceSource->getId());
        foreach ($orderProducts as $line) {
            $result[] = $this->getItemLine($line);
        }

        return $result;
    }

    /**
     * Returns the item line for 1 product line.
     *
     * This method may return child lines if there are options/variants.
     * These lines will be informative, their price will be 0.
     *
     * @param array $item
     *
     * @return array
     */
    protected function getItemLine(array $item)
    {
        $result = array();

        $product = Registry::getInstance()->model_catalog_product->getProduct($item['product_id']);
        $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'mpn');
        $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'isbn');
        $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'jan');
        $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'ean');
        $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'upc');
        $this->addIfSetAndNotEmpty($result, 'itemnumber', $product, 'sku');

        // Product name, model, and option(s).
        $result['product'] = $item['name'];
        if (!empty($item['model'])) {
            $result['product'] .= ' (' . $item['model'] . ')';
        }

        $productPriceEx = $item['price'];
        $productVat = $item['tax'];
        $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx);

        // Options (variants).
        $options = $this->getOrderModel()->getOrderOptions($item['order_id'], $item['order_product_id']);
        if (!empty($options)) {
            // Add options as children.
            $result[Creator::Line_Children] = array();
            $optionsVatInfo = $vatInfo;
            $optionsVatInfo['vatamount'] = 0;
            foreach ($options as $option) {
                $result[Creator::Line_Children][] = array(
                    'product' => "{$option['name']}: {$option['value']}",
                    'unitprice' => 0,
                      // Table order_option does not have a quantity field, so
                      // composite products with multiple same sub product
                      // are apparently not covered.
                    'quantity' => 1,
                  ) + $optionsVatInfo;
            }
        }
        $result['unitprice'] = $productPriceEx;
        $result['quantity'] = $item['quantity'];
        $result += $vatInfo;
        $result['vatamount'] = $productVat;

        return $result;
    }

    /**
     *
     *
     *
     * @return array[]
     *
     */
    protected function getTotalLines()
    {
        $result = array();

        $totalLines = $this->getOrderTotalLines();
        foreach ($totalLines as $totalLine) {
            switch ($totalLine['code']) {
                case 'sub_total':
                    // Sub total of all product lines: ignore.
                    $line = null;
                    break;
                case 'shipping':
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Shipping;
                    break;
                case 'coupon':
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Discount;
                    break;
                case 'tax':
                    // Tax line: added to invoice level
                    $line = null;
                    break;
                case 'voucher':
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Voucher;
                    break;
                case 'total':
                    // Overall total: ignore.
                    $line = null;
                    break;
                default:
                    $line = $this->getTotalLine($totalLine);
                    $line['meta-line-type'] = static::LineType_Other;
                    break;
            }
            if ($line) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * Returns a line based on a "order total line".
     *
     * @param array $line
     *
     * @return array
     */
    protected function getTotalLine(array $line)
    {
        $result = array(
            'product' => $line['title'],
            // Let's hope that this is the value ex vat...
            'unitprice' => $line['value'],
            'quantity' => 1,
        );

        if ($line['code'] === 'voucher') {
            // A voucher is to be seen as a partial payment, thus no tax.
            $result += array(
                'vatrate' => -1,
                'meta-vatrate-source' => Creator::VatRateSource_Exact0,
            );
        } elseif (Number::isZero($line['value'])) {
            // 0-cost lines - e.g. free shipping - also don't have a tax amount,
            // let the completor add the highest appearing vat rate.
            $result += array(
                'vatrate' => null,
                'meta-vatrate-source' => Creator::VatRateSource_Completor,
            );
        } else {
            // Other lines do not have a discoverable vatrate, let a strategy try to
            // compute it.
            $result += array(
                'vatrate' => null,
                'meta-vatrate-source' => Creator::VatRateSource_Strategy,
                // Coupons may have to be split over various taxes, but shipping and
                // other fees not.
                'meta-strategy-split' => $line['code'] === 'coupon',
            );
        }

        return $result;
    }

    /**
     * Returns a list of OpenCart order total records. These are shipment,
     * other fee, tax, and discount lines.
     *
     * @return array[]
     */
    protected function getOrderTotalLines()
    {
        if (!$this->orderTotalLines) {
            $orderModel = $this->getOrderModel();
            $this->orderTotalLines = $orderModel->getOrderTotals($this->order['order_id']);
        }
        return $this->orderTotalLines;
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @return \ModelAccountOrder|\ModelSaleOrder
     */
    protected function getOrderModel()
    {
        return Registry::getInstance()->getOrderModel();
    }
}
