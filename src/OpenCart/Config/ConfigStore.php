<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace Siel\Acumulus\OpenCart\Config;

use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Implements the connection to the OpenCart config component.
 */
class ConfigStore extends BaSeConfigStore
{
    protected $configCode = 'acumulus_siel';

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
     * @return \ModelSettingSetting
     *
     * @noinspection PhpDocMissingThrowsInspection
     * @noinspection PhpMissingReturnTypeInspection : actually a {@see Proxy} is
     *   returned that proxies a {@see \ModelSettingSetting}. So for us, the
     *   type is a \ModelSettingSetting.
     */
    protected function getSettings()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Registry::getInstance()->load->model('setting/setting');
        return Registry::getInstance()->model_setting_setting;
    }
}
