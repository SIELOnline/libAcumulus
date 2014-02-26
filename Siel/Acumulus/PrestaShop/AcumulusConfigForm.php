<?php
/**
 * @file Contains class Siel\Acumulus\PrestaShop\AcumulusConfigForm.
 */
namespace Siel\Acumulus\PrestaShop;

use AdminController;
use Configuration;
use Context;
use HelperForm;
use OrderState;
use Siel\Acumulus\ConfigInterface;
use Tools;
use Acumulus;
use Siel\Acumulus\WebAPI;
use Siel\Acumulus\PrestaShop\Test\TestForm;

/**
 * Class AcumulusConfigForm processes and builds the settings form page for the
 * Prestashop Acumulus module.
 */
class AcumulusConfigForm {

  /** @var Acumulus */
  protected $module;

  /** @var PrestaShopAcumulusConfig */
  protected $acumulusConfig;

  /** @var WebAPI */
  protected $webAPI;

  /** @var array contact type picklist */
  private $contactTypes;

  /** @var string Authentication failure message */
  private $message;

  /**
   * @param PrestaShopAcumulusConfig $config
   * @param Acumulus $module
   */
  public function __construct(PrestaShopAcumulusConfig $config, Acumulus $module) {
    $this->module = $module;
    $this->acumulusConfig = $config;
    $this->webAPI = new WebAPI($this->acumulusConfig);
  }

  /**
   * Gets the translation for a given text.
   *
   * Helper method that just shortens the usage of translation.
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
   * Renders the configuration page.
   *
   * @return string
   */
  public function getContent() {
    $output = '';

    $values = array();
    if (Tools::isSubmit('submit' . $this->module->name)) {
      foreach ($this->acumulusConfig->getKeys() as $key) {
        $postKey = $key;
        $default = NULL;
        if ($key === 'overwriteIfExists') {
          $postKey .= '_1';
          $default = 0;
        }
        $values[$key] = Tools::getValue($postKey, $default);
        if ($key === 'password' && empty($values[$key])) {
          $values[$key] = $this->acumulusConfig->get('password');
        }
      }
      $output .= $this->processForm($values);
    }
    $output .= $this->getForm($values);

    return $output;
  }

  /**
   * Processes a submitted config form.
   *
   * @param array $values
   *
   * @return string
   *   HTML output.
   */
  protected function processForm(array $values) {
    $output = '';
    if (!$this->validateForm($values, $output)) {
      return $output;
    }
    else {
      $this->acumulusConfig->castValues($values);
      $this->acumulusConfig->save($values);
      $output .= $this->module->displayConfirmation($this->t('message_config_saved'));
      return $output;
    }
  }

  /**
   * Validates the form submission.
   *
   * @param array $values
   * @param string $output
   *
   * @return bool
   */
  protected function validateForm(array $values, &$output) {
    $messages = $this->acumulusConfig->validateValues($values);
    foreach ($messages as $message) {
      $output .= $this->module->displayError($this->t($message));
    }
    return empty($messages);
  }

  /**
   * @param array $values
   *
   * @return string
   */
  protected function getForm(array $values) {
    // Get default Language.
    $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

    // Init Form Fields.
    $fields_form[0]['form'] = array(
      'legend' => array(
        'title' => $this->t('button_settings'),
      ),
      'input' => $this->getFormFields(),
      'submit' => array(
        'title' => $this->t('button_save'),
        'class' => 'button'
      )
    );

    // Create and initialize form helper.
    $helper = new HelperForm();

    // Module, token and currentIndex.
    $helper->module = $this->module;
    $helper->name_controller = $this->module->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->module->name;

    // Language.
    $helper->default_form_language = $default_lang;
    $helper->allow_employee_form_lang = $default_lang;

    // Title and toolbar.
    $helper->title = $this->module->displayName;
    $helper->show_toolbar = true; // false -> remove toolbar
    $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit' . $this->module->name;
    $helper->toolbar_btn = array(
      'save' => array(
        'desc' => $this->t('button_save'),
        'href' => AdminController::$currentIndex . '&configure=' . $this->module->name . '&save' . $this->module->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
      ),
      'back' => array(
        'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
        'desc' => $this->t('button_back')
      )
    );

    // Load current values.
    $helper->fields_value = $values + $this->acumulusConfig->getCredentials() + $this->acumulusConfig->getInvoiceSettings();
    $helper->fields_value['overwriteIfExists_1'] = $helper->fields_value['overwriteIfExists'];
    // Add the "values" for the free elements.
    $env = $this->acumulusConfig->getEnvironment();
    $helper->fields_value['legend1'] = '';
    $helper->fields_value['legend2'] = '';
    $helper->fields_value['message2'] = $this->message;
    $helper->fields_value['legend3'] = '';
    $helper->fields_value['message3'] = "<div class='message'>Acumulus module {$env['moduleVersion']} (API: {$env['libraryVersion']}) voor {$env['shopName']} {$env['shopVersion']}</div>";
    $helper->fields_value['description3'] = '<p class="description">' . $this->t('desc_versionInformation') . '</p>';
    array_filter($helper->fields_value);

    return $helper->generateForm($fields_form);
  }

  protected function getFormFields() {
    $result = array();

    // 1st fieldset: Acumulus account settings.
    $fieldset = array();
    $fieldset[] = array(
      'type' => 'text',
      'label' => $this->t('field_code'),
      'name' => 'contractcode',
      'size' => 20,
      'required' => true,
    );
    $fieldset[] = array(
      'type' => 'text',
      'label' => $this->t('field_username'),
      'name' => 'username',
      'size' => 20,
      'required' => true,
    );
    $fieldset[] = array(
      'type' => 'password',
      'label' => $this->t('field_password'),
      'name' => 'password',
      'size' => 20,
      'required' => true,
    );
    $fieldset[] = array(
      'type' => 'text',
      'label' => $this->t('field_email'),
      'name' => 'emailonerror',
      'desc' => $this->t('desc_email'),
      'size' => 30,
      'required' => true,
    );

    // Fieldsets seem to be impossible. Add all fields at the same level with a
    // free field before them.
    $result[] = array(
      'type' => 'free',
      'label' => '<h2>' . $this->t('accountSettingsHeader') . '</h2>',
      'name' => 'legend1',
      'required' => false,
    );
    $result = array_merge($result, $fieldset);

    // 2nd fieldset: invoice settings.
    $fieldset = array();

    // Check if we can retrieve a picklist. This indicates if the account
    $this->message = $this->checkAccountSettings();

    if ($this->message) {
      $fieldset[] = array(
        'type' => 'free',
        'label' => '',
        'name' => 'message2',
        'required' => false,
      );
    }
    else {
      $options = array(
        array(
          'id'    => 'use_shop_invoice',
          'value' => ConfigInterface::InvoiceNrSource_ShopInvoice,
          'label' => $this->t('option_invoiceNrSource_1')
        ),
        array(
          'id'    => 'use_shop_order',
          'value' => ConfigInterface::InvoiceNrSource_ShopOrder,
          'label' => $this->t('option_invoiceNrSource_2')
        ),
        array(
          'id'    => 'use_acumulus',
          'value' => ConfigInterface::InvoiceNrSource_Acumulus,
          'label' => $this->t('option_invoiceNrSource_3'),
        ),
      );
      $fieldset[] = array(
        'type' => 'radio',
        'class' => 't',
        'label' => $this->t('field_invoiceNrSource'),
        'desc' => $this->t('desc_invoiceNrSource'),
        'name' => 'invoiceNrSource',
        'values' => $options,
        'required' => true,
      );

      $options = array(
        array(
          'id'    => 'use_invoicedate',
          'value' => ConfigInterface::InvoiceDate_InvoiceCreate,
          'label' => $this->t('option_dateToUse_1'),
        ),
        array(
          'id'    => 'use_orderdate',
          'value' => ConfigInterface::InvoiceDate_OrderCreate,
          'label' => $this->t('option_dateToUse_2')
        ),
        array(
          'id'    => 'use_transferdate',
          'value' => ConfigInterface::InvoiceDate_Transfer,
          'label' => $this->t('option_dateToUse_3')
        ),
      );
      $fieldset[] = array(
        'type' => 'radio',
        'class' => 't',
        'label' => $this->t('field_dateToUse'),
        'desc' => $this->t('desc_dateToUse'),
        'name' => 'dateToUse',
        'values' => $options,
        'required' => true,
      );

      $options = $this->contactTypes;
      $options = $options['contacttypes'];
      array_unshift($options, array('contacttypeid' => 0, 'contacttypename' => $this->t('option_empty')));
      $fieldset[] = array(
        'type' => 'select',
        'label' => $this->t('field_defaultCustomerType'),
        'name' => 'defaultCustomerType',
        'options' => array(
          'query' => $options,
          'id' => 'contacttypeid',
          'name' => 'contacttypename'
        ),
        'required' => false,
      );

      $options = array(
        array(
          'id' => 'overwrite',
          'name' => $this->t('option_overwriteIfExists'),
          'val' => 1,
        ),
      );
      $fieldset[] = array(
        'type' => 'checkbox',
        'label' => $this->t('field_overwriteIfExists'),
        'desc' => $this->t('desc_overwriteIfExists'),
        'name' => 'overwriteIfExists',
        'values' => array(
          'query' => $options,
          'id' => 'val',
          'name' => 'name'
        ),
      );

      $options = $this->webAPI->getPicklistAccounts();
      $options = $options['accounts'];
      array_unshift($options, array('accountid' => 0, 'accountnumber' => $this->t('option_empty')));
      $fieldset[] = array(
        'type' => 'select',
        'label' => $this->t('field_defaultAccountNumber'),
        'desc' => $this->t('desc_defaultAccountNumber'),
        'name' => 'defaultAccountNumber',
        'options' => array(
          'query' => $options,
          'id' => 'accountid',
          'name' => 'accountnumber'
        ),
        'required' => false,
      );

      $options = $this->webAPI->getPicklistCostCenters();
      $options = $options['costcenters'];
      array_unshift($options, array('costcenterid' => 0, 'costcentername' => $this->t('option_empty')));
      $fieldset[] = array(
        'type' => 'select',
        'label' => $this->t('field_defaultCostHeading'),
        'desc' => $this->t('desc_defaultCostHeading'),
        'name' => 'defaultCostHeading',
        'options' => array(
          'query' => $options,
          'id' => 'costcenterid',
          'name' => 'costcentername'
        ),
        'required' => false,
      );

      $options = $this->webAPI->getPicklistInvoiceTemplates();
      $options = $options['invoicetemplates'];
      array_unshift($options, array('invoicetemplateid' => 0, 'invoicetemplatename' => $this->t('option_empty')));
      $fieldset[] = array(
        'type' => 'select',
        'label' => $this->t('field_defaultInvoiceTemplate'),
        'desc' => $this->t('desc_defaultInvoiceTemplate'),
        'name' => 'defaultInvoiceTemplate',
        'options' => array(
          'query' => $options,
          'id' => 'invoicetemplateid',
          'name' => 'invoicetemplatename'
        ),
        'required' => false,
      );

      $options = array_values(OrderState::getOrderStates((int) Context::getContext()->language->id));
      array_unshift($options, array('id_order_state' => 0, 'name' => $this->t('option_empty_triggerOrderStatus')));
      $fieldset[] = array(
        'type' => 'select',
        'label' => $this->t('field_triggerOrderStatus'),
        'desc' => $this->t('desc_triggerOrderStatus'),
        'name' => 'triggerOrderStatus',
        'options' => array(
          'query' => $options,
          'id' => 'id_order_state',
          'name' => 'name'
        ),
        'required' => false,
      );

    }

    // Using <fieldset>s seem to be impossible. Add all fields at the same level
    // with a free field before them.
    $result[] = array(
      'type' => 'free',
      'label' => '<h2>' . $this->t('invoiceSettingsHeader') . '</h2>',
      'name' => 'legend2',
      'required' => false,
    );
    $result = array_merge($result, $fieldset);

    // 3rd fieldset: version information.
    $fieldset = array();

    $fieldset[] = array(
      'type' => 'free',
      'label' => '',
      'name' => 'message3',
      'required' => false,
    );

    $fieldset[] = array(
      'type' => 'free',
      'label' => '',
      'name' => 'description3',
      'required' => false,
    );

    $result[] = array(
      'type' => 'free',
      'label' => '<h2>' . $this->t('versionInformationHeader') . '</h2>',
      'name' => 'legend3',
      'required' => false,
    );
    $result = array_merge($result, $fieldset);

//    $result = array_merge($result, $this->getTestFields());

    return $result;
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
  protected function checkAccountSettings() {
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

  protected function getTestFields() {
    $output = array();
    if ($this->acumulusConfig->getDebug()) {
      $file = dirname(__FILE__) . '/lib/Siel/Acumulus/PrestaShop/Test/TestForm.php';
      if (is_file($file)) {
        require_once($file);
        $testForm = new TestForm($this->acumulusConfig, $this->module);
        $output = $testForm->getContent();
      }
    }
    return $output;
  }
}
