<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Collectors;

use ArrayObject;
use Siel\Acumulus\Collectors\LineCollector as BaseLineCollector;
use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Invoice\Source;

/**
 * LineCollector contains OpenCart common Line collecting logic.
 */
class LineCollector extends BaseLineCollector
{
    /**
     * Precision of amounts stored in OC. In OC you enter prices inc vat. The
     * price ex vat and vat amount will be calculated and stored with 4
     * digits precision. So 0.001 is on the pessimistic side.
     */
    protected float $precision = 0.001;
    private array $order;

    protected function collectBefore(
        AcumulusObject $acumulusObject,
        PropertySources $propertySources,
        ArrayObject $fieldSpecifications
    ): void {
        parent::collectBefore($acumulusObject, $propertySources, $fieldSpecifications);
        $this->order = $propertySources->get('source')->getShopObject();
    }

    /**
     * Collects a total line based on an order_total record.
     *
     * Note that amounts on 'voucher' and 'coupon' lines are negative but these lines
     * still need some special handling.
     *
     * @param array $totalLine
     *   The total line record.
     * @param string $vat
     *   One of the {@see \Siel\Acumulus\OpenCart\Invoice\Source}::Vat_... constants.
     *
     * @throws \Exception
     */
    protected function collectTotalLine(Line $line, array $totalLine, string $vat): void
    {
        $line->product = $totalLine['title'];
        $line->quantity = 1;
        if ($vat === Source::Vat_Excluded) {
            $line->unitPrice = $totalLine['value'];
        } else {
            $line->metadataSet(Meta::UnitPriceInc, $totalLine['value']);
        }

        if ($totalLine['code'] === 'voucher') {
            // A voucher is to be seen as a partial payment, thus no tax.
            $line->vatRate = -1;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Exact0);
        } elseif ($totalLine['code'] === 'coupon') {
            // Coupons may have to be split over various taxes.
            $line->vatRate = null;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Strategy);
            $line->metadataSet(Meta::StrategySplit, true);
        } else {
            // Try to get a vat rate.
            $this->addVatRateLookupByTotalLineType($line, $totalLine['code']);
            // The completor will add the vat rate based on the looked up vat rate, on just
            // the highest appearing vat rate, or wil pass it to the strategy phase.
            $line->vatRate = null;
            $line->metadataSet(Meta::VatRateSource, VatRateSource::Completor);
            $line->metadataSet(Meta::StrategySplit, false);
        }
    }

    /**
     * Adds vat class and vat rate metadata to $line.
     *
     * The following metadata may be added:
     * - Meta::VatClassId: int
     * - Meta::VatClassName: string
     * - Meta::VatRateLookup: float[]
     * - Meta::VatRateLookupLabel: string[]
     *
     * @throws \Exception
     */
    protected function addVatRateLookupMetadata(Line $line, ?int $taxClassId): void
    {
        $order = $this->order;

        if ($taxClassId > 0) {
            $taxClass = $this->getTaxClass($taxClassId);
            if ($taxClass) {
                $line->metadataSet(Meta::VatClassId, $taxClass['tax_class_id']);
                $line->metadataSet(Meta::VatClassName, $taxClass['title']);
                $line->metadataAdd(Meta::VatRateLookup, null, true);
                $line->metadataAdd(Meta::VatRateLookupLabel, null, true);

                $taxRules = $this->getTaxRules($taxClassId);
                foreach ($taxRules as $taxRule) {
                    $taxRate = $this->getTaxRate((int) $taxRule['tax_rate_id']);
                    if (!empty($taxRate)
                        && $this->isAddressInGeoZone($order, $taxRule['based'], (int) $taxRate['geo_zone_id'])
                    ) {
                        $line->metadataAdd(Meta::VatRateLookup, (float) $taxRate['rate']);
                        $line->metadataAdd(Meta::VatRateLookupLabel, $taxRate['name']);
                    }
                }
            }
        } else {
            $line->metadataSet(Meta::VatClassId, Config::VatClass_Null);
        }
    }

    /**
     * Tries to lookup and return vat rate metadata for the given line type.
     * This is quite hard. The total line (table order_total) contains a code
     * (= line type) and title field, the latter being a translated and possibly
     * formatted descriptive string of the shipping or handling method applied,
     * e.g. Europa  (Weight: 3.00kg). It is (almost) impossible to trace this
     * back to a shipping or handling method. So instead we retrieve all tax
     * class ids for the given type, collect all tax rates for those, and hope
     * that this results in only 1 tax rate. If tax rates wee found, we add
     * metadata for the first result.
     *
     * @param Line $line
     *   The line to add vat rate lookup metadata to.
     * @param string $code
     *   The total line type: shipping, handling, low_order_fee, .....
     *
     * @throws \Exception
     */
    protected function addVatRateLookupByTotalLineType(Line $line, string $code): void
    {
        $query = $this->getTotalLineTaxClassLookupQuery($code);
        $queryResult = $this->getDb()->query($query);
        if ($queryResult->num_rows === 1) {
            $taxClassId = (int) reset($queryResult->row);
            $this->addVatRateLookupMetadata($line, $taxClassId);
        }
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
     * Returns the query to get the tax class id for a given total type.
     * In OC3 the tax class ids for total lines are either stored under:
     * - key = 'total_{$code}_tax_class_id', e.g. total_handling_tax_class_id or
     *   total_low_order_fee_tax_class_id.
     * - key = '{$code}_{module}_tax_class_id', e.g. shipping_flat_tax_class_id
     *   or shipping_weight_tax_class_id.
     * @todo: What about OC4?
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
        return "select distinct `value` from {$prefix}setting where `key` = 'total_{$code}_tax_class_id' or `key` like '{$code}_%_tax_class_id'";
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
