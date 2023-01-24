<?php
/**
 * @noinspection DuplicatedCode  Remove when extracting code common for OC3 and OC4
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Config;

use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\OpenCart\OpenCart4\Helpers\Registry;

/**
 * Implements the connection to the OpenCart config component.
 */
class ConfigStore extends BaSeConfigStore
{
    protected string $configCode = 'acumulus_siel';

    /**
     * {@inheritdoc}
     */
    public function load(): array
    {
        $values = $this->getSettings()->getSetting($this->configCode);
        return $values[$this->configCode . '_' . $this->configKey] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values): bool
    {
        $modelSettingSetting = $this->getSettings();
        $setting = $modelSettingSetting->getSetting($this->configCode);
        $setting[$this->configCode . '_' . $this->configKey] = $values;
        $modelSettingSetting->editSetting($this->configCode, $setting);
        return true;
    }

    /**
     * @return \Opencart\Admin\Model\Setting\Setting|\Opencart\Catalog\Model\Setting\Setting
     *
     * @noinspection PhpMissingReturnTypeInspection : actually a {@see Proxy} is
     *   returned that proxies (one of) the setting model(s). So for us, the
     *   type is a Setting.
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpReturnDocTypeMismatchInspection
     */
    protected function getSettings()
    {
        return Registry::getInstance()->getModel('setting/setting');
    }
}
