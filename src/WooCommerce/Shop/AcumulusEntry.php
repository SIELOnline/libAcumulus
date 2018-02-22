<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

/**
 * Implements the WooCommerce/WordPress specific acumulus entry model class.
 *
 * In WordPress this data is stored as metadata. As such, the "records" returned
 * here are an array of all metadata values, thus not filtered by Acumulus keys.
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    static public $keyEntryId = '_acumulus_entry_id';
    static public $keyToken = '_acumulus_token';
    // Note: these 2 meta keys are not actually stored as the post_id and
    // post_type give us that information.
    static public $keySourceId = '_acumulus_id';
    static public $keySourceType = '_acumulus_type';
    static public $keyCreated = '_acumulus_created';
    static public $keyUpdated = '_acumulus_updated';

    /**
     * @inheritDoc
     */
    protected function get($field)
    {
        $result = parent::get($field);
        if (is_array($result)) {
            $result = reset($result);
        }
        return $result;
    }
}
