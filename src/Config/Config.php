<?php
namespace Siel\Acumulus\Config;

use Exception;
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
    const Nature_Both = 1;
    const Nature_Products = 2;
    const Nature_Services = 3;

    const ForeignVat_Unknown = 0;
    const ForeignVat_Both = 1;
    const ForeignVat_No = 2;
    const ForeignVat_Only = 3;

    const VatFreeProducts_Unknown = 0;
    const VatFreeProducts_Both = 1;
    const VatFreeProducts_No = 2;
    const VatFreeProducts_Only = 3;

    // Note: used both as value in Config and as value for Meta::VatClassId.
    const VatClass_Null = 'vat_class_null';

    const ZeroVatProducts_Unknown = 0;
    const ZeroVatProducts_Both = 1;
    const ZeroVatProducts_No = 2;
    const ZeroVatProducts_Only = 3;

    const MarginProducts_Unknown = 0;
    const MarginProducts_Both = 1;
    const MarginProducts_No = 2;
    const MarginProducts_Only = 3;

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
     *   Array with casted values.
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
                            $values[$key] = (int) $values[$key];
                        }
                        break;
                    case 'bool':
                        if (!is_bool($values[$key])) {
                            $values[$key] = (bool) $values[$key];
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
     *   The passed in set of values reduced to values that should be stored.
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
    protected function get($key)
    {
        $this->load();
        return isset($this->values[$key]) ? $this->values[$key] : null;
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
        $oldValue = isset($this->values[$key]) ? $this->values[$key] : null;
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
     *   - foreignVat
     *   - foreignVatClasses
     *   - vatFreeProducts
     *   - vatFreeClass
     *   - zeroVatProducts
     *   - zeroVatClasses
     *   - marginProducts
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
     *   - defaultAccountNumber
     *   - defaultCostCenter
     *   - defaultInvoiceTemplate
     *   - defaultInvoicePaidTemplate
     *   - paymentMethodAccountNumber
     *   - paymentMethodCostCenter
     *   - sendEmptyInvoice
     *   - sendEmptyShipping
     *   - description
     *   - descriptionText
     *   - invoiceNotes
     *   - optionsShow
     *   - optionsAllOn1Line
     *   - optionsAllOnOwnLine
     *   - optionsMaxLength
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
        $result = array_map(function ($item) {
            return $item['default'];
        }, $result);
        return $result;
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
            if ($pos = strpos($hostName, 'www.') !== false) {
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
                'foreignVat' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::ForeignVat_Unknown,
                ],
                'foreignVatClasses' => [
                    'group' => 'shop',
                    'type' => 'array',
                    'default' => [],
                ],
                'vatFreeProducts' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::VatFreeProducts_Unknown,
                ],
                'vatFreeClass' => [
                    'group' => 'shop',
                    'type' => 'string',
                    'default' => '',
                ],
                'zeroVatProducts' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::ZeroVatProducts_Unknown,
                ],
                'zeroVatClass' => [
                    'group' => 'shop',
                    'type' => 'string',
                    'default' => '',
                ],
                'marginProducts' => [
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => Config::MarginProducts_Unknown,
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
                // For now we don't present the confirmReading option in the UI.
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

    /**
     * Upgrade the datamodel to the given version.
     *
     * This method is only called when the module gets updated.
     *
     * @param string $currentVersion
     *   The current version of the module.
     *
     * @return bool
     *   Success.
     *
     * @throws \Exception
     */
    public function upgrade($currentVersion)
    {
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
    protected function upgrade450()
    {
        $result = true;
        // Keep track of settings that should be updated.
        $newSettings = [];

        // 1) Log level.
        switch ($this->get('logLevel')) {
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
        switch ($this->get('debug')) {
            case 4: // Value for deprecated PluginConfig::Debug_StayLocal.
                $newSettings['logLevel'] = Config::Send_TestMode;
                break;
        }

        if (!empty($newSettings)) {
            $result = $this->save($newSettings);
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
    protected function upgrade453()
    {
        // Keep track of settings that should be updated.
        $newSettings = [];
        if ($this->get('triggerInvoiceSendEvent') == 2) {
            $newSettings['triggerInvoiceEvent'] = Config::TriggerInvoiceEvent_Create;
        } else {
            $newSettings['triggerInvoiceEvent'] = Config::TriggerInvoiceEvent_None;
        }

        return $this->save($newSettings);
    }

    /**
     * 4.6.0 upgrade.
     *
     * - setting removeEmptyShipping inverted.
     *
     * @return bool
     */
    protected function upgrade460()
    {
        $result = true;
        $newSettings = [];

        if ($this->get('removeEmptyShipping') !== null) {
            $newSettings['sendEmptyShipping'] = !$this->get('removeEmptyShipping');
        }

        if (!empty($newSettings)) {
            $result = $this->save($newSettings);
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
    protected function upgrade470()
    {
        $result = true;
        $newSettings = [];

        if ($this->get('salutation') && strpos($this->get('salutation'), '[#') !== false) {
            $newSettings['salutation'] = str_replace('[#', '[', $this->get('salutation'));
        }

        if (!empty($newSettings)) {
            $result = $this->save($newSettings);
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
    protected function upgrade473()
    {
        $result = true;
        $newSettings = [];

        if ($this->get('subject') && strpos($this->get('subject'), '[#') !== false) {
            str_replace('[#b]', '[invoiceSource::reference]', $this->get('subject'));
            str_replace('[#f]', '[invoiceSource::invoiceNumber]', $this->get('subject'));
        }

        if (!empty($newSettings)) {
            $result = $this->save($newSettings);
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
    protected function upgrade496()
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
    protected function upgrade540()
    {
        $result = true;

        // ConfigStore::save should store all settings in 1 serialized value.
        $configStore = $this->getConfigStore();
        if (method_exists($configStore, 'loadOld')) {
            /** @noinspection PhpDeprecationInspection */
            $values = $configStore->loadOld($this->getKeys());
            $result = $this->save($values);
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
    protected function upgrade541()
    {
        $result = true;
        $doSave = false;
        $configStore = $this->getConfigStore();
        $values = $configStore->load();
        array_walk_recursive($values, function(&$value) use (&$doSave) {
            if (is_string($value) && strpos($value, 'originalInvoiceSource::') !== false) {
                $value = str_replace('originalInvoiceSource::', 'order::', $value);
                $doSave = true;
            }
        });
        if ($doSave) {
            $result = $this->save($values);
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
    protected function upgrade542()
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
            $result = $this->save($values);
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
    protected function upgrade550()
    {
        $newSettings = [];
        $newSettings['foreignVat'] = (int) $this->get('digitalServices');
        return $this->save($newSettings);
    }

    /**
     * 6.0.0 upgrade.
     *
     * - Log level is now a Severity constant.
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function upgrade600()
    {
        $requirements = $this->container->getRequirements();
        $messages = $requirements->check();
        foreach ($messages as $message) {
            $this->container->getLog()->error($message);
        }
        if (!empty($messages)) {
            throw new Exception(implode(';', $messages));
        }

        $newSettings = [];
        switch ($this->get('logLevel')) {
            case 3 /*Log::Notice*/:
                $newSettings['logLevel'] = Severity::Notice;
                break;
            case 4 /*Log::Info*/:
            default:
                $newSettings['logLevel'] = Severity::Info;
                break;
            case 5 /*Log::Debug*/:
                $newSettings['logLevel'] = Severity::Log;
                break;
        }
        return $this->save($newSettings);
    }
}
