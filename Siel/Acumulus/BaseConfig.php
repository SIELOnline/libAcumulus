<?php
/**
 * @file Contains the base Configuration class.
 */
namespace Siel\Acumulus;


abstract class BaseConfig implements ConfigInterface{
  /**
   * Increase this value on each change:
   * - point release: bug fixes
   * - minor version: addition of minor features, backwards compatible
   * - major version: major or backwards incompatible changes
   *
   * @var string
   */
  public static $library_version = '3.2.0';

  /** @var bool */
  protected $isLoaded;

  /** @var array */
  protected $values;

  /** @var TranslatorInterface */
  protected $translator;

  /**
   * @param string|TranslatorInterface $language
   *   Either the current language as a string or a translator interface
   */
  public function __construct($language) {
    $this->isLoaded = false;
    $this->values = array(
      // "Internal" configuration settings.
      'baseUri' => 'https://api.sielsystems.nl/acumulus',
      'apiVersion' => 'stable',
      'libraryVersion' => static::$library_version,
      'outputFormat' => 'json',
      'local' => false,
      'debug' => false,

      // Default configuration settings.
      'useMargin' => true,
      'invoiceNrSource' => ConfigInterface::InvoiceNrSource_ShopInvoice,
      'dateToUse' => ConfigInterface::InvoiceDate_InvoiceCreate,
      'overwriteIfExists' => true,
    );
    $this->translator = $language instanceof TranslatorInterface ? $language : new BaseTranslator($language);
  }

  /**
   * Loads the configuration from the actual configuration provider.
   *
   * @return bool
   *   Success
   */
  abstract protected function load();

  /**
   * Updates the configuration with the given values and saves the
   * configuration to the actual configuration provider.
   *
   * @param array $values
   *   A keyed array that contains the values to store.
   *
   * @return bool
   *   Success.
   */
  public function save(array $values) {
    // Update internal values.
    $this->values = array_merge($this->values, $values);
    return true;
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
  public function get($key) {
    if (!$this->isLoaded) {
      $this->load();
      $this->isLoaded = true;
    }
    return isset($this->values[$key]) ? $this->values[$key] : null;
  }

  /**
   * Sets the value of the specified configuration key.
   *
   * @param string $key
   *   The configuration value to set.
   * @param mixed $value
   *   The new value for the configuration key.
   *
   * @return mixed
   *   The old value.
   */
  public function set($key, $value) {
    if (!$this->isLoaded) {
      $this->load();
      $this->isLoaded = true;
    }
    $oldValue = isset($this->values[$key]) ? $this->values[$key] : null;
    $this->values[$key] = $value;
    return $oldValue;
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
    return array(
      'libraryVersion' => $this->get('libraryVersion'),
      'moduleVersion' => $this->get('moduleVersion'),
      'shopName' => $this->get('shopName'),
      'shopVersion' => $this->get('shopVersion'),
    );
  }

  /**
   * @inheritdoc
   */
  public function getLocal() {
    return $this->get('local');
  }

  /**
   * Sets the value for the "stay local" value.
   *
   * @param bool $local
   *
   * @return mixed
   *   The old value.
   */
  public function setLocal($local) {
    return $this->set('local', (bool) $local);
  }

  /**
   * @inheritdoc
   */
  public function getDebug() {
    return $this->get('debug');
  }

  /**
   * Sets the value for the debug value.
   *
   * @param bool $debug
   *
   * @return mixed
   *   The old value.
   */
  public function setDebug($debug) {
    return $this->set('debug', (bool) $debug);
  }

  /**
   * @inheritdoc
   */
  public function log($message) {
    $logFile = $this->get('logFile');
    if ($logFile) {
      $fh = @fopen($logFile, 'a');
      if ($fh) {
        fwrite($fh, $message);
        fclose($fh);
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function t($key) {
    return $this->translator->get($key);
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
      'defaultAccountNumber' => $this->get('defaultAccountNumber'),
      'invoiceNrSource' => $this->get('invoiceNrSource'),
      'dateToUse' => $this->get('dateToUse'),
      'defaultCostHeading' => $this->get('defaultCostHeading'),
      'defaultInvoiceTemplate' => $this->get('defaultInvoiceTemplate'),
      'triggerOrderStatus' => $this->get('triggerOrderStatus'),
      'useMargin' => $this->get('useMargin'),
      'overwriteIfExists' => $this->get('overwriteIfExists'),
    );
  }

  /**
   * Performs common config form validation and casts values to their correct
   * type.
   *
   * @param array $values
   *
   * @return array
   *   A, possibly empty, array with validation error messages.
   */
  public function validateValues(array &$values) {
    $result = array();
    if (empty($values['contractcode'])) {
      $result[] = $this->t('message_validate_contractcode_0');
    }
    elseif (!is_numeric($values['contractcode'])) {
      $result[] = $this->t('message_validate_contractcode_1');
    }
    if (empty($values['username'])) {
      $result[] = $this->t('message_validate_username_0');
    }
    if (empty($values['password'])) {
      $result[] = $this->t('message_validate_password_0');
    }
    if (empty($values['emailonerror'])) {
      $result[] = $this->t('message_validate_email_1');
    }
    else if (!preg_match('/^[^@]+@([^.@]+\.)+[^.@]+$/', $values['emailonerror'])) {
      $result[] = $this->t('message_validate_email_0');
    }
    return $result;
  }

  /**
   * Casts the values to their correct types.
   *
   * Values that come from a submitted form are all strings. The same migth hold
   * for the config store of a web shop. However, internally we work with
   * booleans or integers. So after reading from the config store or form, we
   * cast the values ot their expected types.
   *
   * @param array $values
   */
  public function castValues(array &$values) {
    if (isset($values['invoiceNrSource'])) {
      $values['invoiceNrSource'] = (int) $values['invoiceNrSource'];
    }
    if (isset($values['dateToUse'])) {
      $values['dateToUse'] = (int) $values['dateToUse'];
    }
    if (isset($values['overwriteIfExists'])) {
      $values['overwriteIfExists'] = (bool) $values['overwriteIfExists'];
    }
    if (isset($values['defaultCustomerType'])) {
      $values['defaultCustomerType'] = (int) $values['defaultCustomerType'];
    }
    if (isset($values['defaultAccountNumber'])) {
      $values['defaultAccountNumber'] = (int) $values['defaultAccountNumber'];
    }
    if (isset($values['defaultCostHeading'])) {
      $values['defaultCostHeading'] = (int) $values['defaultCostHeading'];
    }
    if (isset($values['defaultInvoiceTemplate'])) {
      $values['defaultInvoiceTemplate'] = (int) $values['defaultInvoiceTemplate'];
    }
  }

  /**
   * Returns a list of user configurable keys.
   *
   * @return array
   */
  public function getKeys() {
    return array(
      'contractcode',
      'username',
      'password',
      'emailonerror',
      'invoiceNrSource',
      'dateToUse',
      'defaultCustomerType',
      'defaultAccountNumber',
      'defaultCostHeading',
      'defaultInvoiceTemplate',
      'triggerOrderStatus',
      'useMargin',
      'overwriteIfExists',
    );
  }
}
