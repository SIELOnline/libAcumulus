<?php

declare(strict_types=1);

namespace Siel\Acumulus\Config;

use RuntimeException;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Requirements;
use Siel\Acumulus\Helpers\Severity;

use function count;
use function is_string;

use const Siel\Acumulus\Version;

/**
 * Class ConfigUpgrade contains all upgrades to the config.
 */
class ConfigUpgrade
{
    protected Config $config;
    protected ConfigStore $configStore;
    protected Requirements $requirements;
    protected Log $log;

    public function __construct(Config $config, ConfigStore $configStore, Requirements $requirements, Log $log)
    {
        $this->config = $config;
        $this->configStore = $configStore;
        $this->requirements = $requirements;
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

    public function getRequirements(): Requirements
    {
        return $this->requirements;
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
     *   this value (MA2.4). If so, we switch to using a new key 'VersionKey'
     *   in the set of config values.
     * - 'VersionKey' was introduced in 6.4.1. So when upgrading from an
     *   older version it will not be set and if $currentVersion is also not
     *   passed in, we have to guess it. The 6.0.0 update is not idempotent,
     *   whereas the 6.3.1 update is, so we "guess" 6.3.0, making this work for
     *   everybody running on 6.0.1 when updating at once to 6.4.1(release date
     *   2020-08-06) or later. If updating from an older version, some config
     *   values may be 'corrupted'.

     * @param string $currentVersion
     *   The current version of the config data. This will be replaced by the
     *   config value 'VersionKey'. But as long as that key is not set, this
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
            $this->getConfig()->save([Config::VersionKey => Version]);
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
     *
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    protected function applyUpgrades(string $currentVersion): bool
    {
        // Let's start with a Requirements check and fail if not all are met.
        $messages = $this->getRequirements()->check();
        foreach ($messages as $key => $message) {
            $severity = strpos($key, 'warning') !== false ? Severity::Warning : Severity::Error;
            $this->getLog()->log($severity, "Requirement check warning: $message");
            if ($severity === Severity::Warning) {
                unset($messages[$key]);
            }
        }
        if (count($messages) !== 0) {
            throw new RuntimeException('Requirement check failed: ' . implode('; ', $messages));
        }

        $result = true;
        $this->getLog()->notice("Config: start upgrading from $currentVersion");

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

        if (version_compare($currentVersion, '7.4.0', '<')) {
            $result = $this->upgrade740() && $result;
        }

        if (version_compare($currentVersion, '8.0.0', '<')) {
            $result = $this->upgrade800() && $result;
        }

        $this->getLog()->notice('Config: finished upgrading to %s (%s)', Version, $result ? 'success' : 'failure');
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

        if (count($newSettings) !== 0) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.5.3 upgrade.
     *
     * - setting triggerInvoiceSendEvent removed.
     * - setting triggerInvoiceEvent introduced.
     */
    protected function upgrade453(): bool
    {
        // Keep track of settings that should be updated.
        $newSettings = [];
        if ($this->getConfig()->get('triggerInvoiceSendEvent') === 2) {
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
     */
    protected function upgrade460(): bool
    {
        $result = true;
        $newSettings = [];

        if ($this->getConfig()->get('removeEmptyShipping') !== null) {
            $newSettings['sendEmptyShipping'] = !$this->getConfig()->get('removeEmptyShipping');
        }

        if (count($newSettings) !== 0) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.7.0 upgrade.
     *
     * - salutation could already use token, but with old syntax: remove # after [.
     */
    protected function upgrade470(): bool
    {
        $result = true;
        $newSettings = [];

        if ($this->getConfig()->get('salutation') && strpos($this->getConfig()->get('salutation'), '[#') !== false) {
            $newSettings['salutation'] = str_replace('[#', '[', $this->getConfig()->get('salutation'));
        }

        if (count($newSettings) !== 0) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.7.3 upgrade.
     *
     * - subject could already use token, but with #b and #f replace by new token syntax.
     */
    protected function upgrade473(): bool
    {
        $result = true;
        $newSettings = [];

        if ($this->getConfig()->get('subject') && strpos($this->getConfig()->get('subject'), '[#') !== false) {
            $newSettings['subject'] = str_replace(['[#b]', '#f'],
                ['[invoiceSource::reference]', '[invoiceSource::invoiceNumber]'],
                $this->getConfig()->get('subject'));
        }

        if (count($newSettings) !== 0) {
            $result = $this->getConfig()->save($newSettings);
        }
        return $result;
    }

    /**
     * 4.9.6 upgrade.
     *
     * - 4.7.3 update was never called (due to a typo 4.7.0 update was called).
     */
    protected function upgrade496(): bool
    {
        return $this->upgrade473();
    }

    /**
     * 5.4.0 upgrade.
     *
     * - ConfigStore->save should store all settings in 1 serialized value.
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
     */
    protected function upgrade541(): bool
    {
        $result = true;
        $doSave = false;
        $values = $this->getConfig()->getAll();
        array_walk_recursive($values, static function(&$value) use (&$doSave) {
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
     */
    protected function upgrade542(): bool
    {
        $result = true;
        $doSave = false;
        $configStore = $this->getConfigStore();
        $values = $configStore->load();
        array_walk_recursive($values, static function(&$value) use (&$doSave) {
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

        return $this->getConfig()->save($values);
    }

    /**
     * 7.4.0 upgrade.
     *
     * - settings to show invoice and packing slip pdf now available for
     *   detail and list screen:
     *   - renamed the original settings by adding 'Detail' to the end.
     *   - introduced 2 new settings for the list page, copy value from the
     *     original value for the detail screen.
     */
    protected function upgrade740(): bool
    {
        $newSettings = [];

        $old = $this->getConfig()->get('showPdfInvoice');
        if ($old !== null) {
            $newSettings['showInvoiceDetail'] = (bool) $old;
            $newSettings['showInvoiceList'] = (bool) $old;
        }

        $old = $this->getConfig()->get('showPdfPackingSlip');
        if ($old !== null) {
            $newSettings['showPackingSlipDetail'] = (bool) $old;
            $newSettings['showPackingSlipList'] = (bool) $old;
        }

        return $this->getConfig()->save($newSettings);
    }

    /**
     * 8.0.0 upgrade.
     *
     * - Settings to mappings:
     *   - Keep the old settings (for emergency revert).
     *   - Copy the settings that are a mapping to the 'mappings' settings.
     *   - Cater for defaults that have been rephrased (start with Source::, use method
     *     call syntax), so only copy those that are stored in the config.
     */
    protected function upgrade800(): bool
    {
        $mappingKeys = [
            'contactYourId' => [DataType::Customer, Fld::ContactYourId],
            'companyName1' => [AddressType::Invoice, Fld::CompanyName1],
            'companyName2' => [AddressType::Invoice, Fld::CompanyName2],
            'fullName' => [AddressType::Invoice, Fld::FullName],
            'salutation' => [AddressType::Invoice, Fld::Salutation],
            'address1' => [AddressType::Invoice, Fld::Address1],
            'address2' => [AddressType::Invoice, Fld::Address2],
            'postalCode' => [AddressType::Invoice, Fld::PostalCode],
            'city' => [AddressType::Invoice, Fld::City],
            'vatNumber' => [DataType::Customer, Fld::VatNumber],
            'telephone' => [DataType::Customer, Fld::Telephone],
            'fax' => [DataType::Customer, Fld::Fax],
            'email' => [DataType::Customer, Fld::Email],
            'mark' => [DataType::Customer, Fld::Mark],
            'description' => [DataType::Invoice, Fld::Description],
            'descriptionText' => [DataType::Invoice, Fld::DescriptionText],
            'invoiceNotes' => [DataType::Invoice, Fld::InvoiceNotes],
            'itemNumber' => [LineType::Item, Fld::ItemNumber],
            'productName' => [LineType::Item, Fld::Product],
            'nature' => [LineType::Item, Fld::Nature],
            'costPrice' => [LineType::Item, Fld::CostPrice],
            'emailFrom' => [EmailAsPdfType::Invoice, Fld::EmailFrom],
            'emailTo' => [EmailAsPdfType::Invoice, Fld::EmailTo],
            'emailBcc' => [EmailAsPdfType::Invoice, Fld::EmailBcc],
            'subject' => [EmailAsPdfType::Invoice, Fld::Subject],
            'confirmReading' => [EmailAsPdfType::Invoice, Fld::ConfirmReading],
            'packingSlipEmailFrom' => [EmailAsPdfType::PackingSlip, Fld::EmailFrom],
            'packingSlipEmailTo' => [EmailAsPdfType::PackingSlip, Fld::EmailTo],
            'packingSlipEmailBcc' => [EmailAsPdfType::PackingSlip, Fld::EmailBcc],
            'packingSlipSubject' => [EmailAsPdfType::PackingSlip, Fld::Subject],
            'packingSlipConfirmReading' => [EmailAsPdfType::PackingSlip, Fld::ConfirmReading],
        ];
        $result = true;
        $configStore = $this->getConfigStore();
        $values = $configStore->load();
        $mappings = $values[Config::Mappings] ?? [];
        foreach ($mappingKeys as $key => [$group, $property]) {
            // - Was the old key being overridden by the user? That is, is there a value,
            //   even if it is empty?
            // - Does the new mapping somehow already have a value: do not overwrite.
            if (isset($values[$key]) && !isset($mappings[$group][$property])) {
                // Chances are it won't work anymore, as you now probably have to start
                // with 'Source::getSource()::{method on WC_Order}'. So we should issue a
                // warning.
                $mappings[$group] = $mappings[$group] ?? [];
                $mappings[$group][$property] = $values[$key];
            }
        }
        if (!empty($mappings)) {
            $values[Config::Mappings] = $mappings;
            // This is to warn the user.
            $values['showPluginV8MessageOverriddenMappings'] = $this->getOverriddenMappings($mappings);
            $result = $configStore->save($values);
        }
        return $result;
    }

    /**
     * Returns a list of mappings that are overridden.
     *
     * @param string[][] $mappings
     *
     * @return string[]
     */
    private function getOverriddenMappings(array $mappings): array
    {
        $result = [];
        foreach ($mappings as $object => $objectMappings) {
            foreach ($objectMappings as $property => $mapping) {
                $result[] = "$object::$property";
            }
        }
        return $result;
    }
}
