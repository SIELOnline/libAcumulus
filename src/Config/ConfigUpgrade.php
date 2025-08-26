<?php
/**
 * @noinspection PhpUnhandledExceptionInspection Config::save may throw, but we ignore that.
 */

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
     * in the situation where the config data is up to date.
     *
     * Notes:
     * - $currentVersion can be empty if the host environment cannot deliver
     *   this value (MA2.4). If so, we switch to using a new key 'VersionKey'
     *   in the set of config values.
     * - 'VersionKey' was introduced in 6.4.1. So when upgrading from an
     *   older version, it will not be set, and if $currentVersion is also not
     *   passed in, we have to guess it. The 6.0.0 update is not idempotent,
     *   whereas the 6.3.1 update is, so we "guess" 6.3.0, making this work for
     *   everybody running on 6.0.1 when updating at once to 6.4.1(release date
     *   2020-08-06) or later. If updating from an older version, some config
     *   values may be 'corrupted'.
     *
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
     */
    protected function applyUpgrades(string $currentVersion): bool
    {
        // Let's start with a Requirements check and fail if not all are met.
        $messages = $this->getRequirements()->check();
        foreach ($messages as $key => $message) {
            $severity = str_contains($key, 'warning') ? Severity::Warning : Severity::Error;
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

        if (version_compare($currentVersion, '6.4.0', '<')) {
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            $result = $this->upgrade640() && $result;
        }

        if (version_compare($currentVersion, '7.4.0', '<')) {
            $result = $this->upgrade740() && $result;
        }

        if (version_compare($currentVersion, '8.0.0', '<')) {
            $result = $this->upgrade800() && $result;
        }

        if (version_compare($currentVersion, '8.3.0', '<')) {
            $result = $this->upgrade830() && $result;
        }

        if (version_compare($currentVersion, '8.3.6', '<')) {
            $result = $this->upgrade836() && $result;
        }

        if (version_compare($currentVersion, '8.3.7', '<')) {
            $result = $this->upgrade837() && $result;
        }

        $this->getLog()->notice('Config: finished upgrading to %s (%s)', Version, $result ? 'success' : 'failure');
        return $result;
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
     * - settings to show invoice and packing slip PDF now available for
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
     *   - Cater for defaults that have been rephrased (starting with 'Source::',
     *     use method call syntax), so only copy those that are stored in the config.
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
        $values = $this->getConfigStore()->load();
        $mappings = $values[Config::Mappings] ?? [];
        foreach ($mappingKeys as $key => [$group, $property]) {
            // - Was the old key being overridden by the user? That is, is there a value,
            //   even if it is empty?
            // - Does the new mapping somehow already have a value? do not overwrite.
            if (isset($values[$key]) && !isset($mappings[$group][$property])) {
                // Chances are it won't work anymore, as you now probably have to start
                // with 'Source::getShopObject()::{method on shop Order}'. So we should issue
                // a warning.
                $mappings[$group] = $mappings[$group] ?? [];
                $mappings[$group][$property] = $values[$key];
            }
        }
        if (!empty($mappings)) {
            $values[Config::Mappings] = $mappings;
            // This is to warn the user.
            $values['showPluginV8MessageOverriddenMappings'] = $this->getOverriddenMappings($mappings);
            $result = $this->getConfig()->save($values);
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
                $result[] = "$object::$property (was $mapping)";
            }
        }
        return $result;
    }

    /**
     * 8.0.2 upgrade.
     *
     * - Move salutation from (invoice) address to customer.
     */
    protected function upgrade802(): bool
    {
        $result = true;
        $values = $this->getConfigStore()->load();
        $mappings = $values[Config::Mappings] ?? [];
        $doSave = false;
        if (isset($mappings[AddressType::Invoice][Fld::Salutation])) {
            $mappings[DataType::Customer][Fld::Salutation] = $mappings[AddressType::Invoice][Fld::Salutation];
            unset($mappings[AddressType::Invoice][Fld::Salutation]);
            $doSave = true;
        }
        if (isset($mappings[AddressType::Shipping][Fld::Salutation])) {
            if (!$doSave) {
                // 'salutation' field was not set on the "invoice address" but it is set
                // on the "shipping address", copy it from that address.
                $mappings[DataType::Customer][Fld::Salutation] = $mappings[AddressType::Shipping][Fld::Salutation];
            }
            unset($mappings[AddressType::Shipping][Fld::Salutation]);
            $doSave = true;
        }
        if ($doSave) {
            $result = $this->getConfig()->save([Config::Mappings => $mappings]);
        }
        return $result;
    }

    /**
     * 8.3.0 upgrade.
     *
     * - Removed all settings that are now a mapping: just save the config to remove these
     *   settings.
     * - Renamed Source::getSource() to Source::getShopObject(): update Config::Mappings
     *   config value and save it (this takes care of the first update as well).
     */
    protected function upgrade830(): bool
    {
        // Was never called.
        $result = $this->upgrade802();

        $values = $this->getConfigStore()->load();
        $mappings = $values[Config::Mappings] ?? [];
        $replacements = [
            'invoiceSource::' => 'source::',
            '::getSource()::' => '::getShopObject()::',
            'invoiceSourceType::label' => 'source::getLabel(2)',
            'order::' => 'source::getOrder()::',
            'refundedOrder::' => 'source::getParent()::',
            'refund::' => 'source::isCreditNote()::',
        ];
        array_walk_recursive($mappings, static function (&$value) use ($replacements) {
            foreach ($replacements as $search => $replace) {
                if (is_string($value)) {
                    $value = str_replace($search, $replace, $value);
                }
            }
        });
        return $this->getConfig()->save([Config::Mappings => $mappings]) && $result;
    }

    /**
     * 8.3.6 upgrade.
     *
     * - Removed setting 'outputFormat'.
     */
    protected function upgrade836(): bool
    {
        $values = $this->getConfigStore()->load();
        unset($values['outputFormat']);
        return $this->getConfig()->save($values);
    }

    /**
     * 8.3.7 upgrade.
     *
     * - API fields are now lowercase (as are the tags) => update config store when used
     *   as a config key:
     *   - The contract fields, basically we are undoing upgrade836() (which has been
     *     cleaned up).
     *   - Mappings
     * - getTypeLabel() in mappings should be renamed to getType()
     */
    protected function upgrade837(): bool
    {
        // Mapping keys that are commented out are already all lowercase.
        $mappingKeys = [
            DataType::Customer => [
                'contactYourId' => Fld::ContactYourId,
                'vatNumber' => Fld::VatNumber,
//              [telephone', Fld::Telephone],
//              'fax' => Fld::Fax,
//              'email' => Fld::Email,
//              'mark' => Fld::Mark,
//              'description' => Fld::Description,
            ],
            AddressType::Invoice => [
                'companyName1' => Fld::CompanyName1,
                'companyName2' => Fld::CompanyName2,
                'fullName' => Fld::FullName,
//              'salutation' => Fld::Salutation,
//              'address1' => Fld::Address1,
//              'address2' => Fld::Address2,
                'postalCode' => Fld::PostalCode,
//              [city', Fld::City],
            ],
            AddressType::Shipping => [
                'companyName1' => Fld::CompanyName1,
                'companyName2' => Fld::CompanyName2,
                'fullName' => Fld::FullName,
//              'salutation' => Fld::Salutation,
//              'address1' => Fld::Address1,
//              'address2' => Fld::Address2,
                'postalCode' => Fld::PostalCode,
//              [city', Fld::City],
            ],
            DataType::Invoice => [
                'descriptionText' => Fld::DescriptionText,
                'invoiceNotes' => Fld::InvoiceNotes,
            ],
            LineType::Item => [
                'itemNumber' => Fld::ItemNumber,
//              'product' => Fld::Product,
//              'nature' => Fld::Nature,
                'costPrice' => Fld::CostPrice,
            ],
            EmailAsPdfType::Invoice => [
                'emailFrom' => Fld::EmailFrom,
                'emailTo' => Fld::EmailTo,
                'emailBcc' => Fld::EmailBcc,
//              'subject' => Fld::Subject,
                'confirmReading' => Fld::ConfirmReading,
            ],
            EmailAsPdfType::PackingSlip => [
                'emailFrom' => Fld::EmailFrom,
                'emailTo' => Fld::EmailTo,
                'emailBcc' => Fld::EmailBcc,
//              'subject' => Fld::Subject,
                'confirmReading' => Fld::ConfirmReading,
                // Error in the 'mappings' form, those 2 keys were never corrected before 8.3.7.
                'packingSlipEmailTo' => Fld::EmailTo,
                'packingSlipEmailBcc' => Fld::EmailBcc,
            ],
        ];
        $replacements = [
            '::getTypeLabel(' => '::getLabel(',
        ];

        $values = $this->getConfigStore()->load();
        $mappings = $values[Config::Mappings] ?? [];
        $newMappings = [];
        foreach ($mappings as $group => $mappingsGroup) {
            $newMappings[$group] = [];
            foreach ($mappingsGroup as $key => $value) {
                foreach ($replacements as $search => $replace) {
                    if (is_string($value)) {
                        $value = str_replace($search, $replace, $value);
                    }
                }
                $key = $mappingKeys[$group][$key] ?? $key;
                $newMappings[$group][$key] = $value;
            }
        }
        $newValues = [Config::Mappings => $newMappings];

        $keyReplacements = [
            'contractCode' => Fld::ContractCode,
            'userName' => Fld::UserName,
            'emailOnError' => Fld::EmailOnError,
        ];
        foreach ($values as $key => $value) {
            if (isset($keyReplacements[$key])) {
                $newKey = $keyReplacements[$key];
                $newValues[$newKey] = $value;
            }
        }
        return $this->getConfig()->save($newValues);
    }
}
