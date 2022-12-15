<?php
namespace Siel\Acumulus\MyWebShop\Config;

use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\Helpers\Util;

/**
 * Implements the connection to the MyWebShop config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function load(): array
    {
        // @todo: Access your web shop's or CMS's config and get the Acumulus settings.
        $values = $shopConfig->get($this->configKey);
        // @todo: remove this line if your configuration sub system accepts arrays as value.
        return json_decode($values);
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values): bool
    {
        // @todo: remove this line if your configuration sub system accepts arrays as value.
        $configValue = json_encode($values, JSON_FORCE_OBJECT | Util::JsonFlags);
        return $shopConfig->set($this->configKey, $configValue);
    }
}
