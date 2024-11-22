<?php

declare(strict_types=1);

namespace Siel\Acumulus\MyWebShop\Config;

use SensitiveParameter;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\Helpers\Util;
use Siel\Acumulus\Meta;

/**
 * Implements the connection to the MyWebShop config component.
 */
class ConfigStore extends BaSeConfigStore
{
    public function load(): array
    {
        // @todo: Access your web shop's or CMS's config and get the Acumulus settings.
        $values = $shopConfig->get($this->configKey);
        // @todo: remove this line if your configuration sub system accepts arrays as value.
        return json_decode($values);
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        // @todo: remove this line if your configuration sub system accepts arrays as value.
        $configValue = json_encode($values, Meta::JsonFlags | JSON_FORCE_OBJECT);
        return $shopConfig->set($this->configKey, $configValue);
    }
}
