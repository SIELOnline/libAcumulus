<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

/**
 * Provides basic config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - systemValidate()
 * - isSubmitted()
 * - setSubmittedValues()
 */
class ConfigForm extends BaseConfigForm
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
        $this->validateAccountFields();
        $this->validateEmailAsPdfFields();
    }

    /**
     * Validates fields in the account settings fieldset.
     */
    protected function validateAccountFields()
    {
        $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';

        if (empty($this->submittedValues['contractcode'])) {
            $this->errorMessages['contractcode'] = $this->t('message_validate_contractcode_0');
        } elseif (!is_numeric($this->submittedValues['contractcode'])) {
            $this->errorMessages['contractcode'] = $this->t('message_validate_contractcode_1');
        } else {
            // Prevent errors where a copy & paste of the contractcode from the
            // welcome mail includes spaces or tabs before or after the code.
            $this->submittedValues['contractcode'] = trim($this->submittedValues['contractcode']);
        }

        if (empty($this->submittedValues['username'])) {
            $this->errorMessages['username'] = $this->t('message_validate_username_0');
        } elseif ($this->submittedValues['username'] !== trim($this->submittedValues['username'])) {
            $this->warningMessages['username'] = $this->t('message_validate_username_1');
        }

        if (empty($this->submittedValues['password'])) {
            $this->errorMessages['password'] = $this->t('message_validate_password_0');
        } elseif ($this->submittedValues['password'] !== trim($this->submittedValues['password'])) {
            $this->warningMessages['password'] = $this->t('message_validate_password_1');
        } elseif (strpbrk($this->submittedValues['password'], '`\'"#%&;<>\\') !== false) {
            $this->warningMessages['password'] = $this->t('message_validate_password_2');
        }

        if (empty($this->submittedValues['emailonerror'])) {
            $this->errorMessages['emailonerror'] = $this->t('message_validate_email_1');
        } else if (!preg_match($regexpEmail, $this->submittedValues['emailonerror'])) {
            $this->errorMessages['emailonerror'] = $this->t('message_validate_email_0');
        }
    }

    /**
     * Validates fields in the "Email as pdf" settings fieldset.
     */
    protected function validateEmailAsPdfFields()
    {
        $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';

        if (!empty($this->submittedValues['emailFrom']) && !preg_match($regexpEmail, $this->submittedValues['emailFrom'])) {
            $this->errorMessages['emailFrom'] = $this->t('message_validate_email_4');
        }
        // @todo: how to handle this one, now they are on separate forms?
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

        // 1st fieldset: Acumulus account settings.
        $fields['accountSettingsHeader'] = array(
            'type' => 'fieldset',
            'legend' => $this->t('accountSettingsHeader'),
            'description' => $this->t('desc_accountSettings'),
            'fields' => $this->getAccountFields()
        );

        $message = $this->checkAccountSettings();
        $accountOk = empty($message);

        if (!$accountOk) {
            $fields['accountSettingsHeaderMessage'] = array(
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

        if ($accountOk) {
            $fields += array(
                'shopSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('shopSettingsHeader'),
                    'description' => $this->t('desc_shopSettings'),
                    'fields' => $this->getShopFields(),
                ),
                'triggerSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('triggerSettingsHeader'),
                    'description' => sprintf($this->t('desc_triggerSettings'), $this->shopCapabilities->getLink('batch')),
                    'fields' => $this->getTriggerFields(),
                ),
                'invoiceSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceSettingsHeader'),
                    'fields' => $this->getInvoiceFields(),
                ),
            );

            $paymentMethods = $this->shopCapabilities->getPaymentMethods();
            if (!empty($paymentMethods)) {
                $fields += array(
                    'paymentMethodAccountNumberFieldset' => $this->getPaymentMethodsFieldset($paymentMethods, 'paymentMethodAccountNumber', $this->accountNumberOptions),
                    'paymentMethodCostCenterFieldset' => $this->getPaymentMethodsFieldset($paymentMethods, 'paymentMethodCostCenter', $this->costCenterOptions),
                );
            }

            $fields['emailAsPdfSettingsHeader'] = array(
                'type' => 'fieldset',
                'legend' => $this->t('emailAsPdfSettingsHeader'),
                'description' => $this->t('desc_emailAsPdfSettings'),
                'fields' => $this->getEmailAsPdfFields(),
            );
        }

        $fields += array(
            'pluginSettingsHeader' => array(
                'type' => 'fieldset',
                'legend' => $this->t('pluginSettingsHeader'),
                'fields' => $this->getPluginFields(),
            ),
            'versionInformationHeader' => array(
                'type' => 'fieldset',
                'legend' => $this->t('versionInformationHeader'),
                'fields' => $this->getVersionInformation(),
            ),
            'advancedConfigHeader' => array(
                'type' => 'fieldset',
                'legend' => $this->t('advanced_form_header'),
                'fields' => $this->getAdvancedConfigLinkFields(),
            ),
        );

        return $fields;
    }

    /**
     * Returns the set of account related fields.
     *
     * The fields returned:
     * - contractcode
     * - username
     * - password
     * - emailonerror
     *
     * @return array[]
     *   The set of account related fields.
     */
    protected function getAccountFields()
    {
        return array(
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
        );
    }

    /**
     * Returns the set of invoice related fields.
     *
     * The fields returned:
     * - digitalServices
     * - vatFreeProducts
     *
     * @return array[]
     *   The set of shop related fields.
     */
    protected function getShopFields()
    {
        $fields = array(
            'digitalServices' => array(
                'type' => 'radio',
                'label' => $this->t('field_digitalServices'),
                'description' => $this->t('desc_digitalServices'),
                'options' => $this->getDigitalServicesOptions(),
                'attributes' => array(
                    'required' => true,
                ),
            ),
            'vatFreeProducts' => array(
                'type' => 'radio',
                'label' => $this->t('field_vatFreeProducts'),
                'description' => $this->t('desc_vatFreeProducts'),
                'options' => $this->getVatFreeProductsOptions(),
                'attributes' => array(
                    'required' => true,
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns the set of trigger related fields.
     *
     * The fields returned:
     * - triggerOrderStatus
     * - triggerInvoiceEvent
     *
     * @return array[]
     *   The set of trigger related fields.
     */
    protected function getTriggerFields()
    {
        $fields = array(
            'triggerOrderStatus' => array(
                'name' => 'triggerOrderStatus[]',
                'type' => 'select',
                'label' => $this->t('field_triggerOrderStatus'),
                'description' => $this->t('desc_triggerOrderStatus'),
                'options' => $this->getOrderStatusesList(),
                'attributes' => array(
                    'multiple' => true,
                    'size' => min(count($this->getOrderStatusesList()), 8),
                ),
            ),
            // @todo: multi-select? if we change this to multi select, none should no longer be an option.
            'triggerInvoiceEvent' => $this->getOptionsOrHiddenField('triggerInvoiceEvent', 'radio'),
        );
        return $fields;
    }

    /**
     * Returns the set of invoice related fields.
     *
     * The fields returned:
     * - invoiceNrSource
     * - dateToUse
     * - defaultCustomerType
     * - salutation
     * - clientData
     * - defaultAccountNumber
     * - defaultCostCenter
     * - defaultInvoiceTemplate
     * - defaultInvoicePaidTemplate
     *
     * @return array[]
     *   The set of invoice related fields.
     */
    protected function getInvoiceFields()
    {
        $this->accountNumberOptions = $this->picklistToOptions($this->service->getPicklistAccounts(), 'accounts', 0, $this->t('option_empty'));
        $this->costCenterOptions = $this->picklistToOptions($this->service->getPicklistCostCenters(), 'costcenters', 0, $this->t('option_empty'));

        $fields = array(
            'invoiceNrSource' => $this->getOptionsOrHiddenField('invoiceNrSource', 'radio'),
            'dateToUse' => $this->getOptionsOrHiddenField('dateToUse', 'radio'),
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
                'description' => $this->t('desc_defaultInvoiceTemplate'),
                'options' => $this->picklistToOptions($invoiceTemplates,
                    'invoicetemplates', 0, $this->t('option_same_template')),
            ),
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
    protected function getAdvancedConfigLinkFields()
    {
        return array(
            'tellAboutAdvancedSettings' => array(
                'type' => 'markup',
                'value' => sprintf($this->t('desc_advancedSettings'), $this->t('advanced_form_link_text'), $this->t('menu_advancedSettings')),
            ),
            'advancedSettingsLink' => array(
                'type' => 'markup',
                'value' => sprintf($this->t('button_link'), $this->t('advanced_form_link_text'), $this->shopCapabilities->getLink('advanced')),
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
            'emailAsPdf' => 'emailAsPdf',
        );
    }

    /**
     * Returns a list of options for the digital services field.
     *
     * @return array
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    protected function getDigitalServicesOptions()
    {
        return array(
            InvoiceConfigInterface::DigitalServices_Both => $this->t('option_digitalServices_1'),
            InvoiceConfigInterface::DigitalServices_No => $this->t('option_digitalServices_2'),
            InvoiceConfigInterface::DigitalServices_Only => $this->t('option_digitalServices_3'),
        );
    }

    /**
     * Returns a list of options for the vat free products field.
     *
     * @return array
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    protected function getVatFreeProductsOptions()
    {
        return array(
            InvoiceConfigInterface::VatFreeProducts_Both => $this->t('option_vatFreeProducts_1'),
            InvoiceConfigInterface::VatFreeProducts_No => $this->t('option_vatFreeProducts_2'),
            InvoiceConfigInterface::VatFreeProducts_Only => $this->t('option_vatFreeProducts_3'),
        );
    }


}
