<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

/**
 * Provides advanced config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - systemValidate()
 * - isSubmitted()
 * - setSubmittedValues()
 */
class AdvancedConfigForm extends BaseConfigForm
{
    /**
     * Array of key => value pairs that can be used in a select and that
     * represent all costcenters defined in Acumulus for the given account.
     *
     * @var array
     */
    protected $costCenterOptions;

    /**
     * Array of key => value pairs that can be used in a select and that
     * represent all accounts defined in Acumulus for the given account.
     *
     * @var array
     */
    protected $accountNumberOptions;

    /**
     * {@inheritdoc}
     *
     * The results are restricted to the known config keys.
     */
    protected function setSubmittedValues()
    {
        $postedValues = $this->getPostedValues();
        // Check if the full form was displayed or only the account details.
        $fullForm = array_key_exists('salutation', $postedValues);
        foreach ($this->acumulusConfig->getKeys() as $key) {
            if (!$this->addIfIsset($this->submittedValues, $key, $postedValues)) {
                // Add unchecked checkboxes, but only if the full form was
                // displayed as all checkboxes on this form appear in the full
                // form only.
                if ($fullForm && $this->isCheckboxKey($key)) {
                    $this->submittedValues[$key] = '';
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validate()
    {
        $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';
        $regexpMultiEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+([,;][^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+)*$/';

        if (empty($this->submittedValues['contractcode'])) {
            $this->errorMessages['contractcode'] = $this->t('message_validate_contractcode_0');
        } elseif (!is_numeric($this->submittedValues['contractcode'])) {
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
        } else if (!preg_match($regexpEmail, $this->submittedValues['emailonerror'])) {
            $this->errorMessages['emailonerror'] = $this->t('message_validate_email_0');
        }
        if (!empty($this->submittedValues['genericCustomerEmail']) && !preg_match($regexpEmail, $this->submittedValues['genericCustomerEmail'])) {
            $this->errorMessages['genericCustomerEmail'] = $this->t('message_validate_email_2');
        }
        if (!empty($this->submittedValues['emailFrom']) && !preg_match($regexpEmail, $this->submittedValues['emailFrom'])) {
            $this->errorMessages['emailFrom'] = $this->t('message_validate_email_4');
        }
        if (!empty($this->submittedValues['emailBcc']) && !preg_match($regexpMultiEmail, $this->submittedValues['emailBcc'])) {
            $this->errorMessages['emailBcc'] = $this->t('message_validate_email_3');
        }
        if (isset($this->submittedValues['emailAsPdf']) && (bool) $this->submittedValues['emailAsPdf'] && (!array_key_exists('sendCustomer', $this->submittedValues) || !(bool) $this->submittedValues['sendCustomer'])) {
            $this->errorMessages['conflicting_options'] = $this->t('message_validate_conflicting_options');
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the config form. At the minimum, this includes the
     * account settings. If these are OK, the other settings are included as
     * well.
     */
    public function getFieldDefinitions()
    {
        $fields = array();

        $message = $this->checkAccountSettings();
        $accountOk = empty($message);

        // Message fieldset: if account settings have not been filled in.
        if (!$accountOk) {
            $fields['accountSettingsHeader'] = array(
              'type' => 'fieldset',
              'legend' => $this->t('message_error_header'),
              'fields' => array(
                'invoiceMessage' => array(
                  'type' => 'markup',
                  'value' => $message,
                ),
              ),
            );
        }

        // 1st fieldset: Link to config form.
        $fields['configHeader'] = array(
            'type' => 'fieldset',
            'legend' => $this->t('config_form_header'),
            'fields' => $this->getConfigLinkFields(),
        );

        if ($accountOk) {
            $fields['invoiceSettingsHeader'] = array(
                'type' => 'fieldset',
                'legend' => $this->t('invoiceSettingsHeader'),
            );
            $fields['invoiceSettingsHeader']['fields'] = $this->getInvoiceFields();

            // 3rd and 4th fieldset. Settings per active payment method.
            $paymentMethods = $this->shopCapabilities->getPaymentMethods();
            if (!empty($paymentMethods)) {
                $fields["paymentMethodAccountNumberFieldset"] = $this->getPaymentMethodsFieldset($paymentMethods, 'paymentMethodAccountNumber', $this->accountNumberOptions);
                $fields["paymentMethodCostCenterFieldset"] = $this->getPaymentMethodsFieldset($paymentMethods, 'paymentMethodCostCenter', $this->costCenterOptions);
            }

            // 5th fieldset: email as PDF settings.
            $fields['emailAsPdfSettingsHeader'] = array(
                'type' => 'fieldset',
                'legend' => $this->t('emailAsPdfSettingsHeader'),
                'description' => $this->t('desc_emailAsPdfInformation'),
                'fields' => $this->getEmailAsPdfFields(),
            );

            // 6th fieldset: Acumulus version information.
            $fields['versionInformationHeader'] = array(
                'type' => 'fieldset',
                'legend' => $this->t('versionInformationHeader'),
                'fields' => $this->getPluginFields(),
            );
        }

        return $fields;
    }

    /**
     * Returns the set of invoice related fields.
     *
     * The fields returned:
     * - digitalServices
     * - vatFreeProducts
     * - dateToUse
     * - defaultCustomerType
     * - salutation
     * - clientData
     * - defaultAccountNumber
     * - defaultCostCenter
     * - defaultInvoiceTemplate
     * - defaultInvoicePaidTemplate
     * - removeEmptyShipping
     * - triggerOrderStatus
     * - triggerInvoiceEvent
     *
     * @return array[]
     *   The set of invoice related fields.
     */
    protected function getInvoiceFields()
    {
        $invoiceNrSourceOptions = $this->shopCapabilities->getInvoiceNrSourceOptions();
        if (count($invoiceNrSourceOptions) === 1) {
            // Make it a hidden field.
            $invoiceNrSourceField = array(
                'type' => 'hidden',
                'value' => reset($invoiceNrSourceOptions),
            );
        } else {
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

        $dateToUseOptions = $this->shopCapabilities->getDateToUseOptions();
        if (count($dateToUseOptions) === 1) {
            // Make it a hidden field.
            $dateToUseField = array(
                'type' => 'hidden',
                'value' => reset($dateToUseOptions),
            );
        } else {
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

        $invoiceTriggerEventOptions = $this->shopCapabilities->getInvoiceTriggerEvents();
        if (count($invoiceTriggerEventOptions) === 1) {
            // Make it a hidden field.
            $invoiceTriggerEventField = array(
                'type' => 'hidden',
                'value' => reset($invoiceTriggerEventOptions),
            );
        } else {
            $invoiceTriggerEventField = array(
                'type' => 'select',
                'label' => $this->t('field_triggerInvoiceEvent'),
                'description' => $this->t($this->t('desc_triggerInvoiceEvent')),
                'options' => $invoiceTriggerEventOptions,
            );
        }

        $this->accountNumberOptions = $this->picklistToOptions($this->service->getPicklistAccounts(), 'accounts', 0, $this->t('option_empty'));
        $this->costCenterOptions = $this->picklistToOptions($this->service->getPicklistCostCenters(), 'costcenters', 0, $this->t('option_empty'));
        $orderStatuses = $this->getOrderStatusesList();

        $fields = array(
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
                'options' => $this->accountNumberOptions,
            ),
            'defaultCostCenter' => array(
                'type' => 'select',
                'label' => $this->t('field_defaultCostCenter'),
                'description' => $this->t('desc_defaultCostCenter'),
                'options' => $this->costCenterOptions,
            ),
            'defaultInvoiceTemplate' => array(
                'type' => 'select',
                'label' => $this->t('field_defaultInvoiceTemplate'),
                'options' => $this->picklistToOptions($invoiceTemplates = $this->service->getPicklistInvoiceTemplates(), 'invoicetemplates', 0, $this->t('option_empty')),
            ),
            'defaultInvoicePaidTemplate' => array(
                'type' => 'select',
                'label' => $this->t('field_defaultInvoicePaidTemplate'),
                'description' => $this->t('desc_defaultInvoiceTemplates'),
                'options' => $this->picklistToOptions($invoiceTemplates,
                    'invoicetemplates', 0, $this->t('option_same_template')),
            ),
            'removeEmptyShipping' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_removeEmptyShipping'),
                'description' => $this->t('desc_removeEmptyShipping'),
                'options' => array(
                    'removeEmptyShipping' => $this->t('option_removeEmptyShipping'),
                ),
            ),
            'triggerOrderStatus' => array(
                'name' => 'triggerOrderStatus[]',
                'type' => 'select',
                'label' => $this->t('field_triggerOrderStatus'),
                'description' => $this->t('desc_triggerOrderStatus'),
                'options' => $orderStatuses,
                'attributes' => array(
                    'multiple' => true,
                    'size' => min(count($orderStatuses), 8),
                ),
            ),
            'triggerInvoiceEvent' => $invoiceTriggerEventField,
        );
        return $fields;
    }

    /**
     * Returns the set of 'email invoice as PDF' related fields.
     *
     * The fields returned:
     * - emailAsPdf
     * - emailFrom
     * - emailBcc
     * - subject
     *
     * @return array[]
     *   The set of 'email invoice as PDF' related fields.
     */
    protected function getEmailAsPdfFields()
    {
        return array(
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
        );
    }

    /**
     * Returns the set of plugin related fields.
     *
     * The fields returned:
     * - debug
     * - logLevel
     * - versionInformation
     * - versionInformationDesc
     *
     * @return array[]
     *   The set of plugin related fields.
     */
    protected function getPluginFields()
    {
        $env = $this->acumulusConfig->getEnvironment();
        return array(
            'debug' => array(
                'type' => 'radio',
                'label' => $this->t('field_debug'),
                'description' => $this->t('desc_debug'),
                'options' => array(
                    WebConfigInterface::Debug_None => $this->t('option_debug_1'),
                    WebConfigInterface::Debug_SendAndLog => $this->t('option_debug_2'),
                    WebConfigInterface::Debug_TestMode => $this->t('option_debug_3'),
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
                    Log::Notice => $this->t('option_logLevel_3'),
                    Log::Info => $this->t('option_logLevel_4'),
                    Log::Debug => $this->t('option_logLevel_5'),
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
        );
    }

    /**
     * Returns the set of fields introducing the advanced config forms.
     *
     * The fields returned:
     * - tellAboutAdvancedSettings
     * - advancedSettingsLink
     *
     * @return array[]
     *   The set of fields introducing the advanced config form.
     */
    protected function getConfigLinkFields()
    {
        return array(
            'tellAboutBasicSettings' => array(
                'type' => 'markup',
                'value' => sprintf($this->t('desc_basicSettings'), $this->t('config_form_link_text'), $this->t('menu_basicSettings')),
            ),
            'basicSettingsLink' => array(
                'type' => 'markup',
                'value' => sprintf($this->t('button_link'), $this->t('config_form_link_text') , $this->shopCapabilities->getLink('config')),
            ),
        );
    }

    /**
     * Returns a fieldset with a select per payment method.
     *
     * @param array $paymentMethods
     *   Array of payment methods (id => label)
     * @param string $key
     *   Prefix of the keys to use for the different ids.
     * @param array $options
     *   Options for all the selects.
     *
     * @return array
     *   The fieldset definition.
     */
    protected function getPaymentMethodsFieldset(array $paymentMethods, $key, array $options) {
        $fieldset = array(
            'type' => 'fieldset',
            'legend' => $this->t("{$key}Fieldset"),
            'description' => $this->t("desc_{$key}Fieldset"),
            'fields' => array(),
        );

        $options[0] = $this->t('option_use_default');
        foreach ($paymentMethods as $paymentMethodId => $paymentMethodLabel) {
            $fieldset['fields']["{$key}[{$paymentMethodId}]"] = array(
                'type' => 'select',
                'label' => $paymentMethodLabel,
                'options' => $options,
            );
        }
        return $fieldset;
    }

    /**
     * {@inheritdoc}
     */
    protected function getCheckboxKeys()
    {
        return array(
            'sendCustomer' => 'clientData',
            'overwriteIfExists' => 'clientData',
            'removeEmptyShipping' => 'removeEmptyShipping',
            'emailAsPdf' => 'emailAsPdf',
        );
    }
}
