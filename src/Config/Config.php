<?php
namespace Siel\Acumulus\Config;

use Siel\Acumulus\Api;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Tag;

/**
 * Gives common code in this package uniform access to the settings for this
 * extension, hiding all the web shop specific implementations of their config
 * store.
 *
 * This class implements all <...ConfigInterface>s and makes, via a ConfigStore,
 * use of the shop specific configuration functionality to store this
 * configuration in a persistent way.
 *
 * This class als implements the injector interface to allow other classes to
 * easily get the correct derived classes of the base classes.
 */
class Config implements ConfigInterface
{
    /** @var \Siel\Acumulus\Config\ConfigStoreInterface */
    protected $configStore;

    /** @var \Siel\Acumulus\Config\ShopCapabilitiesInterface */
    protected $shopCapabilities;

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
     * @param \Siel\Acumulus\Config\ConfigStoreInterface $configStore
     * @param \Siel\Acumulus\Config\ShopCapabilitiesInterface $shopCapabilities
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(ConfigStoreInterface $configStore, ShopCapabilitiesInterface $shopCapabilities, Translator $translator, Log $log)
    {
        $this->configStore = $configStore;
        $this->shopCapabilities = $shopCapabilities;
        $this->translator = $translator;
        $this->log = $log;

        $this->keyInfo = null;
        $this->isConfigurationLoaded = false;
        $this->values = array();
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
     * @return \Siel\Acumulus\Config\ConfigStoreInterface
     */
    protected function getConfigStore()
    {
        return $this->configStore;
    }

    /**
     * Wrapper getter around the store capabilities object.
     *
     * @return \Siel\Acumulus\Config\ShopCapabilitiesInterface
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
            $this->values = $this->castValues(array_merge($this->getDefaults(), $this->getConfigStore()->load($this->getKeys())));
            $this->isConfigurationLoaded = true;
        }
    }

    /**
     * {@inheritdoc}
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

        $values = $this->castValues($values);
        $result = $this->getConfigStore()->save($values);
        $this->isConfigurationLoaded = false;
        // Sync internal values.
        $this->load();
        return $result;
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
     * @inheritdoc
     */
    public function set($key, $value)
    {
        $this->load();
        $oldValue = isset($this->values[$key]) ? $this->values[$key] : null;
        $this->values[$key] = $value;
        return $oldValue;
    }

    /**
     * @inheritdoc
     */
    public function getEnvironment()
    {
        return $this->getSettingsByGroup('environment');
    }

    /**
     * @inheritdoc
     */
    public function getCredentials()
    {
        $result = $this->getSettingsByGroup('credentials');
        // No separate key for now.
        $result[Tag::EmailOnWarning] = $result[Tag::EmailOnError];
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getShopEventSettings()
    {
        return $this->getSettingsByGroup('event');
    }

    /**
     * @inheritdoc
     */
    public function getPluginSettings()
    {
        return $this->getSettingsByGroup('plugin');
    }

    /**
     * @inheritdoc
     */
    public function getCustomerSettings()
    {
        return $this->getSettingsByGroup(Tag::Customer);
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceSettings()
    {
        return $this->getSettingsByGroup(Tag::Invoice);
    }

    /**
     * @inheritdoc
     */
    public function getShopSettings()
    {
        return $this->getSettingsByGroup('shop');
    }

    /**
     * @inheritdoc
     */
    public function getEmailAsPdfSettings()
    {
        return $this->getSettingsByGroup(Tag::EmailAsPdf);
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
        $result = array();
        foreach ($this->getKeyInfo() as $key => $keyInfo) {
            if ($keyInfo['group'] === $group) {
                $result[$key] = $this->get($key);
            }
        }
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
        foreach ($this->getKeyInfo() as $key => $keyInfo) {
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
                            $values[$key] = array($values[$key]);
                        }
                        break;
                }
            }
        }
        return $values;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
            // As utf8 is now commonly accepted, it is a bit difficult to
            // express the set of characters that are allowed for email
            // addresses, so we remove characters not allowed.
            // See http://stackoverflow.com/a/2049537/1475662: @ ()[]\:;"<>,
            $shopName = str_replace(array(' ', '@', '(', ')', '[', ']', '\\', ':', ';', '"', '<', '>', ','), '', $environment['shopName']);
            $this->keyInfo = array(
                'baseUri' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => Api::baseUri,
                ),
                'apiVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => Api::apiVersion,
                ),
                'libraryVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => PluginConfig::Version,
                ),
                'moduleVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $environment['moduleVersion'],
                ),
                'shopName' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $environment['shopName'],
                ),
                'shopVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $environment['shopVersion'],
                ),
                'hostName' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $this->getHostName(),
                ),
                'phpVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => phpversion(),
                ),
                'os' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => php_uname(),
                ),
                'curlVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => "{$curlVersion['version']} (ssl: {$curlVersion['ssl_version']}; zlib: {$curlVersion['libz_version']})",
                ),
                'jsonVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => phpversion('json'),
                ),
                'debug' => array(
                    'group' => 'plugin',
                    'type' => 'int',
                    'default' => PluginConfig::Send_SendAndMailOnError,
                ),
                'logLevel' => array(
                    'group' => 'plugin',
                    'type' => 'int',
                    'default' => Log::Notice,
                ),
                'outputFormat' => array(
                    'group' => 'plugin',
                    'type' => 'string',
                    'default' => Api::outputFormat,
                ),
                Tag::ContractCode => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                Tag::UserName => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                Tag::Password => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                Tag::EmailOnError => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                'defaultCustomerType' => array(
                    'group' => Tag::Customer,
                    'type' => 'int',
                    'default' => 0,
                ),
                'sendCustomer' => array(
                    'group' => Tag::Customer,
                    'type' => 'bool',
                    'default' => true,
                ),
                'genericCustomerEmail' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => "consumer.$shopName@nul.sielsystems.nl",
                ),
                'emailIfAbsent' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => $shopName . '@nul.sielsystems.nl',
                ),
                'contactYourId' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'contactStatus' => array(
                    'group' => Tag::Customer,
                    'type' => 'int',
                    'default' => Api::ContactStatus_Active,
                ),
                'companyName1' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'companyName2' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'fullName' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'salutation' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'address1' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'address2' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'postalCode' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'city' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'vatNumber' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'telephone' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'fax' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'email' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'overwriteIfExists' => array(
                    'group' => Tag::Customer,
                    'type' => 'bool',
                    'default' => true,
                ),
                'mark' => array(
                    'group' => Tag::Customer,
                    'type' => 'string',
                    'default' => '',
                ),
                'concept' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => PluginConfig::Concept_Plugin,
                ),
                'defaultAccountNumber' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ),
                'defaultCostCenter' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ),
                'defaultInvoiceTemplate' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ),
                'defaultInvoicePaidTemplate' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 0,
                ),
                'paymentMethodAccountNumber' => array(
                    'group' => Tag::Invoice,
                    'type' => 'array',
                    'default' => array(),
                ),
                'paymentMethodCostCenter' => array(
                    'group' => Tag::Invoice,
                    'type' => 'array',
                    'default' => array(),
                ),
                'sendEmptyShipping' => array(
                    'group' => Tag::Invoice,
                    'type' => 'bool',
                    'default' => true,
                ),
                // @todo: add to advanced UI?
                'addMissingAmountLine' => array(
                    'group' => Tag::Invoice,
                    'type' => 'bool',
                    'default' => true,
                ),
                // @todo: add to UI if shop does support it (PS?, WC?).
                'useMargin' => array(
                    'group' => Tag::Invoice,
                    'type' => 'bool',
                    'default' => false,
                ),
                'optionsShow' => array(
                    'group' => Tag::Invoice,
                    'type' => 'bool',
                    'default' => true,
                ),
                'optionsAllOn1Line' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 2,
                ),
                'optionsAllOnOwnLine' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 4,
                ),
                'optionsMaxLength' => array(
                    'group' => Tag::Invoice,
                    'type' => 'int',
                    'default' => 80,
                ),
                'description' => array(
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '[invoiceSource::type] [invoiceSource::reference]',
                ),
                'descriptionText' => array(
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ),
                'invoiceNotes' => array(
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ),
                'itemNumber' => array(
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ),
                'productName' => array(
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ),
                'nature' => array(
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ),
                'costPrice' => array(
                    'group' => Tag::Invoice,
                    'type' => 'string',
                    'default' => '',
                ),
                'digitalServices' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => PluginConfig::DigitalServices_Unknown,
                ),
                'vatFreeProducts' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => PluginConfig::VatFreeProducts_Unknown,
                ),
                'invoiceNrSource' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => PluginConfig::InvoiceNrSource_ShopInvoice,
                ),
                'dateToUse' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => PluginConfig::InvoiceDate_InvoiceCreate,
                ),
                'triggerOrderStatus' => array(
                    'group' => 'event',
                    'type' => 'array',
                    'default' => array(),
                ),
                'triggerInvoiceEvent' => array(
                    'group' => 'event',
                    'type' => 'int',
                    'default' => PluginConfig::TriggerInvoiceEvent_None,
                ),
                'sendEmptyInvoice' => array(
                    'group' => 'event',
                    'type' => 'bool',
                    'default' => true,
                ),
                'emailAsPdf' => array(
                    'group' => Tag::EmailAsPdf,
                    'type' => 'bool',
                    'default' => false,
                ),
                'emailFrom' => array(
                  'group' => Tag::EmailAsPdf,
                  'type' => 'string',
                  'default' => '',
                ),
                'emailTo' => array(
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ),
                'emailBcc' => array(
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ),
                'subject' => array(
                    'group' => Tag::EmailAsPdf,
                    'type' => 'string',
                    'default' => '',
                ),
                // For now, we do not make message configurable...
                // For now we don't present the confirmReading option in the UI.
                'confirmReading' => array(
                    'group' => Tag::EmailAsPdf,
                    'type' => 'bool',
                    'default' => false,
                ),
            );
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
        $newSettings = array();

        // 1) Log level.
        switch ($this->get('logLevel')) {
            case Log::Error:
            case Log::Warning:
                // This is often not giving enough information, so we set it
                // to Notice by default.
                $newSettings['logLevel'] = Log::Notice;
                break;
            case Log::Info:
                // Info was inserted, so this is the former debug level.
                $newSettings['logLevel'] = Log::Debug;
                break;
        }

        // 2) Debug mode.
        switch ($this->get('debug')) {
            case 4: // Value for deprecated ServiceConfigInterfacePlugin::Debug_StayLocal.
                $newSettings['logLevel'] = PluginConfig::Send_TestMode;
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
     * @todo: extract updates into own object.
     *
     * @return bool
     */
    protected function upgrade453()
    {
        // Get current values.
        $values = $this->castValues($this->getConfigStore()->load($this->getKeys()));
        if ($this->get('triggerInvoiceSendEvent') == 2) {
            $values['triggerInvoiceEvent'] = PluginConfig::TriggerInvoiceEvent_Create;
        } else {
            $values['triggerInvoiceEvent'] = PluginConfig::TriggerInvoiceEvent_None;
        }
        unset($values['triggerInvoiceSendEvent']);

        return $this->getConfigStore()->save($values);
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
        // Get current values.
        $values = $this->castValues($this->getConfigStore()->load($this->getKeys()));
        if ($this->get('removeEmptyShipping') !== null) {
            $values['sendEmptyShipping'] = !$this->get('removeEmptyShipping');
            unset($values['removeEmptyShipping']);
        }

        return $this->getConfigStore()->save($values);
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

        // Get current values.
        $values = $this->castValues($this->getConfigStore()->load($this->getKeys()));
        if (!empty($values['salutation']) && strpos($values['salutation'], '[#') !== false) {
            $values['salutation'] = str_replace('[#', '[', $values['salutation']);
            $result = $this->getConfigStore()->save($values);
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

        // Get current values.
        $values = $this->castValues($this->getConfigStore()->load($this->getKeys()));
        if (!empty($values['subject']) && strpos($values['subject'], '[#') !== false) {
            str_replace('[#b]', '[invoiceSource::reference]', $values['subject']);
            str_replace('[#f]', '[invoiceSource::invoiceNumber]', $values['subject']);
            $result = $this->getConfigStore()->save($values);
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
}
