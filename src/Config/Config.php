<?php
/**
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection  SensitiveParameter.
 * @noinspection PhpLanguageLevelInspection  An attribute is a comment in 7.4.
 * @noinspection SelfClassReferencingInspection
 *   I prefer to refer to these constants as Config constants, and so, for me,
 *   it is irrelevant that this happens to be the Config class.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Config;

use SensitiveParameter;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

use function strlen;

use const Siel\Acumulus\Version;

/**
 * Provides uniform access to the settings of libAcumulus.
 *
 * Configuration is stored in the host environment bridged via the ConfigStore
 * class.
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class Config
{
    public const VersionKey = 'configVersion';

    public const Concept_Plugin = 2;

    public const Send_SendAndMailOnError = 1;
    public const Send_SendAndMail = 2;
    public const Send_TestMode = 3;

    public const MissingAmount_Ignore = 1;
    public const MissingAmount_Warn = 2;
    public const MissingAmount_AddLine = 3;

    public const InvoiceNrSource_ShopInvoice = 1;
    public const InvoiceNrSource_ShopOrder = 2;
    public const InvoiceNrSource_Acumulus = 3;

    public const IssueDateSource_InvoiceCreate = 1;
    public const IssueDateSource_OrderCreate = 2;
    public const IssueDateSource_Transfer = 3;

    public const Nature_Unknown = 0;
    public const Nature_Services = 1;
    public const Nature_Products = 2;
    public const Nature_Both = 3;

    public const MarginProducts_Unknown = 0;
    public const MarginProducts_Both = 1;
    public const MarginProducts_No = 2;
    public const MarginProducts_Only = 3;

    public const EuVat_Unknown = 0;
    public const EuVat_Yes = 1;
    public const EuVat_SwitchOnLimit = 2;
    public const EuVat_No = 3;

    public  const VatClass_NotApplicable = 'vat_class_not_applicable';
    // Note: used both as value in Config and as value for Meta::VatClassId.
    public const VatClass_Null = 'vat_class_null';

    public const TriggerInvoiceEvent_None = 0;
    public const TriggerInvoiceEvent_Create = 1;
    public const TriggerInvoiceEvent_Send = 2;

    public const TriggerCreditNoteEvent_None = 0;
    public const TriggerCreditNoteEvent_Create = 1;

    public const PropertyMappings = 'propertyMappings';
    public const MetadataMappings = 'metadataMappings';

    private ConfigStore $configStore;
    private ShopCapabilities $shopCapabilities;
    /** @var callable */
    private $getConfigUpgradeInstance;
    private Environment $environment;
    protected Translator $translator;
    protected Log $log;
    protected array $keyInfo;
    protected bool $isConfigurationLoaded;
    protected bool $isUpgrading;
    protected array $values;

    public function __construct(
        ConfigStore $configStore,
        ShopCapabilities $shopCapabilities,
        callable $getConfigUpgrade,
        Environment $environment,
        Log $log
    )
    {
        $this->configStore = $configStore;
        $this->shopCapabilities = $shopCapabilities;
        $this->getConfigUpgradeInstance = $getConfigUpgrade;
        $this->environment = $environment;
        $this->log = $log;

        $this->isConfigurationLoaded = false;
        $this->values = [];
    }

    /**
     * Wrapper getter around the config store object.
     */
    protected function getConfigStore(): ConfigStore
    {
        return $this->configStore;
    }

    /**
     * Wrapper getter around the store capabilities object.
     */
    protected function getShopCapabilities(): ShopCapabilities
    {
        return $this->shopCapabilities;
    }

    /**
     * Wrapper around the getConfigUpdateInstance callable.
     */
    protected function getConfigUpgrade(): ConfigUpgrade
    {
        return ($this->getConfigUpgradeInstance)();
    }

    /**
     * Loads the configuration from the actual configuration provider.
     *
     * After loading this method checks if the stored values need an upgrade
     * and, if so, will trigger that update.
     */
    protected function load(): void
    {
        if (!$this->isConfigurationLoaded) {
            $this->values = $this->getConfigStore()->load() + $this->getDefaults();
            $this->values = $this->castValues($this->values);
            $this->isConfigurationLoaded = true;

            if (!empty($this->values[Config::VersionKey])
                && version_compare($this->values[Config::VersionKey], Version, '<')
                && !isset($this->isUpgrading)
            ) {
                $this->isUpgrading = true;
                $this->getConfigUpgrade()->upgrade($this->values[Config::VersionKey]);
                $this->isUpgrading = false;
            }
        }
    }

    /**
     * Saves the configuration to the actual configuration provider.
     *
     * @param array $values
     *   A keyed array that contains the values to store, this may be a subset
     *   of the possible keys. Keys that are not present will not be changed.
     *
     * @return bool
     *   Success.
     */
    public function save(
        #[SensitiveParameter]
        array $values
    ): bool {
        // Log values in a notice but without the password.
        $copy = $values;
        if (!empty($copy[Tag::Password])) {
            $copy[Tag::Password] = 'REMOVED FOR SECURITY';
        }
        $this->log->notice('ConfigStore::save(): saving %s', serialize($copy));

        // Remove password if not sent along. We have had some reports that
        // passwords were gone missing, perhaps some shops do not send the value
        // of password fields to the client???
        if (array_key_exists(Tag::Password, $values) && empty($values[Tag::Password])) {
            unset($values[Tag::Password]);
        }

        // As we have 2 setting screens, but also with updates, not all settings
        // will be passed in: complete with other settings.
        $this->load();
        $values = array_merge($this->values, $values);
        $values = $this->castValues($values);
        $values = $this->removeValuesNotToBeStored($values);
        $result = $this->getConfigStore()->save($values);
        $this->isConfigurationLoaded = false;
        // Sync internal values.
        $this->load();
        return $result;
    }

    /**
     * Casts the values to their correct types.
     *
     * Values that come from a submitted form are all strings. Values that come
     * from the config store might be null. However, internally we work with
     * booleans or integers. So after reading from the config store or form, we
     * cast the values to their expected types.
     *
     * @param array $values
     *   Array with values to cast.
     *
     * @return array
     *   Array with cast values.
     */
    protected function castValues(
        #[SensitiveParameter]
        array $values
    ): array {
        $keyInfos = $this->getKeyInfo();
        foreach ($keyInfos as $key => $keyInfo) {
            if (array_key_exists($key, $values)) {
                switch ($keyInfo['type']) {
                    case 'string':
                        if (!is_string($values[$key])) {
                            $values[$key] = (string) $values[$key];
                        }
                        break;
                    case 'int':
                        if (!is_int($values[$key])) {
                            $values[$key] = $values[$key] === '' ? '' : (int) $values[$key];
                        }
                        break;
                    case 'float':
                        if (!is_float($values[$key])) {
                            $values[$key] = $values[$key] === '' ? '' : (float) $values[$key];
                        }
                        break;
                    case 'bool':
                        if (!is_bool($values[$key])) {
                            $values[$key] = $values[$key] === '' ? '' : (bool) $values[$key];
                        }
                        break;
                    case 'array':
                        if (!is_array($values[$key])) {
                            $values[$key] = [$values[$key]];
                        }
                        break;
                }
            }
        }
        return $values;
    }

    /**
     * Removes configuration values that do not have to be stored.
     *
     * Values that do not have to be stored:
     * - Values that are not set.
     * - Values that equal their default value.
     * - Keys that are unknown.
     *
     * @param array $values
     *   The array to remove values from.
     *
     * @return array
     *   The set of values passed in reduced to those values to be stored.
     */
    protected function removeValuesNotToBeStored(array $values): array
    {
        $result = [];
        $keys = $this->getKeys();
        $defaults = $this->getDefaults();
        foreach ($keys as $key) {
            if (isset($values[$key]) && (!isset($defaults[$key]) || $values[$key] !== $defaults[$key])) {
                $result[$key] = $values[$key];
            }
        }
        return $result;
    }

    /**
     * Returns the ShowRatePluginMessage config setting.
     *
     * @noinspection PhpUnused
     *    Called form shop specific code outside this library.
     */
    public function getShowRatePluginMessage(): ?int
    {
        return $this->get('showRatePluginMessage');
    }

    /**
     * Returns all configuration values.
     *
     * @return array
     *   An array with all configuration values keyed by their name.
     */
    public function getAll(): array
    {
        $this->load();
        return $this->values;
    }

    /**
     * Returns the value of the specified configuration value.
     *
     * @param string $key
     *   The requested configuration value
     *
     * @return mixed
     *   The value of the given configuration value or null if not defined. This
     *   will be a simple type (string, int, bool) or a keyed array with simple
     *   values.
     */
    public function get(string $key)
    {
        $this->load();
        return $this->values[$key] ?? null;
    }

    /**
     * Sets the internal value of the specified configuration key.
     *
     * NOTE: This value will not be stored, use save() for that.
     *
     * @param string $key
     *   The configuration value to set.
     * @param mixed $value
     *   The new value for the configuration key.
     *
     * @return mixed
     *   The old value, or null if it was not yet set.
     */
    public function set(string $key, $value)
    {
        $this->load();
        $oldValue = $this->values[$key] ?? null;
        $this->values[$key] = $value;
        return $oldValue;
    }

    /**
     * Returns the contract credentials to authenticate with the Acumulus API.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'contractcode'
     *   - 'username'
     *   - 'password'
     *   - 'emailonerror'
     *   - 'emailonwarning'
     */
    public function getCredentials(): array
    {
        $result = $this->getSettingsByGroup('credentials');
        // No separate key for now.
        $result[Tag::EmailOnWarning] = $result[Tag::EmailOnError];
        return $result;
    }

    /**
     * Returns the set of internal plugin settings.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'debug'
     *   - 'logLevel'
     *   - 'outputFormat'
     */
    public function getPluginSettings(): array
    {
        return $this->getSettingsByGroup('plugin');
    }

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'triggerOrderStatus'
     *   - 'triggerInvoiceEvent'
     *   - 'triggerCreditNoteEvent'
     *   - 'sendEmptyInvoice'
     */
    public function getShopEventSettings(): array
    {
        return $this->getSettingsByGroup('event');
    }

    /**
     * Returns the set of settings related to the shop characteristics.
     *
     * These settings influence the invoice creation and completion tasks.
     *
     * @return array
     *   A keyed array with the keys:
     *   - nature_shop
     *   - 'marginProducts'
     *   - 'euVat'
     *   - 'vatFreeClass'
     *   - 'zeroVatClass'
     *   - 'invoiceNrSource'
     *   - 'dateToUse'
     */
    public function getShopSettings(): array
    {
        return $this->getSettingsByGroup('shop');
    }

    /**
     * Returns the set of settings related to the customer part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'sendCustomer'
     *   - 'overwriteIfExists'
     *   - 'defaultCustomerType'
     *   - 'contactStatus'
     *   - 'contactYourId'
     *   - 'companyName1'
     *   - 'companyName2'
     *   - 'vatNumber'
     *   - 'fullName'
     *   - 'salutation'
     *   - 'address1'
     *   - 'address2'
     *   - 'postalCode'
     *   - 'city'
     *   - 'telephone'
     *   - 'fax'
     *   - 'email'
     *   - 'mark'
     *   - 'genericCustomerEmail'
     */
    public function getCustomerSettings(): array
    {
        return $this->getSettingsByGroup(Tag::Customer);
    }

    /**
     * Returns the settings to collect the invoice address.
     */
    public function getInvoiceAddressSettings(): array
    {
        $keys = array_fill_keys([
            'companyName1',
            'companyName2',
            'fullName',
            'salutation',
            'address1',
            'address2',
            'postalCode',
            'city',
            'countryCode',
        ], true);
        return array_intersect_key($this->getAll(), $keys);
    }

    /**
     * Returns the settings to collect the shipping address.
     */
    public function getShippingAddressSettings(): array
    {
        $keys = array_fill_keys([
            'alt_companyName1',
            'alt_companyName2',
            'alt_fullName',
            'alt_salutation',
            'alt_address1',
            'alt_address2',
            'alt_postalCode',
            'alt_city',
            'alt_countryCode',
        ], true);
        $settings = array_intersect_key($this->getAll(), $keys);
        $result = [];
        foreach ($settings as $key => $value) {
            $newKey = strncmp($key, 'alt_', strlen('alt_')) === 0 ? substr($key, strlen('alt_')) : $key;
            $result[$newKey] = $value;
        }
        return $result;
    }

    /**
     * Returns the set of settings related to the invoice part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'concept'
     *   - 'euCommerceThresholdPercentage'
     *   - 'missingAmount'
     *   - 'defaultAccountNumber'
     *   - 'defaultCostCenter'
     *   - 'defaultInvoiceTemplate'
     *   - 'defaultInvoicePaidTemplate'
     *   - 'paymentMethodAccountNumber'
     *   - 'paymentMethodCostCenter'
     *   - 'sendEmptyShipping'
     *   - 'description'
     *   - 'descriptionText'
     *   - 'invoiceNotes'
     *   - 'optionsShow'
     *   - 'optionsAllOn1Line'
     *   - 'optionsAllOnOwnLine'
     *   - 'optionsMaxLength'
     *   - 'itemNumber'
     *   - 'productName'
     *   - 'nature'
     *   - 'costPrice'
     */
    public function getInvoiceSettings(): array
    {
        return $this->getSettingsByGroup(Tag::Invoice);
    }

    /**
     * Returns the set of settings related to sending an email.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'emailAsPdf'
     *   - 'emailBcc'
     *   - 'emailFrom'
     *   - 'subject'
     *   - 'confirmReading'
     *   - 'packingSlipEmailTo'
     */
    public function getEmailAsPdfSettings(): array
    {
        return $this->getSettingsByGroup(Tag::EmailAsPdf);
    }

    /**
     * Returns the set of settings related to e-mailing an invoice pdf.
     */
    public function getInvoiceEmailAsPdfSettings(): array
    {
        $keys = array_fill_keys([
            'emailAsPdf',
            'emailFrom',
            'emailTo',
            'emailBcc',
            'subject',
            'confirmReading',
        ], true);
        return array_intersect_key($this->getAll(), $keys);
    }

    /**
     * Returns the set of settings related to e-mailing a packing slip ppf.
     */
    public function getPackingSlipEmailAsPdfSettings(): array
    {
        $keys = array_fill_keys([
            'packingSlipEmailFrom',
            'packingSlipEmailTo',
            'packingSlipEmailBcc',
            'packingSlipSubject',
            'packingSlipConfirmReading',
        ], true);
        $settings = array_intersect_key($this->getAll(), $keys);
        $result = [];
        foreach ($settings as $key => $value) {
            $newKey = strncmp($key, 'packingSlip', strlen('packingSlip')) === 0 ? lcfirst(substr($key, strlen('packingSlip'))) : $key;
            $result[$newKey] = $value;
        }
        return $result;
    }

    /**
     * Returns the set of settings related to the invoice status tab/box.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'showInvoiceStatus'
     */
    public function getInvoiceStatusSettings(): array
    {
        return $this->getSettingsByGroup('status');
    }

    /**
     * Returns the set of settings related to the pdf documents.
     *
     * @return array
     *   A keyed array with the keys:
     *   - 'showInvoiceDetail'
     *   - 'mailInvoiceDetail'
     *   - 'showPackingSlipDetail'
     *   - 'mailPackingSlipDetail'
     *   - 'showInvoiceList'
     *   - 'mailInvoiceList'
     *   - 'showPackingSlipList'
     *   - 'mailPackingSlipList'
     */
    public function getDocumentsSettings(): array
    {
        return $this->getSettingsByGroup('documents');
    }

    /**
     * Returns mappings that are overridden via the configuration.
     *
     * @return string[][][]
     *   See {@see Mappings::getAllMappings()}.
     */
    public function getMappings(): array
    {
        return $this->getSettingsByGroup('mappings');
    }

    /**
     * Get all settings belonging to the same group.
     *
     * @param string $group
     *
     * @return array
     *   An array with all settings belonging to the given group.
     */
    protected function getSettingsByGroup(string $group): array
    {
        $result = [];
        foreach ($this->getKeyInfo() as $key => $keyInfo) {
            if ($keyInfo['group'] === $group) {
                $result[$key] = $this->get($key);
            }
        }
        return $result;
    }

    /**
     * Returns a list of keys that are stored in the shop specific config store.
     */
    public function getKeys(): array
    {
        return array_keys($this->getKeyInfo());
    }

    /**
     * Returns a set of default values for the various config settings.
     */
    public function getDefaults(): array
    {
        return array_merge($this->getConfigDefaults(), $this->getShopDefaults());
    }

    /**
     * Returns a set of default values for the various config settings.
     *
     * Not to be used in isolation, use geDefaults() instead.
     */
    protected function getConfigDefaults(): array
    {
        $result = $this->getKeyInfo();
        return array_map(static function ($item) {
            return $item['default'];
        }, $result);
    }

    /**
     * Returns a set of default values that are specific for the shop.
     *
     * Not to be used in isolation, use geDefaults() instead.
     */
    protected function getShopDefaults(): array
    {
        return $this->getShopCapabilities()->getDefaultShopConfig();
    }

    /**
     * Returns the hostname of the current request.
     *
     * The hostname is returned without www. so it can be used as domain name
     * in constructing e-mail addresses.
     */
    protected function getHostName(): string
    {
        $environment = $this->environment->get();
        return $environment['hostName'];
    }

    /**
     * Returns information (group and type) about the keys that are stored in the
     * store config.
     *
     * @return array
     *   A keyed array with information (group and type) about the keys that are
     *   stored in the store config.
     */
    protected function getKeyInfo(): array
    {
        if (!isset($this->keyInfo)) {
            $hostName = $this->getHostName();
            // remove TLD, like .com or .nl, from hostname.
            $pos = strrpos($hostName, '.');
            if ($pos !== false) {
                $hostName = substr($hostName, 0, $pos);
            }
            // As utf8 is now commonly accepted, it is a bit difficult to
            // express the set of characters that are allowed for email
            // addresses, so we remove characters not allowed.
            // See https://stackoverflow.com/a/2049537/1475662: @ ()[]\:;"<>,
            $hostName = str_replace([' ', '@', '(', ')', '[', ']', '\\', ':', ';', '"', '<', '>', ','], '', $hostName);

            $this->keyInfo = [
                // Keep track of the version of the config values. This allows
                // us to make changes to the config like reassigning values,
                // renaming config keys, etc. and be able to execute an update
                // function without being dependent on the host system to
                // provide the current "data model" version.
                Config::VersionKey => [
                    'group' => 'config',
                    'type' => 'string',
                    'default' => '',
                ],
                'debug' => [
                    'group' => 'plugin',
                    'type' => 'int',
                    'default' => Config::Send_SendAndMailOnError,
                ],
                'logLevel' => [
                    'group' => 'plugin',
                    'type' => 'int',
                    'default' => Severity::Notice,
                ],
                'outputFormat' => [
                    'group' => 'plugin',
                    'type' => 'string',
                    'default' => Api::outputFormat,
                ],
                Tag::ContractCode => [
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ],
                Tag::UserName => [
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ],
                Tag::Password => [
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ],
                Tag::EmailOnError => [
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ],
                'defaultCustomerType' => [
                    'group' => Tag::Customer,
                    'type' => 'int',
                    'default' => 0,
                ],
                'sendCustomer' => [
                    'group' => Tag::Customer,
                    'type' => 'bool',
                    'default' => true,
                ],
                'genericCustomerEmail' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => "consumer.$hostName@nul.sielsystems.nl",
                ],
                'emailIfAbsent' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => "$hostName@nul.sielsystems.nl",
                ],
                'contactYourId' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'contactStatus' => [
                    'group' => Tag::Customer,
                    'type' => 'int',
                    'default' => Api::ContactStatus_Active,
                ],
                'companyName1' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'companyName2' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'fullName' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'salutation' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'address1' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'address2' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'postalCode' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'city' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'vatNumber' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'telephone' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'fax' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'email' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'overwriteIfExists' => [
                    'group' => Tag::Customer,
                    'type' => 'bool',
                    'default' => true,
                ],
                'mark' => [
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ],
                'concept' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => Config::Concept_Plugin,
                ],
                'euCommerceThresholdPercentage' => [
                    'group' => Tag::Invoice,
                    'type' => 'float',
                    'default' => 95.0,
                ],
                'missingAmount' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => Config::MissingAmount_Warn,
                ],
                'defaultAccountNumber' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ],
                'defaultCostCenter' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ],
                'defaultInvoiceTemplate' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ],
                'defaultInvoicePaidTemplate' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ],
                'paymentMethodAccountNumber' => [
                    'group' => Tag::Invoice,
                    'type' => 'array',
                    'default' => [],
                ],
                'paymentMethodCostCenter' => [
                    'group' => Tag::Invoice,
                    'type' => 'array',
                    'default' => [],
                ],
                'sendEmptyShipping' => [
                    'group' => Tag::Invoice,
                    'type' => 'bool',
                    'default' => true,
                ],
                'optionsShow' => [
                    'group' => Tag::Invoice,
                    'type' => 'bool',
                    'default' => true,
                ],
                'optionsAllOn1Line' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 2,
                ],
                'optionsAllOnOwnLine' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 4,
                ],
                'optionsMaxLength' => [
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 80,
                ],
                'description' => [
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '[invoiceSourceType::label+invoiceSource::reference'
                        . '+"-"+refundedInvoiceSourceType::label+refundedInvoiceSource::reference]',
                ],
                'descriptionText' => [
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ],
                'invoiceNotes' => [
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ],
                'itemNumber' => [
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ],
                'productName' => [
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ],
                'nature' => [
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ],
                'costPrice' => [
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ],
                'nature_shop' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::Nature_Unknown,
                ],
                'marginProducts' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::MarginProducts_Unknown,
                ],
                'euVat' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::EuVat_Unknown,
                ],
                'vatFreeClass' => [
                    'group' => 'shop',
                    'type' => 'string',
                    'default' => '',
                ],
                'zeroVatClass' => [
                    'group' => 'shop',
                    'type' => 'string',
                    'default' => '',
                ],
                'invoiceNrSource' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::InvoiceNrSource_Acumulus,
                ],
                'dateToUse' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::IssueDateSource_InvoiceCreate,
                ],
                'triggerOrderStatus' => [
                    'group' => 'event',
                    'type' => 'array',
                    'default' => [],
                ],
                'triggerInvoiceEvent' => [
                    'group' => 'event',
                    'type' => 'int',
                    'default' => Config::TriggerInvoiceEvent_None,
                ],
                'triggerCreditNoteEvent' => [
                    'group' => 'event',
                    'type' => 'int',
                    'default' => Config::TriggerCreditNoteEvent_Create,
                ],
                'sendEmptyInvoice' => [
                    'group' => 'event',
                    'type' => 'bool',
                    'default' => true,
                ],
                'showInvoiceStatus' => [
                    'group' => 'status',
                    'type' => 'bool',
                    'default' => true,
                ],
                'showInvoiceDetail' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => true,
                ],
                'mailInvoiceDetail' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => false,
                ],
                'showPackingSlipDetail' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => true,
                ],
                'mailPackingSlipDetail' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => false,
                ],
                'showInvoiceList' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => true,
                ],
                'mailInvoiceList' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => false,
                ],
                'showPackingSlipList' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => true,
                ],
                'mailPackingSlipList' => [
                    'group' => 'documents',
                    'type' => 'bool',
                    'default' => false,
                ],
                'emailAsPdf' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'bool',
                    'default' => false,
                ],
                'emailFrom' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                'emailTo' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                'emailBcc' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                'subject' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                // For now, we do not make the invoice message configurable...
                // For now, we don't present the confirmReading option in the UI.
                'confirmReading' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'bool',
                    'default' => false,
                ],
                // For now, we don't present the packingSlipEmailFrom option in the UI.
                'packingSlipEmailFrom' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                'packingSlipEmailTo' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                'packingSlipEmailBcc' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                // For now, we don't present the packingSlipSubject option in the UI.
                'packingSlipSubject' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ],
                // For now, we do not make packing slip mail message configurable...
                // For now, we don't present the packingSlipConfirmReading option in the UI.
                'packingSlipConfirmReading' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'bool',
                    'default' => false,
                ],
                'showRatePluginMessage' => [
                    'group' => 'other',
                    'type' => 'int',
                    'default' => 0,
                ],
                Config::PropertyMappings => [
                    'group' => 'mappings',
                    'type' => 'array',
                    'default' => [
                        DataType::Invoice => [
                            'paymentStatus' => '[source::getPaymentStatus()]',
                            'paymentDate' => '[source::getPaymentDate()]',
                            'description' => '[source::getTypeLabel(2)+source::getReference()'
                                . '+"-"+source::getParent()::getTypeLabel(1)+source::getParent()::getReference()]',
                        ],
                        DataType::Customer => [
                        ],
                        AddressType::Invoice => [
                        ],
                        AddressType::Shipping => [
                        ],
                        EmailAsPdfType::Invoice => [
                            'subject' => 'Factuur voor [source::getTypeLabel(1)+source::getReference()'
                                . '+"-"+source::getParent()::getTypeLabel(1)+source::getParent()::getReference()]',
                        ],
                        EmailAsPdfType::PackingSlip => [
                            'subject' => 'Pakbon voor [source::getTypeLabel(1)+source::getReference()]',
                        ],
                        LineType::Item => [
                        ],
                    ],
                ],
                Config::MetadataMappings => [
                    'group' => 'mappings',
                    'type' => 'array',
                    'default' => [
                        DataType::Invoice => [
                            Meta::Type => '[source::getType()]',
                            Meta::Id => '[source::getId()]',
                            Meta::Reference => '[source::getReference()]',
                            Meta::Date => '[source::getDate()]',
                            Meta::Status => '[source::getStatus()]',
                            Meta::PaymentMethod => '[source::getPaymentMethod()]',
                            Meta::ShopInvoiceId => '[source::getShopInvoiceId()]',
                            Meta::ShopInvoiceReference => '[source::getShopInvoiceReference()]',
                            Meta::ShopInvoiceDate => '[source::getShopInvoiceDate()]',
                            Meta::Currency => '[source::getCurrency()]',
                            Meta::Totals => '[source::getTotals()]',
                        ],
                    ],
                ],
            ];
        }
        return $this->keyInfo;
    }
}
