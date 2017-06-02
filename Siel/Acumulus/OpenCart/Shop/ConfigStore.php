<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the OpenCart config component.
 */
class ConfigStore extends BaSeConfigStore
{
    const CONFIG_KEY = 'ACUMULUS_';

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @return \ModelSettingSetting
     */
    protected function getSettings()
    {
        Registry::getInstance()->load->model('setting/setting');
        return Registry::getInstance()->model_setting_setting;
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $keys)
    {
        $result = array();
        // Load the values from the web shop specific configuration.
        $configurationValues = $this->getSettings()->getSetting('acumulus_siel');
        $configurationValues = isset($configurationValues['acumulus_siel_module']) ? $configurationValues['acumulus_siel_module'] : array();
        foreach ($keys as $key) {
            if (array_key_exists($key, $configurationValues)) {
                $result[$key] = $configurationValues[$key];
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        // With 2 forms for the settings, not all settings will be saved at the
        // same moment.
        // - Read all currently stored settings.
        // - Overwrite existing settings.
        // - Add settings that had not yet a value.
        // - Remove settings that do no longer have a custom value.
        $setting = $this->getSettings()->getSetting('acumulus_siel');
        if (!isset($setting['acumulus_siel_module'])) {
            $setting['acumulus_siel_module'] = array();
        }

        $defaults = $this->acumulusConfig->getDefaults();
        foreach ($values as $key => $value) {
            if ((isset($defaults[$key]) && $defaults[$key] === $value) || $value === null) {
                unset($setting['acumulus_siel_module'][$key]);
            } else {
                $setting['acumulus_siel_module'][$key] = $value;
            }
        }
        $this->getSettings()->editSetting('acumulus_siel', $setting);
        return true;
    }
}
