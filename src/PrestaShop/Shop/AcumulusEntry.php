<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the PrestaShop specific acumulus entry model class.
 *
 * In WooCommerce this data is stored as metadata. As such, the "records"
 * returned here are an array of all metadata values, thus not filtered by
 * Acumulus keys.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    static protected $keyEntryId = 'id_entry';
}
