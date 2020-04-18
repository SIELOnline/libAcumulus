<?php
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
    public function load()
    {
        $values = $this->getSettings()->getSetting($this->configCode);
        $values = isset($values[$this->configCode . '_' . $this->configKey]) ? $values[$this->configCode . '_' . $this->configKey] : array();
        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        $modelSettingSetting = $this->getSettings();
        $setting = $modelSettingSetting->getSetting($this->configCode);
        $setting[$this->configCode . '_' . $this->configKey] = $values;
        $modelSettingSetting->editSetting($this->configCode, $setting);
        return true;
    }

    /** @noinspection PhpDocMissingThrowsInspection */
    /**
     * @return \ModelSettingSetting
     */
    protected function getSettings()
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        Registry::getInstance()->load->model('setting/setting');
        return Registry::getInstance()->model_setting_setting;
    }

    /**
     * @deprecated Only still here for use during update.
     *
     * @param array $keys
     *
     * @return array
     */
    public function loadOld(array $keys)
    {
        $result = array();
        // Load the values from the web shop specific configuration.
        $configurationValues = $this->getSettings()->getSetting($this->configCode);
        $values = isset($configurationValues['acumulus_siel_module']) ? $configurationValues['acumulus_siel_module'] : array();
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                $result[$key] = $values[$key];
            }
        }

        // Delete the value, this will only be used one more time: during
        // updating to 5.4.0.
        unset($configurationValues['acumulus_siel_module']);
        $this->getSettings()->editSetting('acumulus_siel', $configurationValues);

        return $result;
    }
}
