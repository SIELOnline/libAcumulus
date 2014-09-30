<?php
/**
 * @file Contains the base Configuration class.
 */
namespace Siel\Acumulus\Common;


abstract class BaseConfig implements ConfigInterface{
  /**
   * Increase this value on each change:
   * - point release: bug fixes
   * - minor version: addition of minor features, may be backwards incompatible
   * - major version: major or backwards incompatible changes
   *
   * @var string
   */
  public static $library_version = '3.6.0';

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

    $hostName = parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST);
    if ($hostName) {
      if ($pos = strpos($hostName, 'www.') !== false) {
        $hostName = substr($hostName, $pos + strlen('www.'));
      }
    }
    else {
      $hostName = 'example.com';
    }

    $this->values = array(
      // "Internal" configuration settings.
      'baseUri' => 'https://api.sielsystems.nl/acumulus',
      'apiVersion' => 'stable',
      'libraryVersion' => static::$library_version,
      'outputFormat' => 'json',
      'debug' => ConfigInterface::Debug_None,

      // Default configuration settings.
      'useMargin' => true,
      'invoiceNrSource' => ConfigInterface::InvoiceNrSource_ShopInvoice,
      'dateToUse' => ConfigInterface::InvoiceDate_InvoiceCreate,
      'sendCustomer' => true,
      'overwriteIfExists' => true,
      'genericCustomerEmail' => 'consumer@' . $hostName,
      'defaultInvoicePaidTemplate' => 0,
      'triggerOrderEvent' => ConfigInterface::TriggerOrderEvent_OrderStatus,

      // Default 'email invoice as pdf' settings.
      'emailAsPdf' => false,
      'emailBcc' => '',  // Empty: no bcc.
      'emailFrom' => '', // Empty: default shop address.
      'subject' => '', // Empty: default from Acumulus.
      'confirmReading' => false, // No UI for this setting.
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
  public function getDebug() {
    return $this->get('debug');
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
  public function getLanguage() {
    return $this->translator->getLanguage();
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
      'sendCustomer' => $this->get('sendCustomer'),
      'genericCustomerEmail' => $this->get('genericCustomerEmail'),
      'overwriteIfExists' => $this->get('overwriteIfExists'),
      'defaultAccountNumber' => $this->get('defaultAccountNumber'),
      'invoiceNrSource' => $this->get('invoiceNrSource'),
      'dateToUse' => $this->get('dateToUse'),
      'defaultCostCenter' => $this->get('defaultCostCenter'),
      'defaultInvoiceTemplate' => $this->get('defaultInvoiceTemplate'),
      'defaultInvoicePaidTemplate' => $this->get('defaultInvoicePaidTemplate'),
      'triggerOrderEvent' => $this->get('triggerOrderEvent'),
      'triggerOrderStatus' => $this->get('triggerOrderStatus'),
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
   * Performs common config form validation and casts values to their correct
   * type.
   *
   * @param array $values
   *
   * @return array
   *   A, possibly empty, array with validation error messages.
   */
  public function validateValues(array &$values) {
    $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';
    $regexpMultiEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+([,;][^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+)*$/';
    $result = array();
    if (empty($values['contractcode'])) {
      $result['contractcode'] = $this->t('message_validate_contractcode_0');
    }
    elseif (!is_numeric($values['contractcode'])) {
      $result['contractcode'] = $this->t('message_validate_contractcode_1');
    }
    if (empty($values['username'])) {
      $result['username'] = $this->t('message_validate_username_0');
    }
    if (empty($values['password'])) {
      $result['password'] = $this->t('message_validate_password_0');
    }
    if (empty($values['emailonerror'])) {
      $result['emailonerror'] = $this->t('message_validate_email_1');
    }
    else if (!preg_match($regexpEmail, $values['emailonerror'])) {
      $result['emailonerror'] = $this->t('message_validate_email_0');
    }
    if (!empty($values['genericCustomerEmail'])  && !preg_match($regexpEmail, $values['genericCustomerEmail'])) {
      $result['genericCustomerEmail'] = $this->t('message_validate_email_2');
    }
    if (!empty($values['emailFrom'])  && !preg_match($regexpEmail, $values['emailFrom'])) {
      $result['emailFrom'] = $this->t('message_validate_email_4');
    }
    if (!empty($values['emailBcc'])  && !preg_match($regexpMultiEmail, $values['emailBcc'])) {
      $result['emailBcc'] = $this->t('message_validate_email_3');
    }
    if (isset($values['emailAsPdf']) && (bool) $values['emailAsPdf'] && (!array_key_exists('sendCustomer', $values) || !(bool) $values['sendCustomer'])) {
      $result['conflicting_options'] = $this->t('message_validate_conflicting_options');
    }

    return $result;
  }

  /**
   * Casts the values to their correct types.
   *
   * Values that come from a submitted form are all strings. The same migth hold
   * for the config store of a web shop. However, internally we work with
   * booleans or integers. So after reading from the config store or form, we
   * cast the values to their expected types.
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
    if (isset($values['sendCustomer'])) {
      $values['sendCustomer'] = (bool) $values['sendCustomer'];
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
    if (isset($values['defaultCostCenter'])) {
      $values['defaultCostCenter'] = (int) $values['defaultCostCenter'];
    }
    if (isset($values['defaultInvoiceTemplate'])) {
      $values['defaultInvoiceTemplate'] = (int) $values['defaultInvoiceTemplate'];
    }
    if (isset($values['defaultInvoicePaidTemplate'])) {
      $values['defaultInvoicePaidTemplate'] = (int) $values['defaultInvoicePaidTemplate'];
    }
    if (isset($values['triggerOrderEvent'])) {
      $values['triggerOrderEvent'] = (int) $values['triggerOrderEvent'];
    }
    if (isset($values['emailAsPdf'])) {
      $values['emailAsPdf'] = (bool) $values['emailAsPdf'];
    }
    if (isset($values['confirmReading'])) {
      $values['confirmReading'] = (bool) $values['confirmReading'];
    }
    if (isset($values['debug'])) {
      $values['debug'] = (int) $values['debug'];
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
      'sendCustomer',
      'genericCustomerEmail',
      'overwriteIfExists',
      'defaultAccountNumber',
      'defaultCostCenter',
      'defaultInvoiceTemplate',
      'defaultInvoicePaidTemplate',
      'triggerOrderEvent',
      'triggerOrderStatus',
      'useMargin',
      'emailAsPdf',
      'emailBcc',
      'emailFrom',
      'subject',
      'confirmReading',
      'debug',
    );
  }
}
