<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;

use function is_array;

/**
 * Implements the WooCommerce/WordPress specific acumulus entry model class.
 *
 * In WordPress this data is stored as metadata. As such, the "records" returned
 * here are an array of all metadata values, thus not filtered by Acumulus keys.
 *
 * Note: our keys start with an underscore '_' as they should not be shown on
 * the post page, following WP guidelines:
 * https://developer.wordpress.org/plugins/metadata/managing-post-metadata/#hidden-custom-fields
 */
class AcumulusEntry extends BaseAcumulusEntry
{
    public static string $keyEntryId = '_acumulus_entry_id';
    public static string $keyToken = '_acumulus_token';
    /**
     * Note: these 2 meta keys are not actually stored as the post_id and
     * post_type give us that information.
     */
    public static string $keySourceId = '_acumulus_id';
    public static string $keySourceType = '_acumulus_type';
    public static string $keyCreated = '_acumulus_created';
    public static string $keyUpdated = '_acumulus_updated';

    /**
     * @inheritDoc
     */
    protected function get(string $field)
    {
        $result = parent::get($field);
        if (is_array($result)) {
            $result = reset($result);
        }
        return $result;
    }
}
