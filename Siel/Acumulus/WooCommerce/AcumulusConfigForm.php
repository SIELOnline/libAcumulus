<?php

/**
 * @file
 * Contains \Siel\Acumulus\OpenCart\AcumulusConfigForm
 */

namespace Siel\Acumulus\WooCommerce;

use Siel\Acumulus\ConfigInterface;
use Siel\Acumulus\FormRenderer;
use Siel\Acumulus\WebAPI;

/**
 * Class AcumulusConfigForm builds the settings form page for the
 * WooCommerce Acumulus module.
 */
class AcumulusConfigForm {

  /** @var WooCommerceAcumulusConfig */
  private $acumulusConfig;

  /** @var \Siel\Acumulus\WebAPI */
  private $webAPI;

  /** @var \Siel\Acumulus\FormRenderer */
  private $formRenderer;

  /** @var array configuration values */
  private $values;

  /** @var array contact type picklist */
  private $contactTypes;

  /**
   * @param WooCommerceAcumulusConfig $config
   * param \ControllerModuleAcumulus $module
   */
  public function __construct(WooCommerceAcumulusConfig $config) {
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
  private function t($string) {
    return $this->acumulusConfig->t($string);
  }

  /**
   * Returns a help text in an html tag.
   *
   * @param $string
   *
   * @return string
   */
  private function description($string) {
    return $this->formRenderer->description($string, 'p', 'description');
  }

  /**
   * Defines the fieldsets and fields on the form.
   */
  public function addFields() {
    $this->formRenderer = new FormRenderer();
    $this->values = $this->acumulusConfig->getCredentials() + $this->acumulusConfig->getInvoiceSettings();

    $required = '<span class="required">*</span>';
    // 1st fieldset: Acumulus account settings.
    add_settings_section('fieldset1', $this->t('accountSettingsHeader'), null, 'woocommerce_acumulus');
    add_settings_field('contractcode', $this->t('field_code') . $required, array($this, 'getContractCodeField'), 'woocommerce_acumulus', 'fieldset1');
    add_settings_field('username', $this->t('field_username') . $required, array($this, 'getUserNameField'), 'woocommerce_acumulus', 'fieldset1');
    add_settings_field('password', $this->t('field_password') . $required, array($this, 'getPasswordField'), 'woocommerce_acumulus', 'fieldset1');
    add_settings_field('emailonerror', $this->t('field_email') . $required, array($this, 'getEmailField'), 'woocommerce_acumulus', 'fieldset1');

    add_settings_section('fieldset2', $this->t('invoiceSettingsHeader'), null, 'woocommerce_acumulus');
    $message = $this->checkAccountSettings();
    if (!empty($message)) {
      add_settings_field('invoiceSettingsMessage', '', array($this, 'getInvoiceSettingsMessage'), 'woocommerce_acumulus', 'fieldset2', $message);
    }
    else {
      // 2nd fieldset: Acumulus invoice settings.
      add_settings_field('invoiceNrSource', $this->t('field_invoiceNrSource') . $required, array($this, 'getInvoiceNrSourceField'), 'woocommerce_acumulus', 'fieldset2');
      add_settings_field('dateToUse', $this->t('field_dateToUse') . $required, array($this, 'getDateToUseField'), 'woocommerce_acumulus', 'fieldset2');
      add_settings_field('defaultCustomerTypeField', $this->t('field_defaultCustomerType'), array($this, 'getDefaultCustomerTypeField'), 'woocommerce_acumulus', 'fieldset2');
      add_settings_field('overwriteIfExistsField', $this->t('field_overwriteIfExists'), array($this, 'getOverwriteIfExistsField'), 'woocommerce_acumulus', 'fieldset2');
      add_settings_field('defaultAccountNumberField', $this->t('field_defaultAccountNumber'), array($this, 'getDefaultAccountNumberField'), 'woocommerce_acumulus', 'fieldset2');
      add_settings_field('defaultCostHeadingField', $this->t('field_defaultCostHeading'), array($this, 'getDefaultCostHeadingField'), 'woocommerce_acumulus', 'fieldset2');
      add_settings_field('defaultInvoiceTemplateField', $this->t('field_defaultInvoiceTemplate'), array($this, 'getDefaultInvoiceTemplateField'), 'woocommerce_acumulus', 'fieldset2');
      add_settings_field('triggerOrderStatusField', $this->t('field_triggerOrderStatus'), array($this, 'getTriggerOrderStatusField'), 'woocommerce_acumulus', 'fieldset2');
    }

    add_settings_section('fieldset3', $this->t('versionInformationHeader'), null, 'woocommerce_acumulus');
    add_settings_field('versionInformation', '', array($this, 'getVersionInformation'), 'woocommerce_acumulus', 'fieldset3');
  }

  /**
   * Outputs the HTML for the complete form.
   */
  public function getForm() {
    // your own css files
    echo '<div class="wrap">';
    echo '<form method="post" action="options.php">';
    settings_fields('woocommerce_acumulus');
    do_settings_sections('woocommerce_acumulus');
    submit_button();
    echo '</form>';
    echo '</div>';
  }

  /**
   * Outputs the contract code field.
   */
  public function getContractCodeField() {
    echo $this->formRenderer->text('woocommerce_acumulus[contractcode]', $this->values['contractcode'], array('size' => 20, 'required' => true));
  }

  /**
   * Outputs the user name field.
   */
  public function getUserNameField() {
    echo $this->formRenderer->text('woocommerce_acumulus[username]', $this->values['username'], array('size' => 20, 'required' => true));
  }

  /**
   * Outputs the password field.
   */
  public function getPasswordField() {
    echo $this->formRenderer->password('woocommerce_acumulus[password]', $this->values['password'], array('size' => 20, 'required' => true));
  }

  /**
   * Outputs the email address field.
   */
  public function getEmailField() {
    echo $this->formRenderer->text('woocommerce_acumulus[emailonerror]', $this->values['emailonerror'], array('size' => 20, 'required' => true));
    echo $this->description($this->t('desc_email'));
  }

  public function getInvoiceSettingsMessage($message = '') {
    echo "<div class='message'>$message</div>";
  }

  /**
   * Outputs the source for the invoice number field.
   */
  public function getInvoiceNrSourceField() {
    $options = array(
      ConfigInterface::InvoiceNrSource_ShopInvoice => $this->t('option_invoiceNrSource_1'),
      ConfigInterface::InvoiceNrSource_ShopOrder => $this->t('option_invoiceNrSource_2'),
      ConfigInterface::InvoiceNrSource_Acumulus => $this->t('option_invoiceNrSource_3'),
    );
    echo $this->formRenderer->radio('woocommerce_acumulus[invoiceNrSource]',
      $options, $this->values['invoiceNrSource'], array('required' => true));
    echo $this->description($this->t('desc_invoiceNrSource'));
  }

  /**
   * Outputs the date to use field.
   */
  public function getDateToUseField() {
    $options = array(
      // @todo: check if there is an invoice date.
      ConfigInterface::InvoiceDate_InvoiceCreate => $this->t('option_dateToUse_1'),
      ConfigInterface::InvoiceDate_OrderCreate => $this->t('option_dateToUse_2'),
      ConfigInterface::InvoiceDate_Transfer => $this->t('option_dateToUse_3'),
    );
    echo $this->formRenderer->radio('woocommerce_acumulus[dateToUse]',
      $options, $this->values['dateToUse'], array('required' => true));
    echo $this->description($this->t('desc_dateToUse'));
  }

  /**
   * Outputs the default customer type field.
   */
  public function getDefaultCustomerTypeField() {
    $options = $this->picklistToOptions($this->contactTypes['contacttypes'], 0, $this->t('option_empty'));
    echo $this->formRenderer->select('woocommerce_acumulus[defaultCustomerType]',
      $options, $this->values['defaultCustomerType']);
  }

  /**
   * Outputs the overwrite if exists field.
   */
  public function getOverwriteIfExistsField() {
    $options = array(1 => $this->t('option_overwriteIfExists'));
    echo $this->formRenderer->checkbox('woocommerce_acumulus[overwriteIfExists]',
      $options, array($this->values['overwriteIfExists']));
    echo $this->description($this->t('desc_overwriteIfExists'));
  }

  /**
   * Outputs the default account number field.
   */
  public function getDefaultAccountNumberField() {
    $options = $this->webAPI->getPicklistAccounts();
    $options = $this->picklistToOptions($options['accounts'], 0, $this->t('option_empty'));
    echo $this->formRenderer->select('woocommerce_acumulus[defaultAccountNumber]',
      $options, $this->values['defaultAccountNumber']);
    echo $this->description($this->t('desc_defaultAccountNumber'));
  }

  /**
   * Outputs the default cost heading field.
   */
  public function getDefaultCostHeadingField() {
    $options = $this->webAPI->getPicklistCostCenters();
    $options = $this->picklistToOptions($options['costcenters'], 0, $this->t('option_empty'));
    echo $this->formRenderer->select('woocommerce_acumulus[defaultCostHeading]',
      $options, $this->values['defaultCostHeading']);
    echo $this->description($this->t('desc_defaultCostHeading'));
  }

  /**
   * Outputs the default invoice template field.
   */
  public function getDefaultInvoiceTemplateField() {
    $options = $this->webAPI->getPicklistInvoiceTemplates();
    $options = $this->picklistToOptions($options['invoicetemplates'], 0, $this->t('option_empty'));
    echo $this->formRenderer->select('woocommerce_acumulus[defaultInvoiceTemplate]',
      $options, $this->values['defaultInvoiceTemplate']);
    echo $this->description($this->t('desc_defaultInvoiceTemplate'));
  }

  /**
   * Outputs the trigger order status field.
   */
  public function getTriggerOrderStatusField() {
    $orderStatuses = get_terms('shop_order_status', 'hide_empty=0');
    $options = $this->orderStatusesToOptions($orderStatuses, 0, $this->t('option_empty_triggerOrderStatus'));
    echo $this->formRenderer->select('woocommerce_acumulus[triggerOrderStatus]',
      $options, $this->values['triggerOrderStatus']);
    echo $this->description($this->t('desc_triggerOrderStatus'));
  }


  public function getVersionInformation() {
    $env = $this->acumulusConfig->getEnvironment();
    echo "<div class='message'>Acumulus module {$env['moduleVersion']} (API: {$env['libraryVersion']}) voor {$env['shopName']} {$env['shopVersion']}</div>";
    echo $this->description($this->t('desc_versionInformation'));
  }

  /**
   * Check if we can retrieve a picklist. This indicates if the account
   * settings are known and correct.
   *
   * The picklist will be stored for later use.
   *
   * @return string
   *   A user readable message indicating if the account settings needs yet to
   *   be filled in or were incorrect. The empty string, if a successful
   *   connection was made.
   */
  public function checkAccountSettings() {
    // Check if we can retrieve a picklist. Thi indicates if the account
    // settings are known and correct.
    $message = '';
    $this->contactTypes = null;
    if ($this->acumulusConfig->get('password')) {
      $this->contactTypes = $this->webAPI->getPicklistContactTypes();
      if (!empty($this->contactTypes['errors'])) {
        if ($this->contactTypes['errors'][0]['code'] == 401) {
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
    return $message;
  }

  /**
   * Validates the form submission.
   *
   * @param array $values
   *
   * @return array
   */
  public function validateForm(array $values) {
    $this->acumulusConfig->castValues($values);
    $messages = $this->acumulusConfig->validateValues($values);
    // WooCommerce specific form validation.
    // ... none so far ...
    foreach ($messages as $key => $message) {
      add_settings_error("woocommerce-acumulus[$key]", 'settings', $message, 'error');
    }
    return $values;
  }

  private function picklistToOptions($picklist, $emptyValue = null, $emptyText = null) {
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

  private function orderStatusesToOptions($orderStatuses, $emptyValue = null, $emptyText = null) {
    $result = array();

    if ($emptyValue !== null) {
      $result[$emptyValue] = $emptyText;
    }
    array_walk($orderStatuses, function ($value) use (&$result) {
      $result[$value->slug] = $value->name;
    });

    return $result;
  }
}
