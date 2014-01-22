<?php

/**
 * @file
 * Contains \Siel\Acumulus\OpenCart\AcumulusConfigForm
 */

namespace Siel\Acumulus\OpenCart;

use Siel\Acumulus\WebAPI;

/**
 * Class AcumulusConfigForm processes and builds the settings form page for the
 * Prestashop Acumulus module.
 */
class AcumulusConfigForm {

  /** @var \ControllerModuleAcumulus */
  protected $module;

  /** @var OpenCartAcumulusConfig */
  protected $acumulusConfig;

  /** @var \Siel\Acumulus\WebAPI */
  protected $webAPI;

  /**
   * @param OpenCartAcumulusConfig $config
   * @param \ControllerModuleAcumulus $module
   */
  public function __construct(OpenCartAcumulusConfig $config, \ControllerModuleAcumulus $module) {
    $this->module = $module;
    $this->acumulusConfig = $config;
    $this->webAPI = new WebAPI($this->acumulusConfig);
  }

  /**
   * Gets the translation for a given text.
   *
   * @param string $string
   *   The translation key of the text to translate.
   *
   * @return string Translation
   *   The translated text or the key itself if no translation was found.
   */
  protected function t($string) {
    return $this->acumulusConfig->t($string);
  }

  /**
   * Processes and renders the form.
   *
   * @return array
   */
  public function getForm() {
    // Satisfy PhpStorm.
    $isValid = true;
    $postValues = array();

    // (shop specific)
    $isSubmit = $this->module->request->server['REQUEST_METHOD'] == 'POST';
    if ($isSubmit) {
      // (shop specific)
      $postValues = $this->module->request->post;

      $messages = $this->validateForm($postValues);
      $isValid = empty($messages);
      if ($isValid) {
        $this->saveForm($postValues);
        // Set on screen success message. (shop specific)
        $this->module->addSuccess($this->t('message_success'));
      }
      else {
        // Set on screen error messages. (shop specific)
        foreach ($messages as $message) {
          $this->module->addError($this->t($message));
        }
      }
    }
    $formValues = $this->acumulusConfig->getCredentials() + $this->acumulusConfig->getInvoiceSettings();
    if ($isSubmit && !$isValid) {
      // Present the user with the values filled in (to correct them).
      $formValues = array_merge($formValues, $postValues);
    }
    $this->addCss();
    return $this->getFormContent($formValues);
  }

  /**
   * Saves submitted and validated form values to the configuration.
   *
   * @param array $values
   */
  protected function saveForm(array $values) {
    $this->acumulusConfig->save($values);
    //$this->module->model_setting_setting->editSetting('acumulus', $values);
  }

  /**
   * Validates the form submission.
   *
   * @param array $values
   *
   * @return array
   */
  protected function validateForm(array &$values) {
    $this->acumulusConfig->castValues($values);
    $messages = $this->acumulusConfig->validateValues($values);
    // OpenCart specific form validation.
    // ... none so far ...
    return $messages;
  }

  protected function getFormContent($values) {
    $formRenderer = new FormRenderer();
    $result = array();

    // 1st fieldset: Acumulus account settings.
    $result['accountSettingsHeader'] = '<h2>' . $this->t('accountSettingsHeader') . '</h2>';
    $result['contractcode'] = $formRenderer->simpleField('text', 'contractcode', $this->t('field_code'), $values['contractcode'], array('size' => 20, 'required' => true));
    $result['username'] = $formRenderer->simpleField('text', 'username', $this->t('field_username'), $values['username'], array('size' => 20, 'required' => true));
    $result['password'] = $formRenderer->simpleField('password', 'password', $this->t('field_password'), $values['password'], array('size' => 20, 'required' => true));
    $result['emailonerror'] = $formRenderer->simpleField('text', 'emailonerror', $this->t('field_email'), $values['emailonerror'], array('size' => 30),
      $this->t('desc_email'));

    // Check if we can retrieve a picklist. Thi indicates if the account
    // settings are known and correct.
    $message = '';
    $contactTypes = null;
    if ($this->acumulusConfig->get('password')) {
      $contactTypes = $this->webAPI->getPicklistContactTypes();
      if (!empty($contactTypes['errors'])) {
        if ($contactTypes['errors'][0]['code'] == 401) {
          $message = $this->t('message_error_auth');
        }
        else {
          $message = $this->t('message_error_comm');
        }
      }
    }
    else {
      $message = $this->t('message_auth_unknown');
    }

    $result['invoiceSettingsHeader'] = '<h2>' . $this->t('invoiceSettingsHeader') . '</h2>';
    if ($message) {
      $result['invoiceSettingsMessage'] = $message;
    }
    else {
      // 2nd fieldset
      $options = array(
        0 => $this->t('option_useAcumulusInvoiceNr_0'),
        1 => $this->t('option_useAcumulusInvoiceNr_1'),
      );
      $result['useAcumulusInvoiceNr'] = $formRenderer->listField('radio', 'useAcumulusInvoiceNr', $this->t('field_useAcumulusInvoiceNr'), $options, $values['useAcumulusInvoiceNr'], array('required' => true));

      $options = array(
        1 => $this->t('option_useOrderDate_0'),
        0 => $this->t('option_useOrderDate_1'),
      );
      $result['useOrderDate'] = $formRenderer->listField('radio', 'useOrderDate', $this->t('field_useOrderDate'), $options, $values['useOrderDate'], array('required' => true));

      $options = $this->picklistToOptions($contactTypes['contacttypes'], 0, $this->t('option_empty'));
      $result['defaultCustomerType'] = $formRenderer->listField('select', 'defaultCustomerType', $this->t('field_defaultCustomerType'), $options, $values['defaultCustomerType']);

      $options = array(1 => $this->t('option_overwriteIfExists'));
      $result['overwriteIfExists'] = $formRenderer->listField('checkbox', 'overwriteIfExists', $this->t('field_overwriteIfExists'), $options, array($values['overwriteIfExists']), array(),
        $this->t('desc_overwriteIfExists'));

      $options = $this->webAPI->getPicklistAccounts();
      $options = $this->picklistToOptions($options['accounts'], 0, $this->t('option_empty'));
      $result['defaultAccountNumber'] = $formRenderer->listField('select', 'defaultAccountNumber', $this->t('field_defaultAccountNumber'), $options, $values['defaultAccountNumber'], array(),
        $this->t('desc_defaultAccountNumber'));

      $options = $this->webAPI->getPicklistCostCenters();
      $options = $this->picklistToOptions($options['costcenters'], 0, $this->t('option_empty'));
      $result['defaultCostHeading'] = $formRenderer->listField('select', 'defaultCostHeading', $this->t('field_defaultCostHeading'), $options, $values['defaultCostHeading'], array(),
        $this->t('desc_defaultCostHeading'));

      $options = $this->webAPI->getPicklistInvoiceTemplates();
      $options = $this->picklistToOptions($options['invoicetemplates'], 0, $this->t('option_empty'));
      $result['defaultInvoiceTemplate'] = $formRenderer->listField('select', 'defaultInvoiceTemplate', $this->t('field_defaultInvoiceTemplate'), $options, $values['defaultInvoiceTemplate'], array(),
        $this->t('desc_defaultInvoiceTemplate'));

      $this->module->load->model('localisation/order_status');
      $options = $this->module->model_localisation_order_status->getOrderStatuses();
      $options = $this->picklistToOptions($options, 0, $this->t('option_empty_triggerOrderStatus'));
      $result['triggerOrderStatus'] = $formRenderer->listField('select', 'triggerOrderStatus', $this->t('field_triggerOrderStatus'), $options, $values['triggerOrderStatus'], array(),
        $this->t('desc_triggerOrderStatus'));
    }

//    $result = array_merge($result, $this->getTestFields());

    return $result;
  }

//  protected function getTestFields() {
//    $output = array();
//    $file = dirname(__FILE__) . '/lib/Siel/PrestashopTest/TestForm.php';
//    if (is_file($file)) {
//      require_once($file);
//      $testForm = new Siel\PrestashopTest\TestForm($this->acumulusConfig, $this->module);
//      $output = $testForm->getContent();
//    }
//    return $output;
//  }

  protected function picklistToOptions($picklist, $emptyValue = null, $emptyText = null) {
    $result = array();

    if ($emptyValue !== null) {
      $result[$emptyValue] = $emptyText;
    }
    array_walk($picklist, function ($value) use (&$result) {
      list($optionValue, $optionText) = array_values($value);
      $result[$optionValue] = $optionText;
    });

    return $result;
  }

  protected function addCss() {
    $this->module->document->addStyle('view/stylesheet/acumulus.css');
  }
}
