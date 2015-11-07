<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

/**
 * Provides basic config form handling.
 *
 * Shop specific overrides should - of course - implement the abstract method:
 * - getShopOrderStatuses()
 * Should typically override:
 * - getInvoiceNrSourceOptions()
 * - getDateToUseOptions()
 * - getTriggerInvoiceSendEventOptions()
 * And may optionally (have to) override:
 * - systemValidate()
 * - isSubmitted()
 * - setSubmittedValues()
 */
abstract class ConfigForm extends Form {

  /** @var \Siel\Acumulus\Shop\Config */
  protected $acumulusConfig;

  /**
   * @var array
   *   Contact types picklist result, used to test the connection, storing it in
   *   this property prevents another webservice call.
   */
  protected $contactTypes;

  /**
   * Constructor.
   *
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   * @param Config $config
   */
  public function __construct(TranslatorInterface $translator, Config $config) {
    parent::__construct($translator);

    $translations = new ConfigFormTranslations();
    $this->translator->add($translations);

    $this->acumulusConfig = $config;
  }

  /**
   * {@inheritdoc}
   *
   * This is the set of values as are stored in the config.
   */
  protected function getDefaultFormValues() {
    return $this->acumulusConfig->getCredentials() + $this->acumulusConfig->getShopSettings() + $this->acumulusConfig->getCustomerSettings() + $this->acumulusConfig->getInvoiceSettings() + $this->acumulusConfig->getEmailAsPdfSettings() + $this->acumulusConfig->getOtherSettings();
  }

  /**
   * {@inheritdoc}
   *
   * The results are restricted to the known config keys.
   */
  protected function setSubmittedValues() {
    $postedValues = $this->getPostedValues();
    // Check if the full form was displayed or only the account details.
    $fullForm = array_key_exists('salutation', $postedValues);
    foreach ($this->acumulusConfig->getKeys() as $key) {
      if (!$this->addIfIsset($this->submittedValues, $key, $postedValues)) {
        // Add unchecked checkboxes, but only if the full form was displayed as
        // all checkboxes on this form appear in the full form only.
        if ($fullForm && $this->isCheckboxKey($key)) {
          $this->submittedValues[$key] = '';
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function validate() {
    $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';
    $regexpMultiEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+([,;][^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+)*$/';

    if (empty($this->submittedValues['contractcode'])) {
      $this->errorMessages['contractcode'] = $this->t('message_validate_contractcode_0');
    }
    elseif (!is_numeric($this->submittedValues['contractcode'])) {
      $this->errorMessages['contractcode'] = $this->t('message_validate_contractcode_1');
    }
    if (empty($this->submittedValues['username'])) {
      $this->errorMessages['username'] = $this->t('message_validate_username_0');
    }
    if (empty($this->submittedValues['password'])) {
      $this->errorMessages['password'] = $this->t('message_validate_password_0');
    }
    if (empty($this->submittedValues['emailonerror'])) {
      $this->errorMessages['emailonerror'] = $this->t('message_validate_email_1');
    }
    else if (!preg_match($regexpEmail, $this->submittedValues['emailonerror'])) {
      $this->errorMessages['emailonerror'] = $this->t('message_validate_email_0');
    }
    if (!empty($this->submittedValues['genericCustomerEmail'])  && !preg_match($regexpEmail, $this->submittedValues['genericCustomerEmail'])) {
      $this->errorMessages['genericCustomerEmail'] = $this->t('message_validate_email_2');
    }
    if (!empty($this->submittedValues['emailFrom'])  && !preg_match($regexpEmail, $this->submittedValues['emailFrom'])) {
      $this->errorMessages['emailFrom'] = $this->t('message_validate_email_4');
    }
    if (!empty($this->submittedValues['emailBcc'])  && !preg_match($regexpMultiEmail, $this->submittedValues['emailBcc'])) {
      $this->errorMessages['emailBcc'] = $this->t('message_validate_email_3');
    }
    if (isset($this->submittedValues['emailAsPdf']) && (bool) $this->submittedValues['emailAsPdf'] && (!array_key_exists('sendCustomer', $this->submittedValues) || !(bool) $this->submittedValues['sendCustomer'])) {
      $this->errorMessages['conflicting_options'] = $this->t('message_validate_conflicting_options');
    }
  }

  /**
   * {@inheritdoc}
   *
   * Saves the submitted and validated form values in the configuration store.
   */
  protected function execute() {
    return $this->acumulusConfig->save($this->submittedValues);
  }

  /**
   * {@inheritdoc}
   *
   * This override returns the config form. At the minimum, this includes the
   * account settings. If these are OK, the other settings are included as well.
   */
  public function getFieldDefinitions() {
    $fields = array();

    // 1st fieldset: Acumulus account settings.
    $fields['accountSettingsHeader'] = array(
      'type' => 'fieldset',
      'legend' => $this->t('accountSettingsHeader'),
      // Account fields.
      'fields' => array(
        'contractcode' => array(
          'type' => 'text',
          'label' => $this->t('field_code'),
          'attributes' => array(
            'required' => true,
            'size' => 20,
          ),
        ),
        'username' => array(
          'type' => 'text',
          'label' => $this->t('field_username'),
          'attributes' => array(
            'required' => true,
            'size' => 20,
          ),
        ),
        'password' => array(
          'type' => 'password',
          'label' => $this->t('field_password'),
          'attributes' => array(
            'required' => true,
            'size' => 20,
          ),
        ),
        'emailonerror' => array(
          'type' => 'email',
          'label' => $this->t('field_email'),
          'description' => $this->t('desc_email'),
          'attributes' => array(
            'required' => true,
            'size' => 20,
          ),
        ),
      ),
    );

    $message = $this->checkAccountSettings();
    $accountOk = empty($message);

    // 2nd fieldset: message or invoice sending related fields.
    $fields['invoiceSettingsHeader'] = array(
      'type' => 'fieldset',
      'legend' => $this->t('invoiceSettingsHeader'),
    );
    if (!$accountOk) {
      $fields['invoiceSettingsHeader']['fields'] = array (
        'invoiceMessage' => array(
          'type' => 'markup',
          'value' => $message,
        ),
      );
    }
    else {
      $invoiceNrSourceOptions = $this->getInvoiceNrSourceOptions();
      if (count($invoiceNrSourceOptions) === 1) {
        // Make it a hidden field.
        $invoiceNrSourceField = array(
          'type' => 'hidden',
          'value' => reset($invoiceNrSourceOptions),
        );
      }
      else {
        $invoiceNrSourceField = array(
          'type' => 'radio',
          'label' => $this->t('field_invoiceNrSource'),
          'description' => $this->t('desc_invoiceNrSource'),
          'options' => $invoiceNrSourceOptions,
          'attributes' => array(
            'required' => true,
          ),
        );
      }

      $dateToUseOptions = $this->getDateToUseOptions();
      if (count($dateToUseOptions) === 1) {
        // Make it a hidden field.
        $dateToUseField = array(
          'type' => 'hidden',
          'value' => reset($dateToUseOptions),
        );
      }
      else {
        $dateToUseField = array(
          'type' => 'radio',
          'label' => $this->t('field_dateToUse'),
          'description' => $this->t($this->t('desc_dateToUse')),
          'options' => $dateToUseOptions,
          'attributes' => array(
            'required' => true,
          ),
        );
      }

      $triggerInvoiceSendEventOptions = $this->getTriggerInvoiceSendEventOptions();
      if (count($triggerInvoiceSendEventOptions) === 1) {
        // Make it a hidden field.
        $triggerInvoiceSendEventField = array(
          'type' => 'hidden',
          'value' => reset($triggerInvoiceSendEventOptions),
        );
      }
      else {
        $triggerInvoiceSendEventField = array(
          'type' => 'radio',
          'label' => $this->t('field_triggerInvoiceSendEvent'),
          'description' => $this->t($this->t('desc_triggerInvoiceSendEvent')),
          'options' => $triggerInvoiceSendEventOptions,
          'attributes' => array(
            'required' => true,
          ),
        );
      }

      $fields['invoiceSettingsHeader']['fields'] = array (
        'invoiceNrSource' => $invoiceNrSourceField,
        'dateToUse' => $dateToUseField,
        'defaultCustomerType' => array(
          'type' => 'select',
          'label' => $this->t('field_defaultCustomerType'),
          'options' => $this->picklistToOptions($this->contactTypes, 'contacttypes', 0, $this->t('option_empty')),
        ),
        'salutation' => array(
          'type' => 'text',
          'label' => $this->t('field_salutation'),
          'description' => $this->t('desc_salutation'),
          'attributes' => array(
            'size' => 30,
          ),
        ),
        'clientData' => array(
          'type' => 'checkbox',
          'label' => $this->t('field_clientData'),
          'description' => $this->t('desc_clientData'),
          'options' => array(
            'sendCustomer' => $this->t('option_sendCustomer'),
            'overwriteIfExists' => $this->t('option_overwriteIfExists'),
          ),
        ),
        'defaultAccountNumber' => array(
          'type' => 'select',
          'label' => $this->t('field_defaultAccountNumber'),
          'description' => $this->t('desc_defaultAccountNumber'),
          'options' => $this->picklistToOptions($this->acumulusConfig->getService()->getPicklistAccounts(), 'accounts', 0, $this->t('option_empty')),
        ),
        'defaultCostCenter' => array(
          'type' => 'select',
          'label' => $this->t('field_defaultCostCenter'),
          'description' => $this->t('desc_defaultCostCenter'),
          'options' => $this->picklistToOptions($this->acumulusConfig->getService()->getPicklistCostCenters(), 'costcenters', 0, $this->t('option_empty')),
        ),
        'defaultInvoiceTemplate' => array(
          'type' => 'select',
          'label' => $this->t('field_defaultInvoiceTemplate'),
          'options' => $this->picklistToOptions($invoiceTemplates = $this->acumulusConfig->getService()->getPicklistInvoiceTemplates(), 'invoicetemplates', 0, $this->t('option_empty')),
        ),
        'defaultInvoicePaidTemplate' => array(
          'type' => 'select',
          'label' => $this->t('field_defaultInvoicePaidTemplate'),
          'description' => $this->t('desc_defaultInvoiceTemplates'),
          'options' => $this->picklistToOptions($invoiceTemplates, 'invoicetemplates', 0, $this->t('option_same_template')),
        ),
        'removeEmptyShipping' => array(
          'type' => 'checkbox',
          'label' => $this->t('field_removeEmptyShipping'),
          'description' => $this->t('desc_removeEmptyShipping'),
          'options' => array(
            'removeEmptyShipping' => $this->t('option_removeEmptyShipping'),
          ),
        ),
        'triggerInvoiceSendEvent' => $triggerInvoiceSendEventField,
      );
      if (array_key_exists(ConfigInterface::TriggerInvoiceSendEvent_OrderStatus, $triggerInvoiceSendEventOptions)) {
        $options = $this->getOrderStatusesList();
        $fields['invoiceSettingsHeader']['fields']['triggerOrderStatus'] = array(
          'name' => 'triggerOrderStatus[]',
          'type' => 'select',
          'label' => $this->t('field_triggerOrderStatus'),
          'description' => $this->t('desc_triggerOrderStatus'),
          'options' => $options,
          'attributes' => array(
            'multiple' => TRUE,
            'size' => min(count($options), 8),
          ),
        );
      }
    }

    // 3rd fieldset: email as PDF settings.
    if ($accountOk) {
      $fields['emailAsPdfSettingsHeader'] = array(
        'type' => 'fieldset',
        'legend' => $this->t('emailAsPdfSettingsHeader'),
        'description' => $this->t('desc_emailAsPdfInformation'),
        'fields' => array (
          'emailAsPdf' => array(
            'type' => 'checkbox',
            'label' => $this->t('field_emailAsPdf'),
            'description' => $this->t('desc_emailAsPdf'),
            'options' => array(
              'emailAsPdf' => $this->t('option_emailAsPdf'),
            ),
          ),
          'emailFrom' => array(
            'type' => 'email',
            'label' => $this->t('field_emailFrom'),
            'description' => $this->t('desc_emailFrom'),
            'attributes' => array(
              'size' => 30,
            ),
          ),
          'emailBcc' => array(
            'type' => 'email',
            'label' => $this->t('field_emailBcc'),
            'description' => $this->t('desc_emailBcc'),
            'attributes' => array(
              'multiple' => true,
              'size' => 30,
            ),
          ),
          'subject' => array(
            'type' => 'text',
            'label' => $this->t('field_subject'),
            'description' => $this->t('desc_subject'),
            'attributes' => array(
              'size' => 60,
            ),
          ),
        ),
      );

    }

    // 4th fieldset: Acumulus version information.
    $env = $this->acumulusConfig->getEnvironment();
    $fields['versionInformationHeader'] = array(
      'type' => 'fieldset',
      'legend' => $this->t('versionInformationHeader'),
      'fields' => array (
        'debug' => array(
          'type' => 'radio',
          'label' => $this->t('field_debug'),
          'description' => $this->t('desc_debug'),
          'options' => array(
            WebConfigInterface::Debug_None => $this->t('option_debug_1'),
            WebConfigInterface::Debug_SendAndLog => $this->t('option_debug_2'),
            WebConfigInterface::Debug_TestMode => $this->t('option_debug_4'),
            WebConfigInterface::Debug_StayLocal => $this->t('option_debug_3'),
          ),
          'attributes' => array(
            'required' => true,
          ),
        ),
        'logLevel' => array(
          'type' => 'radio',
          'label' => $this->t('field_logLevel'),
          'description' => $this->t('desc_logLevel'),
          'options' => array(
            Log::None => $this->t('option_logLevel_0'),
            Log::Error => $this->t('option_logLevel_1'),
            Log::Warning => $this->t('option_logLevel_2'),
            Log::Notice => $this->t('option_logLevel_3'),
            Log::Debug => $this->t('option_logLevel_4'),
          ),
          'attributes' => array(
            'required' => true,
          ),
        ),
        'versionInformation' => array(
          'type' => 'markup',
          'value' => "<p>Application: Acumulus module {$env['moduleVersion']}; Library: {$env['libraryVersion']}; Shop: {$env['shopName']} {$env['shopVersion']};<br>" .
                     "Environment: PHP {$env['phpVersion']}; Curl: {$env['curlVersion']}; JSON: {$env['jsonVersion']}; OS: {$env['os']}.</p>",
        ),
        'versionInformationDesc' => array(
          'type' => 'markup',
          'value' => $this->t('desc_versionInformation'),
        ),

      ),
    );

    return $fields;
  }

  /**
   * Checks if the account settings are known and correct by trying to download
   * a picklist.
   *
   * @return string
   *   Message to show in the 2nd and 3rd fieldset. Empty if successful.
   */
  protected function checkAccountSettings() {
    // Check if we can retrieve a picklist. This indicates if the account
    // settings are known and correct.
    $message = '';
    $this->contactTypes = null;
    $credentials = $this->acumulusConfig->getCredentials();
    if (!empty($credentials['contractcode']) && !empty($credentials['username']) && !empty($credentials['password'])) {
      $this->contactTypes = $this->acumulusConfig->getService()->getPicklistContactTypes();
      if (!empty($this->contactTypes['errors'])) {
        $message = $this->t($this->contactTypes['errors'][0]['code'] == 401 ? 'message_error_auth' : 'message_error_comm');
        $this->errorMessages += $this->acumulusConfig->getService()->resultToMessages($this->contactTypes, false);
      }
    }
    else {
      // First fill in your account details.
      $message = $this->t('message_auth_unknown');
    }
    return $message;
  }

  /**
   * Converts a picklist response into an options list.
   *
   * @param array $picklist
   *   The picklist result structure.
   * @param string $key
   *   The key in the picklist result structure under which the actual results
   *   can be found.
   * @param string|null $emptyValue
   *   The value to use for an empty selection.
   * @param string|null $emptyText
   *   The label to use for an empty selection.
   *
   * @return array
   */
  protected function picklistToOptions(array $picklist, $key, $emptyValue = null, $emptyText = null) {
    $result = array();

    if ($emptyValue !== null) {
      $result[$emptyValue] = $emptyText;
    }
    if (!empty($key)) {
      // Take the results under the key. This is to be able to follow the
      // structure returned by the picklist services.
      $picklist = $picklist[$key];
    }
    array_walk($picklist, function ($value) use (&$result) {
      list($optionValue, $optionText) = array_values($value);
      $result[$optionValue] = $optionText;
    });

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function getCheckboxKeys() {
    return array(
      'sendCustomer' => 'clientData',
      'overwriteIfExists' => 'clientData',
      'removeEmptyShipping' => 'removeEmptyShipping',
      'emailAsPdf' => 'emailAsPdf',
    );
  }

  /**
   * Returns an option list of all order statuses including an empty choice.
   *
   * Do not override this method but implement getShopOrderStatuses() instead.
   *
   * @return array
   *   An options array of all order statuses.
   */
  protected function getOrderStatusesList() {
    $result = array();

    $result['0'] = $this->t('option_empty_triggerOrderStatus');
    $result += $this->getShopOrderStatuses();

    return $result;
  }

  /**
   * Returns an option list of all shop order statuses.
   *
   * @return array
   *   An array of all shop order statuses, with the key being the ID for
   *   the dropdown item and the value being the label for the dropdown item.
   */
  abstract protected function getShopOrderStatuses();

  /**
   * Returns a list of valid sources that can be used as invoice number.
   *
   * This may differ per shop as not all shops support invoices as a separate
   * entity.
   *
   * Overrides should typically return a subset of the constants defined in this
   * base implementation, but including at least
   * ConfigInterface::InvoiceNrSource_Acumulus.
   *
   * @return array
   *   An array keyed by the option values and having translated descriptions as
   *   values.
   */
  protected function getInvoiceNrSourceOptions() {
    return array(
      ConfigInterface::InvoiceNrSource_ShopInvoice => $this->t('option_invoiceNrSource_1'),
      ConfigInterface::InvoiceNrSource_ShopOrder => $this->t('option_invoiceNrSource_2'),
      ConfigInterface::InvoiceNrSource_Acumulus => $this->t('option_invoiceNrSource_3'),
    );
  }

  /**
   * Returns a list of valid date sources that can be used as invoice date.
   *
   * This may differ per shop as not all shops support invoices as a separate
   * entity.
   *
   * Overrides should typically return a subset of the constants defined in this
   * base implementation, but including at least
   * ConfigInterface::InvoiceDate_Transfer.
   *
   * @return array
   *   An array keyed by the option values and having translated descriptions as
   *   values.
   */
  protected function getDateToUseOptions() {
    return array(
      ConfigInterface::InvoiceDate_InvoiceCreate => $this->t('option_dateToUse_1'),
      ConfigInterface::InvoiceDate_OrderCreate => $this->t('option_dateToUse_2'),
      ConfigInterface::InvoiceDate_Transfer => $this->t('option_dateToUse_3'),
    );
  }

  /**
   * Returns a list of events that can trigger the automatic sending of an
   * invoice.
   *
   * This may differ per shop as not all shops define events for all moments
   * that can be used to trigger the sending of an invoice.
   *
   * Overrides should typically return a subset of the constants defined in this
   * base implementation. The return array may be empty or only contain
   * ConfigInterface::TriggerInvoiceSendEvent_None, to indicate that no
   * automatic sending is possible (shop does not define any event like model).
   *
   * @return array
   *   An array keyed by the option values and having translated descriptions as
   *   values.
   */
  protected function getTriggerInvoiceSendEventOptions() {
    return array(
      ConfigInterface::TriggerInvoiceSendEvent_None => $this->t('option_triggerInvoiceSendEvent_0'),
      ConfigInterface::TriggerInvoiceSendEvent_OrderStatus => $this->t('option_triggerInvoiceSendEvent_1'),
      ConfigInterface::TriggerInvoiceSendEvent_InvoiceCreate => $this->t('option_triggerInvoiceSendEvent_2'),
    );
  }

}
