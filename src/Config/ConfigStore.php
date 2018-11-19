<?php
namespace Siel\Acumulus\Config;

/**
 * This class is the bridge between the library and the config subsystem.
 *
 * All CMS and webshop software offers some way of storing settings and
 * libAcumulus uses this configuration subsystem of the host environment it is
 * running in to store its own settings.
 *
 * Only values that are set and that differ from the default value are stored.
 * As all settings are queried at once, it makes sense to store all libAcumulus
 * settings in 1 record. Therefore, it is assumed that overriding classes that
 * implement the load() and save() methods, {@see serialize()} and
 * {@see unserialize()} the array and store it in 1 string setting. If the
 * configuration subsystem already supports storing an array in 1 setting, do
 * not (un)serialize yourself.
 */
abstract class ConfigStore
{
    /**
     * Name of the config key.
     *
     * @var string
     */
    protected $configKey = 'acumulus';

    /**
     * Loads the configuration from the actual configuration provider.
     *
     * @return array
     *   An array with any stored configuration values keyed by their name.
     */
    abstract public function load();

    /**
     * Stores the values to the actual configuration provider.
     *
     * @param array $values
     *   A keyed array that contains the values to store. Not set and default
     *   values are already removed from this array, so store as passed.
     *
     * @return bool
     *   Success.
     */
    abstract public function save(array $values);
}
