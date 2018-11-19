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
        // @todo: Access your webhop's or CMS's config and get the Acumulus config
        $values = $shopConfig->get($this->configKey);
        // @todo; remove this line if your configuration sub system accepts arrays as value.
        $result = unserialize($values);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        // @todo; remove this line if your configuration sub system accepts arrays as value.
        $setting = serialize($values);
        // @todo: Access your webhop's or CMS's config and store the Acumulus setting
        $result = $shopConfig->set($this->configKey, $setting);
        return $result;
    }
}
