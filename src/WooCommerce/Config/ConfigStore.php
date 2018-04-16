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
        $result = update_option('acumulus', $values);
        return $result;
    }
}
