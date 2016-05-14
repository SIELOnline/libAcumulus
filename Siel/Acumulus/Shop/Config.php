<?php
namespace Siel\Acumulus\Shop;

use ReflectionClass;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Completor;
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
class Config implements ConfigInterface, InvoiceConfigInterface, ServiceConfigInterface, InjectorInterface
{
    /** @const string */
    const baseNamespace = '\\Siel\\Acumulus';

    /** @var array[]|null */
    protected $keyInfo;

    /** @var bool */
    protected $isLoaded;

    /** @var array */
    protected $values;

    /** @var array */
    protected $instances;

    /** @var string The namespace for the current shop. */
    protected $shopNamespace;

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
        $this->isLoaded = false;
        $this->values = array();
        $this->instances = array();
        $this->shopNamespace = static::baseNamespace . '\\' . $shopNamespace;
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
    public function getTranslator()
    {
        return $this->getInstance('Translator', 'Helpers', array($this->language));
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
    public function getService()
    {
        return $this->getInstance('Service', 'Web', array($this, $this->getTranslator()));
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
        return new Completor($this, $this->getTranslator(), $this->getService());
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
    public function getMailer()
    {
        return $this->getInstance('Mailer', 'Helpers', array($this, $this->getTranslator(), $this->getService()));
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigStore()
    {
        return $this->getInstance('ConfigStore', 'Shop', array($this->shopNamespace));
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
     * {@inheritdoc}
     */
    public function getForm($type)
    {
        $arguments = array($this->getTranslator());
        switch (strtolower($type)) {
            case 'config':
                $arguments[] = $this;
                break;
            case 'batch':
                $arguments[] = $this->getManager();
                break;
            default:
                $this->getLog()->error('Config::getForm(%s): unknown form type', $type);
                break;
        }
        return $this->getInstance(ucfirst($type) . 'Form', 'Shop', $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormRenderer()
    {
        return $this->getInstance('FormRenderer', 'Helpers');
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
     * @throws \ReflectionException
     */
    protected function getInstance($class, $subNamespace, array $constructorArgs = array(), $newInstance = false)
    {
        if (!isset($this->instances[$class]) || $newInstance) {
            // Try shop namespace.
            $fqClass = $this->tryNsInstance($class, $subNamespace, $this->shopNamespace);

            // Try CMS namespace if it exists.
            if (empty($fqClass)) {
                $cmsNamespace = $this->getCmsNamespace();
                if (!empty($cmsNamespace)) {
                    $fqClass = $this->tryNsInstance($class, $subNamespace, $cmsNamespace);
                }
            }

            // Try base namespace.
            if (empty($fqClass)) {
                $fqClass = $this->tryNsInstance($class, $subNamespace, static::baseNamespace);
            }

            // Use ReflectionClass to pass an argument list.
            if (empty($constructorArgs)) {
                // PHP5.3: exception when class has no constructor and
                // newInstanceArgs() is called.
                $this->instances[$class] = new $fqClass();
            }
            else {
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
        // Checking if the file exists prevent warnings in Magento whose own
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
        if (!$this->isLoaded) {
            $this->values = $this->castValues(array_merge($this->getDefaults(), $this->getConfigStore()->load($this->getKeys())));
            $this->isLoaded = true;
        }
    }

    /**
     * Saves the configuration to the actual configuration provider.
     *
     * @param array $values
     *   A keyed array that contains the values to store, this may be a subset of
     *   the possible keys.
     *
     * @return bool
     *   Success.
     */
    public function save(array $values)
    {
        $values = $this->castValues($values);
        $result = $this->getConfigStore()->save($values);
        $this->isLoaded = false;
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
     * Sets the value for the debug setting.
     *
     * @param int $debug
     *
     * @return int
     *   The old value.
     */
    public function setDebug($debug)
    {
        return $this->set('debug', (int) $debug);
    }

    /**
     * Sets the log level.
     *
     * @param int $logLevel
     *
     * @return int
     *   The old value.
     */
    public function setLogLevel($logLevel)
    {
        return $this->set('logLevel', (int) $logLevel);
    }

    /**
     * @inheritdoc
     */
    public function getBaseUri()
    {
        return $this->get('baseUri');
    }

    /**
     * @inheritdoc
     */
    public function getApiVersion()
    {
        return $this->get('apiVersion');
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
    public function getDebug()
    {
        return $this->get('debug');
    }

    /**
     * @inheritdoc
     */
    public function getLogLevel()
    {
        return $this->get('logLevel');
    }

    /**
     * @inheritdoc
     */
    public function getOutputFormat()
    {
        return $this->get('outputFormat');
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
    public function getEmailAsPdfSettings()
    {
        return $this->getSettingsByGroup('emailaspdf');
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
    public function getShopEventSettings()
    {
        return $this->getSettingsByGroup('event');
    }

    /**
     * @inheritdoc
     */
    public function getOtherSettings()
    {
        return $this->getSettingsByGroup('other');
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
     * Returns a list of keys that are stored in the shop specific config store.
     *
     * @return array
     */
    public function getKeys()
    {
        $result = $this->getKeyInfo();
        array_filter($result, function ($item) {
            return $item['group'] != 'environment';
        });
        return array_keys($result);
    }

    /**
     * Returns a set of default values for the various config settings.
     *
     * @return array
     */
    protected function getDefaults()
    {
        $result = $this->getKeyInfo();
        $result = array_map(function ($item) {
            return $item['default'];
        }, $result);
        return $result;
    }

    /**
     * The hostname of the current server.
     *
     * Used for a default email address.
     *
     * @return string
     */
    public function getHostName()
    {
        $hostName = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);
        if ($hostName) {
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
            $hostName = $this->getHostName();
            $curlVersion = curl_version();
            $shopDefaults = $this->getConfigStore()->getShopEnvironment();

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
                'outputFormat' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => ServiceConfigInterface::outputFormat,
                ),
                'libraryVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => ServiceConfigInterface::libraryVersion,
                ),
                'moduleVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $shopDefaults['moduleVersion'],
                ),
                'shopName' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $shopDefaults['shopName'],
                ),
                'shopVersion' => array(
                    'group' => 'environment',
                    'type' => 'string',
                    'default' => $shopDefaults['shopVersion'],
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
                    'default' => 'consumer@' . $hostName,
                ),
                'overwriteIfExists' => array(
                    'group' => 'customer',
                    'type' => 'bool',
                    'default' => true,
                ),
                'salutation' => array(
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
                // @todo: add UI.
                'paymentMethodAccountNumber' => array(
                    'group' => 'invoice',
                    'type' => 'array',
                    'default' => array(),
                ),
                // @todo: add UI.
                'paymentMethodCostCenter' => array(
                    'group' => 'invoice',
                    'type' => 'array',
                    'default' => array(),
                ),
                'removeEmptyShipping' => array(
                    'group' => 'invoice',
                    'type' => 'bool',
                    'default' => false,
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
                // @todo: add these 3 to advanced UI for shops where this is used (OC2, could others use this?).
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
                    'default' => 120,
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
                'triggerInvoiceSendEvent' => array(
                    'group' => 'event',
                    'type' => 'int',
                    'default' => ConfigInterface::TriggerInvoiceSendEvent_None,
                ),
                'triggerOrderStatus' => array(
                    'group' => 'event',
                    'type' => 'array',
                    'default' => array(),
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
                'debug' => array(
                    'group' => 'other',
                    'type' => 'int',
                    'default' => ServiceConfigInterface::Debug_None,
                ),
                'logLevel' => array(
                    'group' => 'other',
                    'type' => 'int',
                    'default' => Log::Error,
                ),
            );
        }
        return $this->keyInfo;
    }
}
