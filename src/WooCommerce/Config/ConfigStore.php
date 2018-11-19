<?php
namespace Siel\Acumulus\WooCommerce\Config;

use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the WordPress config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $values = get_option('acumulus');
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        // WP: update_option() also returns false when there are no changes. We
        // want to return true, so we perform the same check as update_option()
        // before calling update_option().
        $oldValues = get_option('acumulus');
        if ($values === $oldValues || maybe_serialize($values) === maybe_serialize($oldValues)) {
          return true;
        }
        return update_option('acumulus', $values);
    }
}
