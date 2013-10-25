<?php
/**
 * @file Contains the base Configuration class.
 */
namespace Siel\Acumulus;


abstract class BaseConfig implements ConfigInterface{
  /** @var bool */
  protected $isLoaded;

  /** @var array */
  protected $values;

  public function __construct() {
    $this->isLoaded = false;
    $this->values = array(
      'baseUri' => 'https://api.sielsystems.nl/acumulus',
      'apiVersion' => 'stable',
      'libraryVersion' => '1.0-alpha1',
      'outputFormat' => 'json',
      'local' => false,
      'debug' => false,
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
   * Saves the configuration to the actual configuration provider.
   *
   * @param array $values
   *   A keyed array that contains the values to store.
   *
   * @return bool
   *   Success.
   */
  abstract public function save(array $values);

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
   */
  public function setLocal($local) {
    $oldValue = $this->values['local'];
    $this->values['local'] = (bool) $local;
    return $oldValue;
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
   */
  public function setDebug($debug) {
    $oldValue = $this->values['debug'];
    $this->values['debug'] = (bool) $debug;
    return $oldValue;
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
      //@todo: useMargin wordt niet meer gebruikt.
      //'useMargin' => $this->get('useMargin'),
      //@todo: useCostprice wordt niet gebruikt.
      //'useCostPrice' => $this->get('useCostPrice'),
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
    if (!preg_match('/^[~@]+@([~.@]\.)+[~.@]+$/', $values['emailonerror'])) {
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
      //@todo: useMargin wordt niet meer gebruikt.
      //'useMargin',
      'overwriteIfExists',
      //@todo: useCostprice wordt niet gebruikt.
      //'useCostPrice',
    );
  }
}
