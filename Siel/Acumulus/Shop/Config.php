<?php
namespace Siel\Acumulus\Shop;

use ReflectionClass;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Invoice\Completor;
use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Web\ConfigInterface as ServiceConfigInterface;
use Siel\Acumulus\Web\Service;

/**
 * Gives common code in this package uniform access to the settings for this
 * extension, hiding all the web shop specific implementations of their config
 * store.
 *
 * This class implements all <...ConfigInterface>s and makes, via a ConfigStore,
 * use of the shop specific configuration functionality to store this
 * configuration in a persistent way.
 */
class Config implements ConfigInterface, InvoiceConfigInterface, ServiceConfigInterface, InjectorInterface {

  /** @var \Siel\Acumulus\Shop\ConfigStoreInterface */
  protected $configStore;

  /** @var \Siel\Acumulus\Helpers\Log */
  protected $log;

  /** @var \Siel\Acumulus\Web\Service */
  protected $service;

  /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
  protected $translator;

  /** @var bool */
  protected $isLoaded;

  /** @var array */
  protected $values;

  /** @var array */
  protected $instances;

  /**
   * Constructor.
   *
   * @param \Siel\Acumulus\Shop\ConfigStoreInterface $configStore
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   */
  public function __construct(ConfigStoreInterface $configStore, TranslatorInterface $translator) {
    $this->values = array();
    $this->configStore = $configStore;

    $this->translator = $translator;
    require_once(dirname(__FILE__) . '/Translations.php');
    $invoiceHelperTranslations = new Translations();
    $this->translator->add($invoiceHelperTranslations);

    $this->service = new Service($this, $this->translator);
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
  protected function t($key) {
    return $this->translator->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function getLog() {
    return Log::getInstance();
  }

  /**
   * {@inheritdoc}
   */
  public function getService() {
    return $this->service;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource($invoiceSourceType, $invoiceSourceId) {
    return $this->getInstance('Source', array($invoiceSourceType, $invoiceSourceId));
  }

  /**
   * {@inheritdoc}
   */
  public function getCompletor() {
    return new Completor($this, $this->translator, $this->service);
  }

  /**
   * {@inheritdoc}
   */
  public function getCreator() {
    return $this->getInstance('Creator', array($this, $this->translator));
  }

  /**
   * {@inheritdoc}
   */
  public function getMailer() {
    return $this->getInstance('Mailer', array($this, $this->translator, $this->service));
  }

  /**
   * {@inheritdoc}
   */
  public function getManager() {
    return $this->getInstance('InvoiceManager', array($this, $this->translator));
  }

  /**
   * {@inheritdoc}
   */
  public function getAcumulusEntryModel() {
    return $this->getInstance('AcumulusEntryModel');
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
   * @param array $constructorArgs
   *
   * @return object
   */
  protected function getInstance($class, array $constructorArgs = array()) {
    if (!isset($this->instances[$class])) {
      $class = $this->getShopNamespace() . '\\' . $class;
      $reflector = new ReflectionClass($class);
      $this->instances[$class] = $reflector->newInstanceArgs($constructorArgs);
    }
    return $this->instances[$class];
  }

  protected function getShopNamespace() {
    $class = get_class($this->configStore);
    $namespaceEnd = strrpos($class, '\\');
    return substr($class, 0, (int) $namespaceEnd);
  }

  /**
   * Loads the configuration from the actual configuration provider.
   */
  protected function load() {
    if (!$this->isLoaded) {
      $this->values = array_merge($this->getDefaults(), $this->configStore->load($this->getKeys()));
      $this->castValues();
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
  public function save(array $values) {
    $result = $this->configStore->save($values);
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
  protected function get($key) {
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
  protected function set($key, $value) {
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
  public function setDebug($debug) {
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
  public function setLogLevel($logLevel) {
    return $this->set('logLevel', (int) $logLevel);
  }

  /**
   * @inheritdoc
   */
  public function getBaseUri() {
    return $this->get('baseUri');
  }

  /**
   * @inheritdoc
   */
  public function getApiVersion() {
    return $this->get('apiVersion');
  }

  /**
   * @inheritdoc
   */
  public function getEnvironment() {
    $curlVersion = curl_version();
    return array(
      'libraryVersion' => $this->get('libraryVersion'),
      'moduleVersion' => $this->get('moduleVersion'),
      'shopName' => $this->get('shopName'),
      'shopVersion' => $this->get('shopVersion'),
      'phpVersion' => phpversion(),
      'os' => php_uname(),
      'curlVersion' => "{$curlVersion['version']} (ssl: {$curlVersion['ssl_version']}; zlib: {$curlVersion['libz_version']})",
      'jsonVersion' => phpversion('json'),
    );
  }

  /**
   * @inheritdoc
   */
  public function getDebug() {
    return $this->get('debug');
  }

  /**
   * @inheritdoc
   */
  public function getLogLevel() {
    return $this->get('logLevel');
  }

  /**
   * @inheritdoc
   */
  public function getOutputFormat() {
    return $this->get('outputFormat');
  }

  /**
   * @inheritdoc
   */
  public function getCredentials() {
    return array(
      'contractcode' => $this->get('contractcode'),
      'username' => $this->get('username'),
      'password' => $this->get('password'),
      'emailonerror' => $this->get('emailonerror'),
      'emailonwarning' => $this->get('emailonerror'), // No separate key for now.
    );
  }

  /**
   * @inheritdoc
   */
  public function getInvoiceSettings() {
    return array(
      'defaultCustomerType' => $this->get('defaultCustomerType'),
      'sendCustomer' => $this->get('sendCustomer'),
      'genericCustomerEmail' => $this->get('genericCustomerEmail'),
      'overwriteIfExists' => $this->get('overwriteIfExists'),
      'defaultAccountNumber' => $this->get('defaultAccountNumber'),
      'defaultCostCenter' => $this->get('defaultCostCenter'),
      'defaultInvoiceTemplate' => $this->get('defaultInvoiceTemplate'),
      'defaultInvoicePaidTemplate' => $this->get('defaultInvoicePaidTemplate'),
      'useMargin' => $this->get('useMargin'),
    );
  }

  /**
   * @inheritdoc
   */
  public function getEmailAsPdfSettings() {
    return array(
      'emailAsPdf' => $this->get('emailAsPdf'),
      'emailBcc' => $this->get('emailBcc'),
      'emailFrom' => $this->get('emailFrom'),
      'subject' => $this->get('subject'),
      'confirmReading' => $this->get('confirmReading'),
    );
  }

  /**
   * @inheritdoc
   */
  public function getShopSettings() {
    return array(
      'invoiceNrSource' => $this->get('invoiceNrSource'),
      'dateToUse' => $this->get('dateToUse'),
      'triggerInvoiceSendEvent' => $this->get('triggerInvoiceSendEvent'),
      'triggerOrderStatus' => $this->get('triggerOrderStatus'),
    );
  }

  /**
   * Casts the values to their correct types.
   *
   * Values that come from a submitted form are all strings. The same might hold
   * for the config store of a web shop. However, internally we work with
   * booleans or integers. So after reading from the config store or form, we
   * cast the values to their expected types.
   */
  protected function castValues() {
    if (isset($this->values['invoiceNrSource'])) {
      $this->values['invoiceNrSource'] = (int) $this->values['invoiceNrSource'];
    }
    if (isset($this->values['dateToUse'])) {
      $this->values['dateToUse'] = (int) $this->values['dateToUse'];
    }
    if (isset($this->values['sendCustomer'])) {
      $this->values['sendCustomer'] = (bool) $this->values['sendCustomer'];
    }
    if (isset($this->values['overwriteIfExists'])) {
      $this->values['overwriteIfExists'] = (bool) $this->values['overwriteIfExists'];
    }
    if (isset($this->values['defaultCustomerType'])) {
      $this->values['defaultCustomerType'] = (int) $this->values['defaultCustomerType'];
    }
    if (isset($this->values['defaultAccountNumber'])) {
      $this->values['defaultAccountNumber'] = (int) $this->values['defaultAccountNumber'];
    }
    if (isset($this->values['defaultCostCenter'])) {
      $this->values['defaultCostCenter'] = (int) $this->values['defaultCostCenter'];
    }
    if (isset($this->values['defaultInvoiceTemplate'])) {
      $this->values['defaultInvoiceTemplate'] = (int) $this->values['defaultInvoiceTemplate'];
    }
    if (isset($this->values['defaultInvoicePaidTemplate'])) {
      $this->values['defaultInvoicePaidTemplate'] = (int) $this->values['defaultInvoicePaidTemplate'];
    }
    if (isset($this->values['triggerInvoiceSendEvent'])) {
      $this->values['triggerInvoiceSendEvent'] = (int) $this->values['triggerInvoiceSendEvent'];
    }
    if (isset($this->values['emailAsPdf'])) {
      $this->values['emailAsPdf'] = (bool) $this->values['emailAsPdf'];
    }
    if (isset($this->values['confirmReading'])) {
      $this->values['confirmReading'] = (bool) $this->values['confirmReading'];
    }
    if (isset($this->values['debug'])) {
      $this->values['debug'] = (int) $this->values['debug'];
    }
    if (isset($this->values['logLevel'])) {
      $this->values['logLevel'] = (int) $this->values['logLevel'];
    }
  }

  /**
   * Returns a list of keys that are stored in the shop specific config store.
   *
   * @return array
   */
  public function getKeys() {
    return array(
      'contractcode',
      'username',
      'password',
      'emailonerror',
      'defaultCustomerType',
      'sendCustomer',
      'genericCustomerEmail',
      'overwriteIfExists',
      'defaultAccountNumber',
      'defaultCostCenter',
      'invoiceNrSource',
      'dateToUse',
      'defaultInvoiceTemplate',
      'defaultInvoicePaidTemplate',
      'emailAsPdf',
      'emailBcc',
      'emailFrom',
      'subject',
      'confirmReading',
      'invoiceNrSource',
      'dateToUse',
      'triggerInvoiceSendEvent',
      'triggerOrderStatus',
      'useMargin',
      'debug',
      'logLevel',
    );
  }

  /**
   * Returns a set of default values for the various config settings.
   *
   * @return array
   */
  protected function getDefaults() {
    $hostName = $this->getHostName();

    $defaults = array(
      // Web service configuration settings.
      'baseUri'                    => ServiceConfigInterface::baseUri,
      'apiVersion'                 => ServiceConfigInterface::apiVersion,
      'libraryVersion'             => ServiceConfigInterface::libraryVersion,
      'outputFormat'               => ServiceConfigInterface::outputFormat,
      'debug'                      => ServiceConfigInterface::Debug_None,
      'logLevel'                   => Log::Error,

      // Default invoice settings.
      'sendCustomer'               => true,
      'overwriteIfExists'          => true,
      'genericCustomerEmail'       => 'consumer@' . $hostName,
      'defaultInvoicePaidTemplate' => 0,

      // Default 'email invoice as pdf' settings.
      'emailAsPdf'                 => false,
      'emailBcc'                   => '',  // Empty: no bcc.
      'emailFrom'                  => '', // Empty: default from Acumulus template
      'subject'                    => '', // Empty: default from Acumulus.
      'confirmReading'             => false, // No UI for this setting.

      // Default shop settings.
      'useMargin'                  => false,
      'invoiceNrSource'            => ConfigInterface::InvoiceNrSource_ShopInvoice,
      'dateToUse'                  => ConfigInterface::InvoiceDate_InvoiceCreate,
      'triggerInvoiceSendEvent'    => ConfigInterface::TriggerInvoiceSendEvent_None,
    );
    $shopDefaults = $this->configStore->getShopEnvironment();

    return array_merge($defaults, $shopDefaults);
  }

  /**
   * The hostname of the current server.
   *
   * Used for a default email address.
   *
   * @return string
   */
  public function getHostName() {
    $hostName = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);
    if ($hostName) {
      if ($pos = strpos($hostName, 'www.') !== FALSE) {
        $hostName = substr($hostName, $pos + strlen('www.'));
      }
    }
    else {
      $hostName = 'example.com';
    }
    return $hostName;
  }

}
