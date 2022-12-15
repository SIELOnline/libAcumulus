<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Config;

use Configuration;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the PrestaShop config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function load(): array
    {
        $values = Configuration::get(strtoupper($this->configKey));
        return unserialize($values);
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values): bool
    {
        $values = serialize($values);
        return Configuration::updateValue(strtoupper($this->configKey), $values);
    }
}
