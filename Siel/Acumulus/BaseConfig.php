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
  public static $library_version = '1.0.1';

  /** @var bool */
  protected $isLoaded;

  /** @var array */
  protected $values;

  public function __construct() {
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
      'useAcumulusInvoiceNr' => false,
      'useOrderDate' => true,
      'overwriteIfExists' => true,
    );
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
    file_put_contents('/tmp/acumulus-webapi.log', $message, FILE_APPEND);
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
      'useAcumulusInvoiceNr' => $this->get('useAcumulusInvoiceNr'),
      'useOrderDate' => $this->get('useOrderDate'),
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
   *   A possibly empty array with validation error messages.
   */
  public function validateFormValues(array &$values) {
    $result = array();
    if (empty($values['contractcode'])) {
      $result[] = 'Het veld Contractcode is verplicht, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.';
    }
    elseif (!is_numeric($values['contractcode'])) {
      $result[] = 'Het veld Contractcode is een numeriek veld, vul de contractcode in die u ook gebruikt om in te loggen op Acumulus.';
    }
    if (empty($values['username'])) {
      $result[] = 'Het veld Gebruikersnaam is verplicht, vul de gebruikersnaam in die u ook gebruikt om in te loggen op Acumulus.';
    }
    if (empty($values['password'])) {
      $result[] = 'Het veld Wachtwoord is verplicht, vul het wachtwoord in dat u ook gebruikt om in te loggen op Acumulus.';
    }
    if (!preg_match('/^[^@]+@([^.@]+\.)+[^.@]+$/', $values['emailonerror'])) {
      $result[] = 'Het veld Email is geen valide e-mailadres, vul uw eigen e-mailadres in.';
    }
    $values['useAcumulusInvoiceNr'] = (bool) $values['useAcumulusInvoiceNr'];
    $values['useOrderDate'] = (bool) $values['useOrderDate'];
    $values['overwriteIfExists'] = (bool) $values['overwriteIfExists'];
    $values['defaultCustomerType'] = (int) $values['defaultCustomerType'];
    $values['defaultAccountNumber'] = (int) $values['defaultAccountNumber'];
    $values['defaultCostHeading'] = (int) $values['defaultCostHeading'];
    $values['defaultInvoiceTemplate'] = (int) $values['defaultInvoiceTemplate'];
    return $result;
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
      'useAcumulusInvoiceNr',
      'useOrderDate',
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
