<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection SensitiveParameter is PHP 8.2
 * @noinspection PhpMultipleClassDeclarationsInspection OC3 has many double class definitions
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Config;

use SensitiveParameter;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Implements the connection to the OpenCart config component.
 */
class ConfigStore extends BaSeConfigStore
{
    public static string $configCode = 'acumulus_siel';

    public function load(): array
    {
        $values = $this->getSettings()->getSetting(self::$configCode);
        return $values[self::$configCode . '_' . $this->configKey] ?? [];
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        $modelSettingSetting = $this->getSettings();
        $setting = $modelSettingSetting->getSetting(self::$configCode);
        $setting[self::$configCode . '_' . $this->configKey] = $values;
        $modelSettingSetting->editSetting(self::$configCode, $setting);
        return true;
    }

    /**
     * @return \Opencart\Admin\Model\Setting\Setting|\Opencart\Catalog\Model\Setting\Setting|\ModelSettingSetting
     *
     * @noinspection PhpMissingReturnTypeInspection
     *   Actually a {@see Proxy} is returned that proxies (one of) the setting model(s).
     *   So for us, the type is a Setting.
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function getSettings()
    {
        return Registry::getInstance()->getModel('setting/setting');
    }
}
