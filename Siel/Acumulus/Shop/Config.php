<?php
namespace Siel\Acumulus\Shop;

use ReflectionClass;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Web\ConfigInterface as ServiceConfigInterface;

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
    /** @const string */
    const baseNamespace = '\\Siel\\Acumulus\\';

    /** @var array[]|null */
    protected $keyInfo;

    /** @var bool */
    protected $isConfigurationLoaded;

    /** @var bool */
    protected $moduleSpecificTranslationsAdded = false;

    /** @var array */
    protected $values;

    /** @var array */
    protected $instances;

    /** @var string The namespace for the current shop. */
    protected $shopNamespace;

    /** @var string The namespace for customisations on top of the current shop. */
    protected $customNamespace;

    /** @var string The language to display texts in. */
    protected $language;

    /**
     * Constructor.
     *
     * @param string $shopNamespace
     * @param string $language
     *   A language or locale code, e.g. nl, nl-NL, or en-UK.
     */
    public function __construct($shopNamespace, $language)
    {
        $this->keyInfo = null;
        $this->isConfigurationLoaded = false;
        $this->values = array();
        $this->instances = array();
        $this->shopNamespace = static::baseNamespace . $shopNamespace;
        global $sielAcumulusCustomNamespace;
        $this->customNamespace = !empty($sielAcumulusCustomNamespace) ? $sielAcumulusCustomNamespace : '';
        $this->language = substr($language, 0, 2);
        $this->getLog();
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
        return $this->getTranslator()->get($key);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomNamespace($customNamespace)
    {
        $this->customNamespace = $customNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslator()
    {
        /** @var Translator $translator */
        $translator = $this->getInstance('Translator', 'Helpers', array($this->language));
        if (!$this->moduleSpecificTranslationsAdded) {
            try {
                /** @var \Siel\Acumulus\Helpers\TranslationCollection $translations */
                $translations = $this->getInstance('ModuleSpecificTranslations', 'helpers');
                $translator->add($translations);
            } catch (\InvalidArgumentException $e) {}
            $this->moduleSpecificTranslationsAdded = true;
        }
        return $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getLog()
    {
        return $this->getInstance('Log', 'Helpers', array($this));
    }

    /**
     * {@inheritdoc}
     */
    public function getMailer()
    {
        return $this->getInstance('Mailer', 'Helpers', array($this, $this->getTranslator(), $this->getService()));
    }

    /**
     * {@inheritdoc}
     */
    public function getToken()
    {
        return $this->getInstance('Token', 'Helpers');
    }

    /**
     * {@inheritdoc}
     */
    public function getForm($type)
    {
        $class = ucfirst($type);
        $arguments = array(
            $this->getTranslator(),
            $this->getShopCapabilities(),
        );
        switch (strtolower($type)) {
            case 'config':
                $arguments[] = $this;
                $arguments[] = $this->getService();
                break;
            case 'advanced':
                $class = 'AdvancedConfig';
                $arguments[] = $this;
                $arguments[] = $this->getService();
                break;
            case 'batch':
                $arguments[] = $this->getManager();
                break;
        }
        return $this->getInstance($class . 'Form', 'Shop', $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormRenderer()
    {
        return $this->getInstance('FormRenderer', 'Helpers');
    }

    /**
     * {@inheritdoc}
     */
    public function getService()
    {
        return $this->getInstance('Service', 'Web', array($this->getCommunicator(), $this, $this->getTranslator()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCommunicator()
    {
        return $this->getInstance('Communicator', 'Web', array($this));
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($invoiceSourceType, $invoiceSourceOrId)
    {
        return $this->getInstance('Source', 'Invoice', array($invoiceSourceType, $invoiceSourceOrId), true);
    }

    /**
     * {@inheritdoc}
     */
    public function getCompletor()
    {
        return $this->getInstance('Completor', 'Invoice', array($this, $this->getCompletorInvoiceLines(), $this->getCompletorStrategyLines(), $this->getTranslator(), $this->getService()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCompletorInvoiceLines()
    {
        return $this->getInstance('CompletorInvoiceLines', 'Invoice', array($this, $this->getFlattenerInvoiceLines()));
    }

    /**
     * {@inheritdoc}
     */
    public function getFlattenerInvoiceLines()
    {
        return $this->getInstance('FlattenerInvoiceLines', 'Invoice', array($this));
    }

    /**
     * {@inheritdoc}
     */
    public function getCompletorStrategyLines()
    {
        return $this->getInstance('CompletorStrategyLines', 'Invoice', array($this, $this->getTranslator()));
    }

    /**
     * {@inheritdoc}
     */
    public function getCreator()
    {
        return $this->getInstance('Creator', 'Invoice', array($this, $this->getTranslator()));
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigStore()
    {
        return $this->getInstance('ConfigStore', 'Shop', array($this, $this->shopNamespace));
    }

    /**
     * {@inheritdoc}
     */
    public function getShopCapabilities()
    {
        return $this->getInstance('ShopCapabilities', 'Shop', array($this->getTranslator()));
    }

    /**
     * {@inheritdoc}
     */
    public function getManager()
    {
        return $this->getInstance('InvoiceManager', 'Shop', array($this));
    }

    /**
     * {@inheritdoc}
     */
    public function getAcumulusEntryModel()
    {
        return $this->getInstance('AcumulusEntryModel', 'Shop');
    }

    /**
     * Returns an instance of the given class.
     *
     * The class is taken from the same namespace as the configStore property.
     * Only 1 instance is created per class.
     *
     * @param string $class
     *   The name of the class without namespace. The namespace is taken from the
     *   configStore object.
     * @param string $subNamespace
     *   The sub namespace (within the shop namespace) in which the class resides.
     * @param array $constructorArgs
     *   An array of arguments to pass to the constructor, may be an empty array.
     * @param bool $newInstance
     *   Whether to create a new instance or reuse an already existing instance
     *
     * @return object
     *
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     */
    protected function getInstance($class, $subNamespace, array $constructorArgs = array(), $newInstance = false)
    {
        if (!isset($this->instances[$class]) || $newInstance) {
            // Try custom namespace.
            if (!empty($this->customNamespace)) {
                $fqClass = $this->tryNsInstance($class, $subNamespace, $this->customNamespace);
            }

            // Try the shop namespace and any parent namespaces.
            $namespaces = explode('\\', $this->shopNamespace);
            while (empty($fqClass) && !empty($namespaces)) {
                $namespace = implode('\\', $namespaces);
                $fqClass = $this->tryNsInstance($class, $subNamespace, $namespace);
                array_pop($namespaces);
            }

            if (empty($fqClass)) {
                throw new \InvalidArgumentException("Class $class not found in namespace $subNamespace");
            }

            // Create a new instance.
            // As PHP5.3 produces a fatal error when a class has no constructor
            // and newInstanceArgs() is called, we have to differentiate between
            // no arguments and arguments.
            if (empty($constructorArgs)) {
                $this->instances[$class] = new $fqClass();
            } else {
                $reflector = new ReflectionClass($fqClass);
                $this->instances[$class] = $reflector->newInstanceArgs($constructorArgs);
            }
        }
        return $this->instances[$class];
    }

    protected function tryNsInstance($class, $subNamespace, $namespace)
    {
        $fqClass = $namespace . '\\' . $subNamespace . '\\' . $class;
        $fileName = __DIR__ . DIRECTORY_SEPARATOR . '..' . str_replace('\\', DIRECTORY_SEPARATOR, substr($fqClass, strlen('/Siel/Acumulus'))) . '.php';
        // Checking if the file exists prevents warnings in Magento whose own
        // autoloader logs warnings when a class cannot be loaded.
        return is_readable($fileName) && class_exists($fqClass) ? $fqClass : '';
    }

    /**
     * Returns the namespace for the current cms.
     *
     * @return string
     *   The namespace for the current cms or the empty string if the current shop
     *   is not contained in a CMS namespace.
     */
    protected function getCmsNamespace()
    {
        // Get parent namespace of the shop namespace.
        $cmsNamespaceEnd = strrpos($this->shopNamespace, '\\');
        $cmsNamespace = substr($this->shopNamespace, 0, (int) $cmsNamespaceEnd);
        // But if that is Acumulus there's no CMS namespace.
        if (substr($cmsNamespace, -strlen('\\Acumulus')) === '\\Acumulus') {
            $cmsNamespace = '';
        }
        return $cmsNamespace;
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
        if (!empty($copy['password'])) {
            $copy['password'] = 'REMOVED FOR SECURITY';
        }
        Log::getInstance()->notice('ConfigStore::save(): saving %s', serialize($copy));

        // Remove password if not sent along. We have had some reports that
        // passwords were gone missing, perhaps some shops do not send the value
        // of password fields to the client???
        if (array_key_exists('password', $values) && empty($values['password'])) {
            unset($values['password']);
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
    protected function set($key, $value)
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
        $result['emailonwarning'] = $result['emailonerror'];
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
        return $this->getSettingsByGroup('customer');
    }

    /**
     * @inheritdoc
     */
    public function getInvoiceSettings()
    {
        return $this->getSettingsByGroup('invoice');
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
        return $this->getSettingsByGroup('emailaspdf');
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
     * from the config store might be NULL. However, internally we work with
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
            $environment = $this->getConfigStore()->getShopEnvironment();
            // As utf8 is now commonly accepted, it is a bit difficult to
            // express the set of characters that are allowed for email
            // addresses, so we remove characters not allowed.
            // See http://stackoverflow.com/a/2049537/1475662: @ ()[]\:;"<>,
            $shopName = str_replace(array(' ', '@', '(', ')', '[', ']', '\\', ':', ';', '"', '<', '>', ','), '', $environment['shopName']);
            $this->keyInfo = array(
                'baseUri' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => ServiceConfigInterface::baseUri,
                ),
                'apiVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => ServiceConfigInterface::apiVersion,
                ),
                'libraryVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => ServiceConfigInterface::libraryVersion,
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
                    'default' => ServiceConfigInterface::Debug_None,
                ),
                'logLevel' => array(
                    'group' => 'plugin',
                    'type' => 'int',
                    'default' => Log::Notice,
                ),
                'outputFormat' => array(
                    'group' => 'plugin',
                    'type' => 'string',
                    'default' => ServiceConfigInterface::outputFormat,
                ),
                'contractcode' => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                'username' => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                'password' => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                'emailonerror' => array(
                    'group' => 'credentials',
                    'type' => 'string',
                    'default' => '',
                ),
                'defaultCustomerType' => array(
                    'group' => 'customer',
                    'type' => 'int',
                    'default' => 0,
                ),
                'sendCustomer' => array(
                    'group' => 'customer',
                    'type' => 'bool',
                    'default' => true,
                ),
                'genericCustomerEmail' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => "consumer.$shopName@nul.sielsystems.nl",
                ),
                'emailIfAbsent' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => $shopName . '@nul.sielsystems.nl',
                ),
                'contactYourId' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'contactStatus' => array(
                    'group' => 'customer',
                    'type' => 'int',
                    'default' => InvoiceConfigInterface::ContactStatus_Active,
                ),
                'companyName1' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'companyName2' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'fullName' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'salutation' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'address1' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'address2' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'postalCode' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'city' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'vatNumber' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'telephone' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'fax' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'email' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'overwriteIfExists' => array(
                    'group' => 'customer',
                    'type' => 'bool',
                    'default' => true,
                ),
                'mark' => array(
                    'group' => 'customer',
                    'type' => 'string',
                    'default' => '',
                ),
                'defaultAccountNumber' => array(
                    'group' => 'invoice',
                    'type' => 'int',
                    'default' => 0,
                ),
                'defaultCostCenter' => array(
                    'group' => 'invoice',
                    'type' => 'int',
                    'default' => 0,
                ),
                'defaultInvoiceTemplate' => array(
                    'group' => 'invoice',
                    'type' => 'int',
                    'default' => 0,
                ),
                'defaultInvoicePaidTemplate' => array(
                    'group' => 'invoice',
                    'type' => 'int',
                    'default' => 0,
                ),
                'paymentMethodAccountNumber' => array(
                    'group' => 'invoice',
                    'type' => 'array',
                    'default' => array(),
                ),
                'paymentMethodCostCenter' => array(
                    'group' => 'invoice',
                    'type' => 'array',
                    'default' => array(),
                ),
                'sendEmptyShipping' => array(
                    'group' => 'invoice',
                    'type' => 'bool',
                    'default' => true,
                ),
                // @todo: add to advanced UI?
                'addMissingAmountLine' => array(
                    'group' => 'invoice',
                    'type' => 'bool',
                    'default' => true,
                ),
                // @todo: add to UI if shop does support it (PS?, WC?).
                'useMargin' => array(
                    'group' => 'invoice',
                    'type' => 'bool',
                    'default' => false,
                ),
                'optionsAllOn1Line' => array(
                    'group' => 'invoice',
                    'type' => 'int',
                    'default' => 2,
                ),
                'optionsAllOnOwnLine' => array(
                    'group' => 'invoice',
                    'type' => 'int',
                    'default' => 4,
                ),
                'optionsMaxLength' => array(
                    'group' => 'invoice',
                    'type' => 'int',
                    'default' => 80,
                ),
                'description' => array(
                    'group' => 'invoice',
                    'type' => 'string',
                    'default' => '[invoiceSource::type] [invoiceSource::reference]',
                ),
                'descriptionText' => array(
                    'group' => 'invoice',
                    'type' => 'string',
                    'default' => '',
                ),
                'invoiceNotes' => array(
                    'group' => 'invoice',
                    'type' => 'string',
                    'default' => '',
                ),
                'digitalServices' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => InvoiceConfigInterface::DigitalServices_Unknown,
                ),
                'vatFreeProducts' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => InvoiceConfigInterface::VatFreeProducts_Unknown,
                ),
                'invoiceNrSource' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => InvoiceConfigInterface::InvoiceNrSource_ShopInvoice,
                ),
                'dateToUse' => array(
                    'group' => 'shop',
                    'type' => 'int',
                    'default' => InvoiceConfigInterface::InvoiceDate_InvoiceCreate,
                ),
                'triggerOrderStatus' => array(
                    'group' => 'event',
                    'type' => 'array',
                    'default' => array(),
                ),
                'triggerInvoiceEvent' => array(
                    'group' => 'event',
                    'type' => 'int',
                    'default' => ConfigInterface::TriggerInvoiceEvent_None,
                ),
                'sendEmptyInvoice' => array(
                    'group' => 'event',
                    'type' => 'bool',
                    'default' => true,
                ),
                'emailAsPdf' => array(
                    'group' => 'emailaspdf',
                    'type' => 'bool',
                    'default' => false,
                ),
                'emailBcc' => array(
                    'group' => 'emailaspdf',
                    'type' => 'string',
                    'default' => '',
                ),
                'emailFrom' => array(
                    'group' => 'emailaspdf',
                    'type' => 'string',
                    'default' => '',
                ),
                'subject' => array(
                    'group' => 'emailaspdf',
                    'type' => 'string',
                    'default' => '',
                ),
                'confirmReading' => array(
                    'group' => 'emailaspdf',
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
            case 4: // Value for deprecated ServiceConfigInterface::Debug_StayLocal.
                $newSettings['logLevel'] = ServiceConfigInterface::Debug_TestMode;
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
        // Get current values.
        $values = $this->castValues($this->getConfigStore()->load($this->getKeys()));
        if ($this->get('triggerInvoiceSendEvent') == 2) {
            $values['triggerInvoiceEvent'] = ConfigInterface::TriggerInvoiceEvent_Create;
        } else {
            $values['triggerInvoiceEvent'] = ConfigInterface::TriggerInvoiceEvent_None;
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
}
