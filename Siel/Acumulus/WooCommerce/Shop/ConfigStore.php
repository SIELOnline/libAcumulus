<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the WordPress config component.
 *
 * @todo: the save method is not used as WP stores the values itself.
 * It seems to work but all values, including (translated) markup, is stored.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function getShopEnvironment()
    {
        global $wp_version, $woocommerce;
        $environment = array(
            // Lazy load is no longer needed (as in L3) as this method will only be
            // called when the config gets actually queried.
            'moduleVersion' => \Acumulus::create()->getVersionNumber(),
            'shopName' => $this->shopName,
            'shopVersion' => (isset($woocommerce) ? $woocommerce->version : 'unknown') . ' (WordPress: ' . $wp_version . ')',
        );
        return $environment;
    }

    /**
     * {@inheritdoc}
     */
    public function load(array $keys)
    {
        $result = array();
        // Load the values from the web shop specific configuration.
        // WP does add its own magic_quotes (see wp_magic_quotes()), we undo it.
        $configurationValues = stripslashes_deep(get_option('acumulus'));
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
     *
     * @todo: his gets never called.When it gets called: use stripslashes here?!
     */
    public function save(array $values)
    {
        // With 2 forms for the settings, not all settings will be saved at the
        // same moment.
        // - Read all currently stored settings.
        // - Overwrite existing settings.
        // - Add settings that had not yet a value.
        // - Remove settings that do not (or no longer) have a custom value.
        $defaults = $this->acumulusConfig->getDefaults();
        $configurationValues = get_option('acumulus');
        foreach ($values as $key => $value) {
            if ((isset($defaults[$key]) && $defaults[$key] === $value) || $value === null) {
                unset($configurationValues[$key]);
            } else {
                $configurationValues[$key] = $value;
            }
        }
        return update_option('acumulus', $configurationValues);
    }
}
