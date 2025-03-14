<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection SensitiveParameter is PHP 8.2
 */

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Config;

use Configuration;
use SensitiveParameter;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the PrestaShop config component.
 */
class ConfigStore extends BaSeConfigStore
{
    public function load(): array
    {
        $values = Configuration::get(strtoupper($this->configKey));
        return !empty($values) ? unserialize($values, ['allow_classes' => false]) : [];
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        $serializedValues = serialize($values);
        return Configuration::updateValue(strtoupper($this->configKey), $serializedValues);
    }
}
