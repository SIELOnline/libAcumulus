<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the WordPress config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function load(array $keys)
    {
        $result = array();
        $configurationValues = get_option('acumulus');
        if (is_array($configurationValues)) {
            foreach ($keys as $key) {
                // Do not overwrite defaults if no value is set.
                if (isset($configurationValues[$key])) {
                    $result[$key] = $configurationValues[$key];
                }
            }
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        $result = true;
        // With 2 forms for the settings, not all settings will be saved at the
        // same moment.
        // - Read all currently stored settings.
        // - Overwrite existing settings.
        // - Add settings that had not yet a value.
        // - Remove settings that do not (or no longer) have a custom value.
        $defaults = $this->acumulusConfig->getDefaults();
        $configurationValues = get_option('acumulus');
        $oldConfigurationValues = $configurationValues;
        foreach ($values as $key => $value) {
            if ((isset($defaults[$key]) && $defaults[$key] === $value) || $value === null) {
                unset($configurationValues[$key]);
            } else {
                $configurationValues[$key] = $value;
            }
        }

        // Prevent error message when there are no changes:
        if ($oldConfigurationValues != $configurationValues) {
          $result = update_option('acumulus', $configurationValues);
        }
        return $result;
    }
}
