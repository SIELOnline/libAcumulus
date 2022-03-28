<?php
namespace Siel\Acumulus\MyWebShop\Config;

use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the MyWebShop config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        // @todo: Access your web shop's or CMS's config and get the Acumulus settings.
        $values = $shopConfig->get($this->configKey);
        // @todo: remove this line if your configuration sub system accepts arrays as value.
        $values = json_decode($values);
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        // @todo: remove this line if your configuration sub system accepts arrays as value.
        $values = json_encode($values);
        return $shopConfig->set($this->configKey, $values);
    }
}
