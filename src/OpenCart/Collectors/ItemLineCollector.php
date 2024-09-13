<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use Siel\Acumulus\Collectors\LineCollector;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * ItemLineCollector contains OpenCart specific {@see LineType::Item} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ItemLineCollector extends LineCollector
{
    /**
     * Precision of amounts stored in OC. In OC you enter prices inc vat. The
     * price ex vat and vat amount will be calculated and stored with 4
     * digits precision. So 0.001 is on the pessimistic side.
     */
    protected float $precision = 0.001;

    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   An item line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->getItemLine($acumulusObject);
    }

    /**
     * Collects the item line for 1 product line.
     *
     * This method may return child lines if there are options/variants.
     * These lines will be informative, their price will be 0.
     *
     * @param Line $line
     *   An item line with the mapped fields filled in
     *
     * @throws \Exception
     */
    protected function getItemLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\OpenCart\Invoice\Item $item */
        $item = $this->getPropertySource('item');
        /** @var array $shopItem */
        $shopItem = $item->getShopObject();
        /** @var \Siel\Acumulus\Invoice\Product $product */
        $product = $item->getProduct();
        /** @var array|null $shopProduct */
        $shopProduct = $product?->getShopObject();

        // Get vat range info from item line.
        $productPriceEx = (float) $shopItem['price'];
        $productVat = (float) $shopItem['tax'];
        self::addVatRangeTags($line, $productVat, $productPriceEx, $this->precision, $this->precision);

        // Try to look up the vat rate via product.
        $vatMetadata = $this->getVatRateLookupMetadata((int) $shopProduct['tax_class_id']);
        $line->metadataSetMultiple($vatMetadata);

        // Check for cost price and margin scheme.
        if (!empty($line['costPrice']) && $this->allowMarginScheme()) {
            // Margin scheme:
            // - Do not put VAT on invoice: send price incl VAT as 'unitprice'.
            // - But still send the VAT rate to Acumulus.
            $line->unitPrice = $productPriceEx + $productVat;
        } else {
            $line->unitPrice = $productPriceEx;
            $line->metadataSet(Meta::VatAmount, $productVat);
        }

        // Options (variants).
        $options = $item->getOrderProductOptions();
        if (!empty($options)) {
            // Add options as children.
            // In the old Creator we added all kinds of vat rate related metadata, but as
            // options do not have a price, this seems unnecessary. Just add the
            // VatRateSource::Parent as Meta::VatRateSource.
//            $result[Meta::ChildrenLines] = [];
//            $vatMetadata[Meta::VatAmount] = 0;
//            $vatMetadata[Meta::VatRateSource] = VatRateSource::Parent;
//            $optionsVatInfo = $vatInfo; // $vatInfo is vat range tags + vat rate lookup metadata
//            $optionsVatInfo[Meta::VatAmount] = 0;
            foreach ($options as $option) {
                /** @var Line $child */
                $child = $this->createAcumulusObject();
                $child->product = "{$option['name']}: {$option['value']}";
                // Table order_option does not have a quantity field, so
                // composite products with multiple same sub product
                // are apparently not covered. Take quantity from parent.
                $child->quantity = $line->quantity;
                $child->unitPrice = 0;
                $child->metadataSet(Meta::VatRateSource, VatRateSource::Parent);
                $line->addChild($child);
            }
        }
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
        $source = $this->getPropertySource('source');
        $order = $source->getOrder()->getShopObject();

        $result = [];

        if ($taxClassId > 0) {
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
                    $taxRate = $this->getTaxRate((int) $taxRule['tax_rate_id']);
                    if (!empty($taxRate)
                        && $this->isAddressInGeoZone($order, $taxRule['based'], (int) $taxRate['geo_zone_id'])
                    ) {
                        $result[Meta::VatRateLookup][] = $taxRate['rate'];
                        $result[Meta::VatRateLookupLabel][] = $taxRate['name'];
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
        /** @var \stdClass $query (documentation error in DB) */
        $query = $this->getDb()->query('SELECT * FROM ' . DB_PREFIX . "tax_class WHERE tax_class_id = '" . $tax_class_id . "'");
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
        /** @var \stdClass $query (documentation error in DB) */
        $query = $this->getDb()->query('SELECT * FROM ' . DB_PREFIX . "tax_rule WHERE tax_class_id = '" . $tax_class_id . "'");
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
        /** @var \stdClass $query (documentation error in DB) */
        $query = $this->getDb()->query(
            'SELECT tr.tax_rate_id, tr.name AS name, tr.rate, tr.type, tr.geo_zone_id,
            gz.name AS geo_zone, tr.date_added, tr.date_modified
            FROM ' . DB_PREFIX . 'tax_rate tr
            LEFT JOIN ' . DB_PREFIX . "geo_zone gz ON (tr.geo_zone_id = gz.geo_zone_id)
            WHERE tr.tax_rate_id = '" . $tax_rate_id . "'"
        );
        return $query->row;
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
            $countryId = (int) $order["{$addressType}_country_id"];
            $zoneId = (int) (!empty($order["{$addressType}_zone_id"]) ? $order["{$addressType}_zone_id"] : 0);
        } elseif (!empty($order["{$fallbackAddressType}_country_id"])) {
            $countryId = (int) $order["{$fallbackAddressType}_country_id"];
            $zoneId = (int) (!empty($order["{$fallbackAddressType}_zone_id"]) ? $order["{$fallbackAddressType}_zone_id"] : 0);
        } else {
            $countryId = 0;
            $zoneId = 0;
        }

        $zones = $this->getZoneToGeoZones($geoZoneId);
        foreach ($zones as $zone) {
            // Check if this zone definition covers the same country.
            if ((int) $zone['country_id'] === $countryId) {
                // Check if the zone definition covers the whole country or if
                // they are equal.
                if ((int) $zone['zone_id'] === 0 || (int) $zone['zone_id'] === $zoneId) {
                    return true;
                }
            }
        }
        return false;
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
            /** @var \stdClass $query (documentation error in DB) */
            $query = $this->getDb()->query('SELECT * FROM ' . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . $geo_zone_id . "'");
            $geoZonesCache[$geo_zone_id] = $query->rows;
        }
        return $geoZonesCache[$geo_zone_id];
    }

    /**
     * Wrapper method to get {@see Registry::$db}.
     *
     * @return \Opencart\System\Library\DB|\DB
     */
    protected function getDb()
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
