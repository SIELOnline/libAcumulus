<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Invoice\ConfigInterface as InvoiceConfigInterface;

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
        $this->validateRelationFields();
        $this->validateEmailAsPdfFields();
    }

    /**
     * Validates fields in the relation management settings fieldset.
     */
    protected function validateRelationFields()
    {
//        $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';

        if (isset($this->submittedValues['emailAsPdf']) && (bool) $this->submittedValues['emailAsPdf'] && (!array_key_exists('sendCustomer', $this->submittedValues) || !(bool) $this->submittedValues['sendCustomer'])) {
            $this->errorMessages['conflicting_options'] = $this->t('message_validate_conflicting_options');
        }
//        if (!empty($this->submittedValues['genericCustomerEmail']) && !preg_match($regexpEmail, $this->submittedValues['genericCustomerEmail'])) {
//            $this->errorMessages['genericCustomerEmail'] = $this->t('message_validate_email_2');
//        }
    }

    /**
     * Validates fields in the "Email as pdf" settings fieldset.
     */
    protected function validateEmailAsPdfFields()
    {
        $regexpMultiEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+([,;][^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+)*$/';

        if (!empty($this->submittedValues['emailBcc']) && !preg_match($regexpMultiEmail, $this->submittedValues['emailBcc'])) {
            $this->errorMessages['emailBcc'] = $this->t('message_validate_email_3');
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
            $fields += array(
                'relationSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('relationSettingsHeader'),
                    'description' => $this->t('desc_relationSettingsHeader'),
                    'fields' => $this->getRelationFields(),
                ),
                'invoiceSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceSettingsHeader'),
                    'fields' => $this->getInvoiceFields(),
                ),
                'emailAsPdfSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('emailAsPdfSettingsHeader'),
                    'description' => $this->t('desc_emailAsPdfSettings'),
                    'fields' => $this->getEmailAsPdfFields(),
                ),
                'versionInformationHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('versionInformationHeader'),
                    'fields' => $this->getVersionInformation(),
                ),
            );
        }

        return $fields;
    }

    /**
     * Returns the set of relation management fields.
     *
     * The fields returned:
     * - defaultCustomerType
     * - contactStatus
     * - salutation
     * - clientData
     *
     * @return array[]
     *   The set of relation management fields.
     */
    protected function getRelationFields()
    {
        $fields = array(
            'defaultCustomerType' => array(
                'type' => 'select',
                'label' => $this->t('field_defaultCustomerType'),
                'options' => $this->picklistToOptions($this->contactTypes, 'contacttypes', 0, $this->t('option_empty')),
            ),
            'contactStatus' => array(
                'type' => 'radio',
                'label' => $this->t('field_contactStatus'),
                'description' => $this->t('desc_contactStatus'),
                'options' => $this->getContactStatusOptions(),
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
        );
        return $fields;
    }

    /**
     * Returns the set of invoice related fields.
     *
     * The fields returned:
     * - removeEmptyShipping
     *
     * @return array[]
     *   The set of invoice related fields.
     */
    protected function getInvoiceFields()
    {
        $fields = array(
            'removeEmptyShipping' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_removeEmptyShipping'),
                'description' => $this->t('desc_removeEmptyShipping'),
                'options' => array(
                    'removeEmptyShipping' => $this->t('option_removeEmptyShipping'),
                ),
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
            'emailBcc' => array(
                'type' => 'email',
                'label' => $this->t('field_emailBcc'),
                'description' => $this->t('desc_emailBcc'),
                'attributes' => array(
                    'multiple' => true,
                    'size' => 30,
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
     * {@inheritdoc}
     */
    protected function getCheckboxKeys()
    {
        return array(
            'sendCustomer' => 'clientData',
            'overwriteIfExists' => 'clientData',
            'removeEmptyShipping' => 'removeEmptyShipping',
        );
    }

    protected function getContactStatusOptions()
    {
        return array(
            InvoiceConfigInterface::ContactStatus_Active => $this->t('option_contactStatus_Active'),
            InvoiceConfigInterface::ContactStatus_Disabled => $this->t('option_contactStatus_Disabled'),
        );
    }
}
