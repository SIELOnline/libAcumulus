<?php
/**
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 */

namespace Siel\Acumulus\Config;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Tag;
use const Siel\Acumulus\Version;

/**
 * Provides uniform access to the settings of libAcumulus.
 *
 * Configuration is stored in the host environment bridged via the ConfigStore
 * class.
 *
 * This class also provides an {@see Config::update()) method to update the
 * stored config values when changes in these are made between versions.
 */
class Config
{
    const configVersion = 'configVersion';

    const Concept_Plugin = 2;

    const Send_SendAndMailOnError = 1;
    const Send_SendAndMail = 2;
    const Send_TestMode = 3;

    const MissingAmount_Ignore = 1;
    const MissingAmount_Warn = 2;
    const MissingAmount_AddLine = 3;

    const InvoiceNrSource_ShopInvoice = 1;
    const InvoiceNrSource_ShopOrder = 2;
    const InvoiceNrSource_Acumulus = 3;

    const InvoiceDate_InvoiceCreate = 1;
    const InvoiceDate_OrderCreate = 2;
    const InvoiceDate_Transfer = 3;

    const Nature_Unknown = 0;
    const Nature_Services = 1;
    const Nature_Products = 2;
    const Nature_Both = 3;

    const MarginProducts_Unknown = 0;
    const MarginProducts_Both = 1;
    const MarginProducts_No = 2;
    const MarginProducts_Only = 3;

    const VatClass_NotApplicable = 'vat_class_not_applicable';
    // Note: used both as value in Config and as value for Meta::VatClassId.
    const VatClass_Null = 'vat_class_null';

    const TriggerInvoiceEvent_None = 0;
    const TriggerInvoiceEvent_Create = 1;
    const TriggerInvoiceEvent_Send = 2;

    const TriggerCreditNoteEvent_None = 0;
    const TriggerCreditNoteEvent_Create = 1;

    /** @var \Siel\Acumulus\Config\ConfigStore */
    protected $configStore;

    /** @var \Siel\Acumulus\Config\ShopCapabilities */
    protected $shopCapabilities;

    /** @var \Siel\Acumulus\Helpers\Container */
    protected $container;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var array[]|null */
    protected $keyInfo;

    /** @var bool */
    protected $isConfigurationLoaded;

    /**
     * @var bool
     */
    protected $isUpgrading;

    /** @var array */
    protected $values;

    /**
     * Config constructor.
     *
     * @param \Siel\Acumulus\Config\ConfigStore $configStore
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(ConfigStore $configStore, ShopCapabilities $shopCapabilities, Container $container, Translator $translator, Log $log)
    {
        $this->configStore = $configStore;
        $this->shopCapabilities = $shopCapabilities;
        $this->container = $container;
        $this->translator = $translator;
        $this->log = $log;

        $this->keyInfo = null;
        $this->isConfigurationLoaded = false;
        $this->values = [];
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t($key)
    {
        return $this->translator->get($key);
    }

    /**
     * Wrapper getter around the config store object.
     *
     * @return \Siel\Acumulus\Config\ConfigStore
     */
    protected function getConfigStore()
    {
        return $this->configStore;
    }

    /**
     * Wrapper getter around the store capabilities object.
     *
     * @return \Siel\Acumulus\Config\ShopCapabilities
     */
    protected function getShopCapabilities()
    {
        return $this->shopCapabilities;
    }

    /**
     * Loads the configuration from the actual configuration provider.
     *
     * After loading this method checks if the stored values need an upgrade
     * and, if so, will trigger that update.
     */
    protected function load()
    {
        if (!$this->isConfigurationLoaded) {
            $this->values = $this->getDefaults();
            $values = $this->getConfigStore()->load();
            if (is_array($values)) {
                $this->values = array_merge($this->getDefaults(), $values);
            }
            $this->values = $this->castValues($this->values);
            $this->isConfigurationLoaded = true;

            if (!empty($this->values[Config::configVersion])
                && version_compare($this->values[Config::configVersion], Version, '<')
                && !$this->isUpgrading
            ) {
                $this->isUpgrading = true;
                $this->container->getConfigUpgrade()->upgrade($this->values[Config::configVersion]);
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
    public function save(array $values)
    {
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
     *
     * @return array
     *   Array with cast values.
     */
    protected function castValues(array $values)
    {
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
                            $values[$key] =  $values[$key] === '' ? '' : (bool) $values[$key];
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
    protected function removeValuesNotToBeStored(array $values)
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
     * @return int
     *
     * @noinspection PhpUnused
     */
    public function getShowRatePluginMessage()
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
    public function get($key)
    {
        $this->load();
        return $this->values[$key] ?? null;
    }

    /**
     * Sets the internal value of the specified configuration key.
     *
     * This value will not be stored, use save() for that.
     *
     * @param string $key
     *   The configuration value to set.
     * @param mixed $value
     *   The new value for the configuration key.
     *
     * @return mixed
     *   The old value.
     */
    public function set($key, $value)
    {
        $this->load();
        $oldValue = $this->values[$key] ?? null;
        $this->values[$key] = $value;
        return $oldValue;
    }

    /**
     * Returns information about the environment of this library.
     *
     * @return array
     *   A keyed array with information about the environment of this library:
     *   - baseUri
     *   - apiVersion
     *   - libraryVersion
     *   - moduleVersion
     *   - shopName
     *   - shopVersion
     *   - hostName
     *   - phpVersion
     *   - os
     *   - curlVersion
     *   - jsonVersion
     */
    public function getEnvironment()
    {
        return $this->getSettingsByGroup('environment');
    }

    /**
     * Returns the contract credentials to authenticate with the Acumulus API.
     *
     * @return array
     *   A keyed array with the keys:
     *   - contractcode
     *   - username
     *   - password
     *   - emailonerror
     *   - emailonwarning
     */
    public function getCredentials()
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
     *   - debug
     *   - logLevel
     *   - outputFormat
     */
    public function getPluginSettings()
    {
        return $this->getSettingsByGroup('plugin');
    }

    /**
     * Returns the set of settings related to reacting to shop events.
     *
     * @return array
     *   A keyed array with the keys:
     *   - triggerOrderStatus
     *   - triggerInvoiceEvent
     *   - triggerCreditNoteEvent
     *   - sendEmptyInvoice
     */
    public function getShopEventSettings()
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
     *   - marginProducts
     *   - euVatClasses
     *   - vatFreeClass
     *   - zeroVatClass
     *   - invoiceNrSource
     *   - dateToUse
     */
    public function getShopSettings()
    {
        return $this->getSettingsByGroup('shop');
    }

    /**
     * Returns the set of settings related to the customer part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - sendCustomer
     *   - overwriteIfExists
     *   - defaultCustomerType
     *   - contactStatus
     *   - contactYourId
     *   - companyName1
     *   - companyName2
     *   - vatNumber
     *   - fullName
     *   - salutation
     *   - address1
     *   - address2
     *   - postalCode
     *   - city
     *   - telephone
     *   - fax
     *   - email
     *   - mark
     *   - genericCustomerEmail
     */
    public function getCustomerSettings()
    {
        return $this->getSettingsByGroup(Tag::Customer);
    }

    /**
     * Returns the set of settings related to the invoice part of an invoice.
     *
     * @return array
     *   A keyed array with the keys:
     *   - concept
     *   - euCommerceThresholdPercentage
     *   - missingAmount
     *   - defaultAccountNumber
     *   - defaultCostCenter
     *   - defaultInvoiceTemplate
     *   - defaultInvoicePaidTemplate
     *   - paymentMethodAccountNumber
     *   - paymentMethodCostCenter
     *   - sendEmptyShipping
     *   - description
     *   - descriptionText
     *   - invoiceNotes
     *   - optionsShow
     *   - optionsAllOn1Line
     *   - optionsAllOnOwnLine
     *   - optionsMaxLength
     *   - itemNumber
     *   - productName
     *   - nature
     *   - costPrice
     */
    public function getInvoiceSettings()
    {
        return $this->getSettingsByGroup(Tag::Invoice);
    }

    /**
     * Returns the set of settings related to sending an email.
     *
     * @return array
     *   A keyed array with the keys:
     *   - emailAsPdf
     *   - emailBcc
     *   - emailFrom
     *   - subject
     *   - confirmReading
     */
    public function getEmailAsPdfSettings()
    {
        return $this->getSettingsByGroup(Tag::EmailAsPdf);
    }

    /**
     * Returns the set of settings related to the invoice status tab/box.
     *
     * @return array
     *   A keyed array with the keys:
     *   - showInvoiceStatus
     *   - showPdfInvoice
     *   - showPdfPackingSlip
     */
    public function getInvoiceStatusSettings()
    {
        return $this->getSettingsByGroup('status');
    }

    /**
     * Get all settings belonging to the same group.
     *
     * @param string $group
     *
     * @return array
     *   An array of settings.
     */
    protected function getSettingsByGroup($group)
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
     *
     * @return array
     */
    public function getKeys()
    {
        $result = $this->getKeyInfo();
        array_filter($result, function ($item) {
            return $item['group'] !== 'environment';
        });
        return array_keys($result);
    }

    /**
     * Returns a set of default values for the various config settings.
     *
     * @return array
     */
    public function getDefaults()
    {
        return array_merge($this->getConfigDefaults(), $this->getShopDefaults());
    }

    /**
     * Returns a set of default values for the various config settings.
     *
     * Not to be used in isolation, use geDefaults() instead.
     *
     * @return array
     */
    protected function getConfigDefaults()
    {
        $result = $this->getKeyInfo();
        return array_map(function ($item) {
            return $item['default'];
        }, $result);
    }

    /**
     * Returns a set of default values that are specific for the shop.
     *
     * Not to be used in isolation, use geDefaults() instead.
     *
     * @return array
     */
    protected function getShopDefaults()
    {
        return $this->getShopCapabilities()->getShopDefaults();
    }

    /**
     * Returns the hostname of the current request.
     *
     * The hostname is returned without www. so it can be used as domain name
     * in constructing e-mail addresses.
     *
     * @return string
     *   The hostname of the current request.
     */
    protected function getHostName()
    {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $hostName = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);
        }
        if (!empty($hostName)) {
            if (($pos = strpos($hostName, 'www.')) !== false) {
                $hostName = substr($hostName, $pos + strlen('www.'));
            }
        } else {
            $hostName = 'example.com';
        }
        return $hostName;
    }

    /**
     * Returns information (group and type) about the keys that are stored in the
     * store config.
     *
     * @return array
     *   A keyed array with information (group and type) about the keys that are
     *   stored in the store config.
     */
    protected function getKeyInfo()
    {
        if ($this->keyInfo === null) {
            $curlVersion = curl_version();
            $environment = $this->getShopCapabilities()->getShopEnvironment();
            $hostName = $this->getHostName();
            // remove TLD.
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
                Config::configVersion => [
                    'group' => 'config',
                    'type' => 'string',
                    'default' => '',
                ],
                'baseUri' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => Api::baseUri,
                ],
                'apiVersion' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => Api::apiVersion,
                ],
                'libraryVersion' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => Version,
                ],
                'moduleVersion' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $environment['moduleVersion'],
                ],
                'shopName' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $environment['shopName'],
                ],
                'shopVersion' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $environment['shopVersion'],
                ],
                'hostName' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $this->getHostName(),
                ],
                'phpVersion' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => phpversion(),
                ],
                'os' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => php_uname(),
                ],
                'curlVersion' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => "{$curlVersion['version']} (ssl: {$curlVersion['ssl_version']}; zlib: {$curlVersion['libz_version']})",
                ],
                'jsonVersion' => [
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => phpversion('json'),
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
                    'default' => '[invoiceSource::type+invoiceSource::reference+"-"+refundedInvoiceSource::type+refundedInvoiceSource::reference]',
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
                'euVatClasses' => [
                    'group' => 'shop',
                    'type' => 'array',
                    'default' => [],
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
                    'default' => Config::InvoiceNrSource_ShopInvoice,
                ],
                'dateToUse' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::InvoiceDate_InvoiceCreate,
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
                // For now, we do not make message configurable...
                // For now, we don't present the confirmReading option in the UI.
                'confirmReading' => [
                    'group' => Tag::EmailAsPdf,
                    'type' => 'bool',
                    'default' => false,
                ],
                'showInvoiceStatus' => [
                    'group' => 'status',
                    'type' => 'bool',
                    'default' => true,
                ],
                'showPdfInvoice' => [
                    'group' => 'status',
                    'type' => 'bool',
                    'default' => true,
                ],
                'showPdfPackingSlip' => [
                    'group' => 'status',
                    'type' => 'bool',
                    'default' => true,
                ],
                'showRatePluginMessage' => [
                    'group' => 'other',
                    'type' => 'int',
                    'default' => 0,
                ],
            ];
        }
        return $this->keyInfo;
    }
}
