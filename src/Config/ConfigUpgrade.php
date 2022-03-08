<?php
/**
 * Note: As long as we want to check for a minimal PHP version via the
 * Requirements checking process provided by the classes below, we should not
 * use PHP7 language constructs in the following classes:
 * - {@see Container}: creates instances of the below classes.
 * - {@see Requirements}: executes the checks.
 * - {@see \Siel\Acumulus\Config\ConfigUpgrade}: initiates the check.
 * - {@see \Siel\Acumulus\Helpers\Severity}: part of a failed check.
 * - {@see \Siel\Acumulus\Helpers\Message}: represents a failed check.
 * - {@see \Siel\Acumulus\Helpers\MessageCollection}: represents failed checks.
 * - {@see Log}: Logs failed checks.
 *
 * The PHP7 language constructs we suppress the warnings for:
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 */

namespace Siel\Acumulus\Config;

use RuntimeException;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;

use const Siel\Acumulus\Version;

/**
 * Class ConfigUpgrade contains all upgrades to the config.
 */
class ConfigUpgrade
{
    protected /*Config*/ $config;
    protected /*ConfigStore*/ $configStore;
    protected /*Container*/ $container;
    protected /*Log*/ $log;

    public function __construct(Config $config, ConfigStore $configStore, Container $container, Log $log)
    {
        $this->config = $config;
        $this->configStore = $configStore;
        $this->container = $container;
        $this->log = $log;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getConfigStore(): ConfigStore
    {
        return $this->configStore;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getLog(): Log
    {
        return $this->log;
    }

    /**
     * Upgrade the config to the latest version.
     *
     * This method should only be called when the module just got updated. But
     * as not all host systems offer a specific update moment, it may get called
     * on each (real) initialisation of this module, so we return quick and fast
     * in the situation where the config data is up-to-date.
     *
     * Notes:
     * - $currentVersion can be empty if the host environment cannot deliver
     *   this value (MA2.4). If so, we switch to using a new key 'configVersion'
     *   in the set of config values.
     * - 'configVersion' was introduced in 6.4.1. So when upgrading from an
     *   older version it will not be set and if $currentVersion is also not
     *   passed in, we have to guess it. The 6.0.0 update is not idempotent,
     *   whereas the 6.3.1 update is, so we "guess" 6.3.0, making this work for
     *   everybody running on 6.0.1 when updating at once to 6.4.1(release date
     *   2020-08-06) or later. If updating from an older version, some config
     *   values may be 'corrupted'.

     * @param string $currentVersion
     *   The current version of the config data. This will be replaced by the
     *   config value 'configVersion'. But as long as that key is not set, this
     *   'external' value (often a separate value in the host's config table)
     *   should be used.
     *
     * @return bool
     *   Success.
     */
    public function upgrade(string $currentVersion): bool
    {
        if ($currentVersion === '') {
            $currentVersion = '6.3.0';
        }

        $result = true;
        if (version_compare($currentVersion, Version, '<')) {
            $result = $this->applyUpgrades($currentVersion);
            $this->getConfig()->save([Config::configVersion => Version]);
        }
        return $result;
    }
    /**
     * Applies all updates since the $currentVersion to the config.
     *
     * Notes:
     * - If possible values for a config key are re-assigned, the default, which
     *   comes from code in the Config class, will already be the newly assigned
     *   value. So when updating, we should only update values stored in the
     *   database. Therefore, in a number of the methods below, you will see
     *   that the values are "loaded" using the ConfigStore, not the load()
     *   method of Config.
     *
     * @param string $currentVersion
     *   The current version of the config. Has already been confirmed to be
     *   less than the current version.
     *
     * @return bool
     *   Success.
     */
    public function applyUpgrades(string $currentVersion): bool
    {
        // Let's start with a Requirements check and fail if not all are met.
        $requirements = $this->getContainer()->getRequirements();
        $messages = $requirements->check();
        foreach ($messages as $message) {
            $this->getLog()->error("Requirement check failed: $message");
        }
        if (!empty($messages)) {
            throw new RuntimeException('Requirement check failed: ' . implode('; ', $messages));
        }

        $result = true;

        if (version_compare($currentVersion, '4.5.0', '<')) {
            $result = $this->upgrade450();
        }

        if (version_compare($currentVersion, '4.5.3', '<')) {
            $result = $this->upgrade453() && $result;
        }

        if (version_compare($currentVersion, '4.6.0', '<')) {
            $result = $this->upgrade460() && $result;
        }

        if (version_compare($currentVersion, '4.7.0', '<')) {
            $result = $this->upgrade470() && $result;
        }

        if (version_compare($currentVersion, '4.7.3', '<')) {
            $result = $this->upgrade473() && $result;
        }

        if (version_compare($currentVersion, '4.8.5', '<')) {
            $result = $this->upgrade496() && $result;
        }

        if (version_compare($currentVersion, '5.4.0', '<')) {
            $result = $this->upgrade540() && $result;
        }

        if (version_compare($currentVersion, '5.4.1', '<')) {
            $result = $this->upgrade541() && $result;
        }

        if (version_compare($currentVersion, '5.4.2', '<')) {
            $result = $this->upgrade542() && $result;
        }

        if (version_compare($currentVersion, '5.5.0', '<')) {
            $result = $this->upgrade550() && $result;
        }

        if (version_compare($currentVersion, '6.0.0', '<')) {
            $result = $this->upgrade600() && $result;
        }

        if (version_compare($currentVersion, '6.3.1', '<')) {
            $result = $this->upgrade631() && $result;
        }

        if (version_compare($currentVersion, '6.4.0', '<')) {
            $result = $this->upgrade640() && $result;
        }

        return $result;
    }


    /**
     * 4.5.0 upgrade.
     *
     * - Log level: added level info and set log level to notice if it currently
     *   is error or warning.
     * - Debug mode: the values of test mode and stay local are switched. Stay
     *   local is no longer used, so both these 2 values become the new test
     *   mode.
     *
     * @return bool
     */
    protected function upgrade450(): bool
    {
        $result = true;
        // Keep track of settings that should be updated.
        $newSettings = [];

        // 1) Log level.
        switch ($this->getConfig()->get('logLevel')) {
            case 1 /*Log::Error*/:
            case 2 /*Log::Warning*/:
                // This is often not giving enough information, so we set it
                // to Notice by default.
                $newSettings['logLevel'] = 3 /*Log::Notice*/;
                break;
            case 4 /*Log::Info*/:
                // Info was inserted, so this is the former debug level.
                $newSettings['logLevel'] = 5 /*Log::Debug*/;
                break;
        }

        // 2) Debug mode.
        /** @noinspection PhpSwitchStatementWitSingleBranchInspection */
        switch ($this->getConfig()->get('debug')) {
            case 4: // Value for deprecated PluginConfig::Debug_StayLocal.
                $newSettings['logLevel'] = Config::Send_TestMode;
                break;
        }

        if (!empty($newSettings)) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.5.3 upgrade.
     *
     * - setting triggerInvoiceSendEvent removed.
     * - setting triggerInvoiceEvent introduced.
     *
     * @return bool
     */
    protected function upgrade453(): bool
    {
        // Keep track of settings that should be updated.
        $newSettings = [];
        if ($this->getConfig()->get('triggerInvoiceSendEvent') == 2) {
            $newSettings['triggerInvoiceEvent'] = Config::TriggerInvoiceEvent_Create;
        } else {
            $newSettings['triggerInvoiceEvent'] = Config::TriggerInvoiceEvent_None;
        }

        return $this->getConfig()->save($newSettings);
    }

    /**
     * 4.6.0 upgrade.
     *
     * - setting removeEmptyShipping inverted.
     *
     * @return bool
     */
    protected function upgrade460(): bool
    {
        $result = true;
        $newSettings = [];

        if ($this->getConfig()->get('removeEmptyShipping') !== null) {
            $newSettings['sendEmptyShipping'] = !$this->getConfig()->get('removeEmptyShipping');
        }

        if (!empty($newSettings)) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.7.0 upgrade.
     *
     * - salutation could already use token, but with old syntax: remove # after [.
     *
     * @return bool
     */
    protected function upgrade470(): bool
    {
        $result = true;
        $newSettings = [];

        if ($this->getConfig()->get('salutation') && strpos($this->getConfig()->get('salutation'), '[#') !== false) {
            $newSettings['salutation'] = str_replace('[#', '[', $this->getConfig()->get('salutation'));
        }

        if (!empty($newSettings)) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.7.3 upgrade.
     *
     * - subject could already use token, but with #b and #f replace by new token syntax.
     *
     * @return bool
     */
    protected function upgrade473(): bool
    {
        $result = true;
        $newSettings = [];

        if ($this->getConfig()->get('subject') && strpos($this->getConfig()->get('subject'), '[#') !== false) {
            str_replace('[#b]', '[invoiceSource::reference]', $this->getConfig()->get('subject'));
            str_replace('[#f]', '[invoiceSource::invoiceNumber]', $this->getConfig()->get('subject'));
        }

        if (!empty($newSettings)) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.9.6 upgrade.
     *
     * - 4.7.3 update was never called (due to a typo 4.7.0 update was called).
     *
     * @return bool
     */
    protected function upgrade496(): bool
    {
        return $this->upgrade473();
    }

    /**
     * 5.4.0 upgrade.
     *
     * - ConfigStore->save should store all settings in 1 serialized value.
     *
     * @return bool
     */
    protected function upgrade540(): bool
    {
        $result = true;

        // ConfigStore::save should store all settings in 1 serialized value.
        $configStore = $this->getConfigStore();
        if (method_exists($configStore, 'loadOld')) {
            $values = $configStore->loadOld($this->getConfig()->getKeys());
            $result = $this->getConfig()->save($values);
        }

        return $result;
    }

    /**
     * 5.4.1 upgrade.
     *
     * - property source originalInvoiceSource renamed to order.
     *
     * @return bool
     */
    protected function upgrade541(): bool
    {
        $result = true;
        $doSave = false;
        $values = $this->getConfig()->getAll();
        array_walk_recursive($values, function(&$value) use (&$doSave) {
            if (is_string($value) && strpos($value, 'originalInvoiceSource::') !== false) {
                $value = str_replace('originalInvoiceSource::', 'order::', $value);
                $doSave = true;
            }
        });
        if ($doSave) {
            $result = $this->getConfig()->save($values);
        }

        return $result;
    }

    /**
     * 5.4.2 upgrade.
     *
     * - property paymentState renamed to paymentStatus.
     *
     * @return bool
     */
    protected function upgrade542(): bool
    {
        $result = true;
        $doSave = false;
        $configStore = $this->getConfigStore();
        $values = $configStore->load();
        array_walk_recursive($values, function(&$value) use (&$doSave) {
            if (is_string($value) && strpos($value, 'paymentState') !== false) {
                $value = str_replace('paymentState', 'paymentStatus', $value);
                $doSave = true;
            }
        });
        if ($doSave) {
            $result = $this->getConfig()->save($values);
        }

        return $result;
    }

    /**
     * 5.5.0 upgrade.
     *
     * - setting digitalServices extended and therefore renamed to foreignVat.
     *
     * @return bool
     */
    protected function upgrade550(): bool
    {
        $newSettings = [];
        $newSettings['foreignVat'] = (int) $this->getConfig()->get('digitalServices');
        return $this->getConfig()->save($newSettings);
    }

    /**
     * 6.0.0 upgrade.
     *
     * - Log level is now a Severity constant.
     *
     * @return bool
     */
    protected function upgrade600(): bool
    {
        $configStore = $this->getConfigStore();
        $values = $configStore->load();
        $newSettings = [];
        if (isset($values['logLevel'])) {
            switch ($values['logLevel']) {
                case 3 /*Log::Notice*/ :
                    $newSettings['logLevel'] = Severity::Notice;
                    break;
                case 4 /*Log::Info*/ :
                default:
                    $newSettings['logLevel'] = Severity::Info;
                    break;
                case 5 /*Log::Debug*/ :
                    $newSettings['logLevel'] = Severity::Log;
                    break;
            }
        }
        return $this->getConfig()->save($newSettings);
    }

    /**
     * 6.3.0 upgrade.
     *
     * - Only 1 setting for type of tax and its classes (foreign, free, 0).
     *
     * @return bool
     */
    protected function upgrade631(): bool
    {
        $configStore = $this->getConfigStore();
        $values = $configStore->load();
        // If Foreign vat was not set (unknown) or set to No, we should reset
        // any value in foreignVatClasses.
        // const ForeignVat_Unknown = 0;
        // const ForeignVat_No = 2;
        if (isset($values['foreignVat']) && ($values['foreignVat'] === 0 || $values['foreignVat'] === 2)) {
            $values['foreignVatClasses'] = [];
        }

        // If "vat free products" was not set (unknown) we should set the value
        // of vatFreeClass to "empty".
        // const VatFreeProducts_Unknown = 0;
        if (isset($values['vatFreeProducts']) && $values['vatFreeProducts'] === 0) {
            $values['vatFreeClass'] = '';
        }
        // If "vat free products" was set to No, we should set the value
        // of vatFreeClass to Config::VatClass_NotApplicable.
        // const VatFreeProducts_No = 2;
        if (isset($values['vatFreeProducts']) && $values['vatFreeProducts'] === 2) {
            $values['vatFreeClass'] = Config::VatClass_NotApplicable;
        }

        // If "0 vat products" was not set (unknown) we should set the value
        // of zeroVatClass to "empty".
        // const ZeroVatProducts_Unknown = 0;
        if (isset($values['zeroVatProducts']) && $values['zeroVatProducts'] === 0) {
            $values['zeroVatClass'] = '';
        }
        // If "0 vat products" was set to No, we should set the value
        // of zeroVatClass to Config::VatClass_NotApplicable.
        // const ZeroVatProducts_No = 2;
        if (isset($values['zeroVatProducts']) && $values['zeroVatProducts'] === 2) {
            $values['zeroVatClass'] = Config::VatClass_NotApplicable;
        }

        return $this->getConfig()->save($values);
    }

    /**
     * 6.4.0 upgrade.
     *
     * - values for setting nature_shop changed into combinable bit values.
     * - foreignVatClasses renamed to euVatClasses.
     *
     * @return bool
     */
    protected function upgrade640(): bool
    {
        $configStore = $this->getConfigStore();
        $values = $configStore->load();

        // Nature constants Services and Both are switched.
        if (isset($values['nature_shop'])) {
            switch ($values['nature_shop']) {
                case 1:
                    $values['nature_shop'] = 3;
                    break;
                case 3:
                    $values['nature_shop'] = 1;
                    break;
            }
        } else {
            $values['nature_shop'] = Config::Nature_Unknown;
        }

        // foreignVatClasses renamed to euVatClasses.
        if (isset($values['foreignVatClasses'])) {
            $values['euVatClasses'] = $values['foreignVatClasses'];
        }

        $this->getLog()->notice('Config: updating to 6.4.0');
        return $this->getConfig()->save($values);
    }
}
