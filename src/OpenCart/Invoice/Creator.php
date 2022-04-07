<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 * @noinspection SqlDialectInspection
 */

namespace Siel\Acumulus\OpenCart\Invoice;

use DB;
use RuntimeException;
use Siel\Acumulus\Invoice\Creator as BaseCreator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;

/**
 * Allows creating arrays in the Acumulus invoice structure from an OpenCart
 * order.
 */
class Creator extends BaseCreator
{
    // More specifically typed property.
    /** @var \Siel\Acumulus\OpenCart\Invoice\Source */
    protected $invoiceSource;

    /** @var array */
    protected $order;

    /**
     * Precision of amounts stored in OC. In OC you enter prices inc vat. The
     * price ex vat and vat amount will be calculated and stored with 4
     * digits precision. So 0.001 is on the pessimistic side.
     *
     * @var float
     */
    protected $precision = 0.001;

    /**
     * {@inheritdoc}
     *
     * This override also initializes WooCommerce specific properties related to
     * the source.
     *
     * @throws \Exception
     */
    protected function setInvoiceSource(\Siel\Acumulus\Invoice\Source $invoiceSource)
    {
        parent::setInvoiceSource($invoiceSource);

        // Load some models and properties we are going to use.
        $this->getRegistry()->load->model('catalog/product');

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
     *
     * @throws \Exception
     */
    protected function getInvoiceLines(): array
    {
        $itemLines = $this->getItemLines();
        $itemLines = $this->addLineType($itemLines, static::LineType_OrderItem);
        $totalLines = $this->getTotalLines();
        return array_merge($itemLines, $totalLines);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    protected function getItemLines(): array
    {
        $result = [];
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
     * @throws \Exception
     */
    protected function getItemLine(array $item): array
    {
        $result = [];

        // $product can be empty if the product has been deleted.
        /** @var \ModelCatalogProduct $model */
        $model = $this->getRegistry()->getModel('Catalog/Product');
        $product = $model->getProduct($item['product_id']);
        if (!empty($product)) {
            $this->addPropertySource('product', $product);
        }
        $this->addPropertySource('item', $item);

        $this->addProductInfo($result);

        // Get vat range info from item line.
        $productPriceEx = $item['price'];
        $productVat = $item['tax'];
        $vatInfo = $this->getVatRangeTags($productVat, $productPriceEx, $this->precision, $this->precision);

        // Try to look up the vat rate via product.
        $vatInfo += $this->getVatRateLookupMetadata($product['tax_class_id']);

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as 'unitprice'.
            // - But still send the VAT rate to Acumulus.
            $result[Tag::UnitPrice] = $productPriceEx + $productVat;
        } else {
            $result[Tag::UnitPrice] = $productPriceEx;
            $result[Meta::VatAmount] = $productVat;
        }
        $result[Tag::Quantity] = $item['quantity'];
        $result += $vatInfo;

        // Options (variants).
        $options = $this->getOrderModel()->getOrderOptions($item['order_id'], $item['order_product_id']);
        if (!empty($options)) {
            // Add options as children.
            $result[Meta::ChildrenLines] = [];
            $optionsVatInfo = $vatInfo;
            $optionsVatInfo[Meta::VatAmount] = 0;
            foreach ($options as $option) {
                $result[Meta::ChildrenLines][] = [
                    Tag::Product => "{$option['name']}: {$option['value']}",
                    Tag::UnitPrice => 0,
                      // Table order_option does not have a quantity field, so
                      // composite products with multiple same sub product
                      // are apparently not covered. Take quantity from parent.
                    Tag::Quantity => $item['quantity'],
                                                 ] + $optionsVatInfo;
            }
        }
        $this->removePropertySource('product');
        $this->removePropertySource('item');

        return $result;
    }

    /**
     * Looks up and returns vat class and vat rate metadata.
     *
     * @param int|null $taxClassId
     *   The tax class to look up.
     *
     * @return array
     *   An empty array or an array with keys:
     *   - Meta::VatClassId: int
     *   - Meta::VatClassName: string
     *   - Meta::VatRateLookup: float[]
     *   - Meta::VatRateLookupLabel: string[]
     *
     * @throws \Exception
     */
    protected function getVatRateLookupMetadata(?int $taxClassId): array
    {
        $result = [];

        if (!empty($taxClassId)) {
            $taxClass = $this->getTaxClass($taxClassId);
            if ($taxClass) {
                $result += [
                    Meta::VatClassId => $taxClass['tax_class_id'],
                    Meta::VatClassName => $taxClass['title'],
                    Meta::VatRateLookup => [],
                    Meta::VatRateLookupLabel => [],
                ];

                $taxRules = $this->getTaxRules($taxClassId);
                foreach ($taxRules as $taxRule) {
                    $taxRate = $this->getTaxRate($taxRule['tax_rate_id']);
                    if (!empty($taxRate)) {
                        if ($this->isAddressInGeoZone($this->order, $taxRule['based'], $taxRate['geo_zone_id'])) {
                            $result[Meta::VatRateLookup][] = $taxRate['rate'];
                            $result[Meta::VatRateLookupLabel][] = $taxRate['name'];
                        }
                    }
                }
            }
        } else {
            $result += [
                Meta::VatClassId => Config::VatClass_Null,
            ];
        }
        return $result;
    }

    /**
     * Returns all total lines: shipping, handling, discount, ...
     *
     * @return array[]
     *   An array of invoice lines.
     *
     * @throws \Exception
     */
    protected function getTotalLines(): array
    {
        $result = [];

        /**
         * @var $totalLines
         *   The set of order total lines for this order. This set is ordered by
         *   sort_order, meaning that lines before the tax line are amounts ex
         *   vat and line after are inc vat.
         */
        $totalLines = $this->invoiceSource->getOrderTotalLines();
        $exVat = true;
        foreach ($totalLines as $totalLine) {
            switch ($totalLine['code']) {
                case 'sub_total':
                    // Subtotal of all product lines: ignore.
                    $line = null;
                    break;
                case 'shipping':
                    $line = $this->getTotalLine($totalLine, $exVat);
                    $line = $this->addLineType($line, static::LineType_Shipping);
                    break;
                case 'coupon':
                    $line = $this->getTotalLine($totalLine, $exVat);
                    $line = $this->addLineType($line, static::LineType_Discount);
                    break;
                case 'tax':
                    // Tax line: added to invoice level
                    $line = null;
                    $exVat = false;
                    break;
                case 'voucher':
                    $line = $this->getTotalLine($totalLine, $exVat);
                    $line = $this->addLineType($line, static::LineType_Voucher);
                    break;
                case 'total':
                    // Overall total: ignore.
                    $line = null;
                    break;
                default:
                    $line = $this->getTotalLine($totalLine, $exVat);
                    $line = $this->addLineType($line, static::LineType_Other);
                    break;
            }
            if ($line) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * Returns a line based on an "order total line".
     *
     * @param array $line
     *   The total line.
     * @param bool $exVat
     *   Whether the value in this line is ex (true) or inc (false) vat.
     *
     * @return array
     *   An Acumulus invoice line.
     * @throws \Exception
     */
    protected function getTotalLine(array $line, bool $exVat): array
    {
        $result = [
            Tag::Product => $line['title'],
            Tag::Quantity => 1,
        ];
        if ($exVat) {
            $result[Tag::UnitPrice] = $line['value'];
        } else {
            $result[Meta::UnitPriceInc] = $line['value'];
        }

        if ($line['code'] === 'voucher') {
            // A voucher is to be seen as a partial payment, thus no tax.
            $result += [
                Tag::VatRate => -1,
                Meta::VatRateSource => Creator::VatRateSource_Exact0,
            ];
        } elseif ($line['code'] === 'coupon') {
            // Coupons may have to be split over various taxes.
            $result += [
                Tag::VatRate => null,
                Meta::VatRateSource => Creator::VatRateSource_Strategy,
                Meta::StrategySplit => $line['code'] === 'coupon',
            ];
        } else {
            // Try to get a vat rate.
            $vatRateLookupMetaData = $this->getVatRateLookupByTotalLineType($line['code']);
            // The completor will add the looked up vat rate based on looked up
            // or just the highest appearing vat rate, or wil pass it to the
            // strategy phase.
            $result += [
                    Tag::VatRate => null,
                    Meta::VatRateSource => Creator::VatRateSource_Completor,
                    Meta::StrategySplit => false,
                ] + $vatRateLookupMetaData;
        }

        return $result;
    }

    /**
     * Tries to lookup and return vat rate metadata for the given line type.
     * This is quite hard. The total line (table order_total) contains a code
     * (= line type) and title field, the latter being a translated and possibly
     * formatted descriptive string of the shipping or handling method applied,
     * e.g. Europa  (Weight: 3.00kg). It is (almost) impossible to trace this
     * back to a shipping or handling method. So instead we retrieve all tax
     * class ids for the given type, collect all tax rates for those, and hope
     * that this results in only 1 tax rate.
     *
     * @param string $code
     *   The total line type: shipping, handling, low_order_fee, .....
     *
     * @return array
     *   A, possibly empty, array with vat rate lookup metadata. Empty if no OR
     *   multiple tax rates were found.
     *
     * @throws \Exception
     */
    protected function getVatRateLookupByTotalLineType(string $code): array
    {
        $result = [];
        $query = $this->getTotalLineTaxClassLookupQuery($code);
        $queryResult = $this->getDb()->query($query);
        if (!empty($queryResult->row)) {
            $taxClassId = reset($queryResult->row);
            $result = $this->getVatRateLookupMetadata($taxClassId);
        }
        return $result;
    }

    /**
     * Returns whether the address of the order lies within the geo zone.
     *
     * @param array $order
     *   The order.
     * @param string $addressType
     *   'payment' or 'shipping'.
     * @param int $geoZoneId
     *   The id of the geo zone.
     *
     * @return bool
     *   True if the address of the order lies within the geo zone, false
     *   otherwise.
     *
     * @throws \Exception
     */
    protected function isAddressInGeoZone(array $order, string $addressType, int $geoZoneId): bool
    {
        $fallbackAddressType = $addressType === 'payment' ? 'shipping' : 'payment';
        if (!empty($order["{$addressType}_country_id"])) {
            $countryId = $order["{$addressType}_country_id"];
            $zoneId = !empty($order["{$addressType}_zone_id"]) ? $order["{$addressType}_zone_id"] : 0;
        } elseif (!empty($order["{$fallbackAddressType}_country_id"])) {
            $countryId = $order["{$fallbackAddressType}_country_id"];
            $zoneId = !empty($order["{$fallbackAddressType}_zone_id"]) ? $order["{$fallbackAddressType}_zone_id"] : 0;
        } else {
            $countryId = 0;
            $zoneId = 0;
        }

        $zones = $this->getZoneToGeoZones($geoZoneId);
        foreach ($zones as $zone) {
            // Check if this zone definition covers the same country.
            if ($zone['country_id'] == $countryId) {
                // Check if the zone definition covers the whole country or if
                // they are equal.
                if ($zone['zone_id'] == 0 || $zone['zone_id'] == $zoneId) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns the query to get the tax class id for a given total type.
     * In OC3 the tax class ids for total lines are either stored under:
     * - key = 'total_{$code}_tax_class_id', e.g. total_handling_tax_class_id or
     *   total_low_order_fee_tax_class_id.
     * - key = '{$code}_{module}_tax_class_id', e.g. shipping_flat_tax_class_id
     *   or shipping_weight_tax_class_id.
     *
     * @param string $code
     *   The type of total line, e.g. shipping, handling or low_order_fee.
     *
     * @return string
     *   The query to execute.
     */
    protected function getTotalLineTaxClassLookupQuery(string $code): string
    {
        $prefix = DB_PREFIX;
        $code = $this->getDb()->escape($code);
        return "SELECT `value` FROM {$prefix}setting where `key` = 'total_{$code}_tax_class_id' OR `key` LIKE '{$code}_%_tax_class_id'";
    }

    /**
     * {@inheritdoc}
     */
    protected function getShippingLine(): array
    {
        throw new RuntimeException(__METHOD__ . ' should never be called');
    }

    /**
     * Copy of ModelLocalisationTaxClass::getTaxClass().
     * This model cannot be used on the catalog side, so I just copied the code.
     *
     * @param int $tax_class_id
     *
     * @return array
     *   The tax class record for the given $tax_class_id.
     *
     * @throws \Exception
     */
    protected function getTaxClass(int $tax_class_id): array
    {
        /** @var \stdClass $query  (documentation error in DB) */
        $query = $this->getDb()->query("SELECT * FROM " . DB_PREFIX . "tax_class WHERE tax_class_id = '" . $tax_class_id . "'");
        return $query->row;
    }

    /**
     * Copy of ModelLocalisationTaxClass::getTaxRules().
     * This model cannot be used on the catalog side, so I just copied the code.
     *
     * @param int $tax_class_id
     *
     * @return array[]
     *   A list of tax rules belonging to the given $tax_class_id.
     *
     * @throws \Exception
     */
    protected function getTaxRules(int $tax_class_id): array
    {
        /** @var \stdClass $query  (documentation error in DB) */
        $query = $this->getDb()->query("SELECT * FROM " . DB_PREFIX . "tax_rule WHERE tax_class_id = '" . $tax_class_id . "'");
        return $query->rows;
    }

    /**
     * Copy of ModelLocalisationTaxRate::getTaxRate().
     * This model cannot be used on the catalog side, so I just copied the code.
     *
     * @param int $tax_rate_id
     *
     * @return array
     *   The tax rate record for the given $tax_rate_id.
     *
     * @throws \Exception
     */
    protected function getTaxRate(int $tax_rate_id): array
    {
        /** @var \stdClass $query  (documentation error in DB) */
        $query = $this->getDb()->query("SELECT tr.tax_rate_id, tr.name AS name, tr.rate, tr.type, tr.geo_zone_id,
            gz.name AS geo_zone, tr.date_added, tr.date_modified
            FROM " . DB_PREFIX . "tax_rate tr
            LEFT JOIN " . DB_PREFIX . "geo_zone gz ON (tr.geo_zone_id = gz.geo_zone_id)
            WHERE tr.tax_rate_id = '" . $tax_rate_id . "'");
        return $query->row;
    }

    /**
     * Copy of \ModelLocalisationGeoZone::getZoneToGeoZones().
     * This model cannot be used on the catalog side, so I just copied the code.
     *
     * @param int $geo_zone_id
     *
     * @return array[]
     *   A List of zone_to_geo_zone records for the given $geo_geo_zone_id.
     *
     * @throws \Exception
     */
    protected function getZoneToGeoZones(int $geo_zone_id): array
    {
        static $geoZonesCache = [];

        if (!isset($geoZonesCache[$geo_zone_id])) {
            /** @var \stdClass $query  (documentation error in DB) */
            $query = $this->getDb()->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . $geo_zone_id . "'");
            $geoZonesCache[$geo_zone_id] = $query->rows;
        }
        return $geoZonesCache[$geo_zone_id];
    }

    /**
     * @return \ModelCheckoutOrder|\ModelSaleOrder
     */
    protected function getOrderModel()
    {
        return $this->getRegistry()->getOrderModel();
    }

    /**
     * @return \DB
     */
    protected function getDb(): DB
    {
        return $this->getRegistry()->db;
    }

    /**
     * Wrapper method that returns the OpenCart registry class.
     */
    protected function getRegistry(): Registry
    {
        return Registry::getInstance();
    }
}
