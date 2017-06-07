<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\ConfigInterface;

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
     */
    protected function validate()
    {
        $this->validateRelationFields();
        $this->validateOptionsFields();
        $this->validateEmailAsPdfFields();
    }

    /**
     * Validates fields in the relation management settings fieldset.
     */
    protected function validateRelationFields()
    {
        $settings = $this->acumulusConfig->getEmailAsPdfSettings();
        if ((!array_key_exists('sendCustomer', $this->submittedValues) || !(bool) $this->submittedValues['sendCustomer']) && $settings['emailAsPdf']) {
            $this->warningMessages['conflicting_options'] = $this->t('message_validate_conflicting_options');
        }
    }

    /**
     * Validates fields in the "Invoice" settings fieldset.
     */
    protected function validateOptionsFields()
    {
        if ($this->submittedValues['optionsAllOn1Line'] == PHP_INT_MAX && $this->submittedValues['optionsAllOnOwnLine'] == 1) {
            $this->errorMessages['optionsAllOnOwnLine'] = $this->t('message_validate_options_0');
        }
        if ($this->submittedValues['optionsAllOn1Line'] > $this->submittedValues['optionsAllOnOwnLine'] && $this->submittedValues['optionsAllOnOwnLine'] > 1) {
            $this->errorMessages['optionsAllOnOwnLine'] = $this->t('message_validate_options_1');
        }

        if (isset($this->submittedValues['optionsMaxLength']) && !ctype_digit($this->submittedValues['optionsMaxLength'])) {
            $this->errorMessages['optionsMaxLength'] = $this->t('message_validate_options_2');
        }
    }

    /**
     * Validates fields in the "Email as pdf" settings fieldset.
     */
    protected function validateEmailAsPdfFields()
    {
        // Check for valid email address if no token syntax is used.
        $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';
        if (!empty($this->submittedValues['emailTo']) && strpos($this->submittedValues['emailTo'], '[') === false && !preg_match($regexpEmail, $this->submittedValues['emailTo'])) {
            $this->errorMessages['emailTo'] = $this->t('message_validate_email_5');
        }

        // Check for valid email addresses if no token syntax is used.
        $regexpMultiEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+([,;][^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+)*$/';
        if (!empty($this->submittedValues['emailBcc']) && strpos($this->submittedValues['emailBcc'], '[') === false && !preg_match($regexpMultiEmail, $this->submittedValues['emailBcc'])) {
            $this->errorMessages['emailBcc'] = $this->t('message_validate_email_3');
        }

        // Check for valid email address if no token syntax is used.
        if (!empty($this->submittedValues['emailFrom']) && strpos($this->submittedValues['emailFrom'], '[') === false && !preg_match($regexpEmail, $this->submittedValues['emailFrom'])) {
            $this->errorMessages['emailFrom'] = $this->t('message_validate_email_4');
        }

        $settings = $this->acumulusConfig->getCustomerSettings();
        if (isset($this->submittedValues['emailAsPdf']) && (bool) $this->submittedValues['emailAsPdf'] && !$settings['sendCustomer']) {
            $this->errorMessages  ['conflicting_options'] = $this->t('message_validate_conflicting_options');
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
                'tokenHelpHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('tokenHelpHeader'),
                    'description' => $this->t('desc_tokens'),
                    'fields' => $this->getTokenFields(),
                ),
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
                'invoiceLinesSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceLinesSettingsHeader'),
                    'fields' => $this->getInvoiceLinesFields(),
                ),
                'optionsSettingsHeader' => array(
                    'type' => 'fieldset',
                    'legend' => $this->t('optionsSettingsHeader'),
                    'description' => $this->t('desc_optionsSettingsHeader'),
                    'fields' => $this->getOptionsFields(),
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
     *
     *
     *
     * @return array
     *   The set of possible tokens per object
     */
    protected function getTokenFields() {
        return $this->tokenInfo2Fields($this->shopCapabilities->getTokenInfo());
    }

    /**
     * Returns a set of token info fields based on the shop specific token info.
     *
     * @param string[][][] $tokenInfo
     *
     * @return array
     *   Form fields.
     */
    protected function tokenInfo2Fields(array $tokenInfo)
    {
        $fields = array();
        foreach ($tokenInfo as $variableName => $variableInfo) {
            $fields["token-$variableName"] = $this->get1TokenField($variableName, $variableInfo);
        }
        return $fields;
    }

    /**
     * Returns a set of token info fields based on the shop specific token info.
     *
     * @param string $variableName
     * @param string[][] $variableInfo
     *
     * @return array Form fields.
     * Form fields.
     */
    protected function get1TokenField($variableName, array $variableInfo)
    {
        $value = "<p class='property-name'><strong>$variableName</strong>";

        if (!empty($variableInfo['more-info'])) {
            $value .= ' ' . $variableInfo['more-info'];
        } else {
            if (!empty($variableInfo['class'])) {
                if (!empty($variableInfo['file'])) {
                    $value .= ' (' . $this->see2Lists('see_class_file', 'see_classes_files', $variableInfo['class'], $variableInfo['file']) . ')';
                } else {
                    $value .= ' (' . $this->seeList('see_class', 'see_classes', $variableInfo['class']) . ')';
                }
            } elseif (!empty($variableInfo['table'])) {
                $value .= ' (' . $this->seeList('see_table', 'see_tables', $variableInfo['table']) . ')';
            } elseif (!empty($variableInfo['file'])) {
                $value .= ' (' . $this->seeList('see_file', 'see_files', $variableInfo['file']) . ')';
            }

            if (!empty($variableInfo['additional-info'])) {
                $value .= ' (' . $variableInfo['additional-info'] . ')';
            }
        }

        $value .= ':</p>';

        if (!empty($variableInfo['properties'])) {
            $value .= '<ul class="property-list">';
            foreach ($variableInfo['properties'] as $property) {
                $value .= "<li>$property</li>";
            }

            if (!empty($variableInfo['properties-more'])) {
                if (!empty($variableInfo['class'])) {
                    $value .= '<li>' . $this->seeList('see_class_more', 'see_classes_more', $variableInfo['class']). '</li>';
                } elseif (!empty($variableInfo['table'])) {
                    $value .= '<li>' . $this->seeList('see_table_more', 'see_tables_more', $variableInfo['table']) . '</li>';
                }
            }
            $value .= '</ul>';
        }

        return array(
            'type'=> 'markup',
            'value' => $value,
        );
    }

    /**
     * Converts the contents of $list1 and $list2 to a human readable string.
     *
     * @param string $keySingle
     * @param string $keyPlural
     * @param string|string[] $list1
     * @param string|string[] $list2
     *
     * @return string
     */
    protected function see2Lists($keySingle, $keyPlural, $list1, $list2)
    {
        $sList1 = $this->listToString($list1);
        $sList2 = $this->listToString($list2);
        $key = is_array($list1) && count($list1) > 1 ? $keyPlural : $keySingle;
        return sprintf($this->t($key), $sList1, $sList2);
    }

    /**
     * Converts the contents of $list to a human readable string.
     *
     * @param string $keySingle
     * @param string $keyPlural
     * @param string|string[] $list
     *
     * @return string
     */
    protected function seeList($keySingle, $keyPlural, $list)
    {
        $sList = $this->listToString($list);
        $key = is_array($list) && count($list) > 1 ? $keyPlural : $keySingle;
        return sprintf($this->t($key), $sList);
    }

    /**
     * Returns $list as a grammatically correct and nice string.
     *
     * @param string|string[] $list
     *
     * @return string
     */
    protected function listToString($list) {
        if (is_array($list)) {
            if (count($list) > 1) {
                $listLast = array_pop($list);
                $listBeforeLast = array_pop($list);
                array_push($list, $listBeforeLast . ' ' . $this->t('and') . ' ' . $listLast);
            }
        } else {
            $list = array($list);
        }
        return implode(', ', $list);
    }

    /**
     * Returns the set of relation management fields.
     *
     * The fields returned:
     * - sendCustomer
     * - overwriteIfExists
     * - defaultCustomerType
     * - contactStatus
     * - contactYourId
     * - companyName1
     * - companyName2
     * - vatNumber
     * - fullName
     * - salutation
     * - address1
     * - address2
     * - postalCode
     * - city
     * - telephone
     * - fax
     * - email
     * - mark
     *
     * @return array[]
     *   The set of relation management fields.
     */
    protected function getRelationFields()
    {
        $fields = array(
            'clientData' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_clientData'),
                'description' => $this->t('desc_clientData'),
                'options' => array(
                    'sendCustomer' => $this->t('option_sendCustomer'),
                    'overwriteIfExists' => $this->t('option_overwriteIfExists'),
                ),
            ),
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
            'contactYourId' => array(
                'type' => 'text',
                'label' => $this->t('field_contactYourId'),
                'description' => $this->t('desc_contactYourId') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'companyName1' => array(
                'type' => 'text',
                'label' => $this->t('field_companyName1'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'companyName2' => array(
                'type' => 'text',
                'label' => $this->t('field_companyName2'),
                'description' => $this->t('msg_tokens'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'vatNumber' => array(
                'type' => 'text',
                'label' => $this->t('field_vatNumber'),
                'description' => $this->t('desc_vatNumber') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'fullName' => array(
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'salutation' => array(
                'type' => 'text',
                'label' => $this->t('field_salutation'),
                'description' => $this->t('desc_salutation') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'address1' => array(
                'type' => 'text',
                'label' => $this->t('field_address1'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'address2' => array(
                'type' => 'text',
                'label' => $this->t('field_address2'),
                'description' => $this->t('desc_address') . ' ' . $this->t('msg_tokens'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'postalCode' => array(
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'description' => $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'city' => array(
                'type' => 'text',
                'label' => $this->t('field_city'),
                'description' => $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'telephone' => array(
                'type' => 'text',
                'label' => $this->t('field_telephone'),
                'description' => $this->t('desc_telephone') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'fax' => array(
                'type' => 'text',
                'label' => $this->t('field_fax'),
                'description' => $this->t('desc_fax') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'email' => array(
                'type' => 'text',
                'label' => $this->t('field_email'),
                'description' => $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'mark' => array(
                'type' => 'text',
                'label' => $this->t('field_mark'),
                'description' => $this->t('desc_mark') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns the set of invoice related fields.
     *
     * The fields returned:
     * - sendEmptyInvoice
     * - sendEmptyShipping
     * - description
     * - descriptionText
     * - invoiceNotes
     *
     * @return array[]
     *   The set of invoice related fields.
     */
    protected function getInvoiceFields()
    {
        $fields = array(
            'concept' => array(
                'type' => 'radio',
                'label' => $this->t('field_concept'),
                'description' => $this->t('desc_concept'),
                'options' => array(
                    ConfigInterface::Concept_Plugin => $this->t('option_concept_2'),
                    ConfigInterface::Concept_No => $this->t('option_concept_0'),
                    ConfigInterface::Concept_Yes => $this->t('option_concept_1'),
                ),
                'attributes' => array(
                    'required' => true,
                ),
            ),
            'sendWhat' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_sendWhat'),
                'description' => $this->t('desc_sendWhat'),
                'options' => array(
                    'sendEmptyInvoice' => $this->t('option_sendEmptyInvoice'),
                    'sendEmptyShipping' => $this->t('option_sendEmptyShipping'),
                ),
            ),
            'description' => array(
                'type' => 'text',
                'label' => $this->t('field_description'),
                'description' => $this->t('desc_description') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'descriptionText' => array(
                'type' => 'textarea',
                'label' => $this->t('field_descriptionText'),
                'description' => $this->t('desc_descriptionText') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                    'rows' => 6,
                    'style' => 'box-sizing: border-box; width: 100%; min-width: 24em;',
                ),
            ),
            'invoiceNotes' => array(
                'type' => 'textarea',
                'label' => $this->t('field_invoiceNotes'),
                'description' => $this->t('desc_invoiceNotes') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                    'rows' => 6,
                    'style' => 'box-sizing: border-box; width: 100%; min-width: 24em;',
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns the set of invoice line related fields.
     *
     * The fields returned:
     * - itemNumber
     * - productName
     * - nature
     * - costPrice
     *
     * @return array[]
     *   The set of invoice line related fields.
     */
    protected function getInvoiceLinesFields()
    {
        $fields = array(
            'itemNumber' => array(
                'type' => 'text',
                'label' => $this->t('field_itemNumber'),
                'description' => $this->t('desc_itemNumber') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'productName' => array(
                'type' => 'text',
                'label' => $this->t('field_productName'),
                'description' => $this->t('desc_productName') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'nature' => array(
                'type' => 'text',
                'label' => $this->t('field_nature'),
                'description' => $this->t('desc_nature'),
                'attributes' => array(
                    'size' => 30,
                ),
            ),
            'costPrice' => array(
                'type' => 'text',
                'label' => $this->t('field_costPrice'),
                'description' => $this->t('desc_costPrice') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns the set of options related fields.
     *
     * The fields returned:
     * - optionsAllOn1Line
     * - optionsAllOnOwnLine
     * - optionsMaxLength
     *
     * @return array[]
     *   The set of options related fields.
     */
    protected function getOptionsFields()
    {
        $fields = array(
            'showOptions' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_showOptions'),
                'description' => $this->t('desc_showOptions'),
                'options' => array(
                    'optionsShow' => $this->t('option_optionsShow'),
                ),
            ),
            'optionsAllOn1Line' => array(
                'type' => 'select',
                'label' => $this->t('field_optionsAllOn1Line'),
                'options' => array(
                    0 => $this->t('option_do_not_use'),
                    PHP_INT_MAX => $this->t('option_always'),
                ) + array_combine(range(1, 10), range(1, 10)),
            ),
            'optionsAllOnOwnLine' => array(
                'type' => 'select',
                'label' => $this->t('field_optionsAllOnOwnLine'),
                'options' => array(
                    PHP_INT_MAX => $this->t('option_do_not_use'),
                    1 => $this->t('option_always'),
                ) + array_combine(range(2, 10), range(2, 10)),
            ),
            'optionsMaxLength' => array(
                'type' => 'number',
                'label' => $this->t('field_optionsMaxLength'),
                'description' => $this->t('desc_optionsMaxLength'),
                'attributes' => array(
                    'min' => 1,
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
            'emailAsPdf' => array(
                'type' => 'checkbox',
                'label' => $this->t('field_emailAsPdf'),
                'description' => $this->t('desc_emailAsPdf'),
                'options' => array(
                    'emailAsPdf' => $this->t('option_emailAsPdf'),
                ),
            ),
            'emailTo' => array(
                'type' => 'email',
                'label' => $this->t('field_emailTo'),
                'description' => $this->t('desc_emailTo') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'emailBcc' => array(
                'type' => 'email',
                'label' => $this->t('field_emailBcc'),
                'description' => $this->t('desc_emailBcc') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'multiple' => true,
                    'size' => 60,
                ),
            ),
            'emailFrom' => array(
                'type' => 'email',
                'label' => $this->t('field_emailFrom'),
                'description' => $this->t('desc_emailFrom') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
                ),
            ),
            'subject' => array(
                'type' => 'text',
                'label' => $this->t('field_subject'),
                'description' => $this->t('desc_subject') . ' ' . $this->t('msg_token'),
                'attributes' => array(
                    'size' => 60,
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
     * Returns the list of possible values for the contact status field.
     *
     * @return array
     *   The list of possible values for the contact status field keyed by the
     *   value to use in the API and translated labels as the values.
     *
     */
    protected function getContactStatusOptions()
    {
        return array(
            ConfigInterface::ContactStatus_Active => $this->t('option_contactStatus_Active'),
            ConfigInterface::ContactStatus_Disabled => $this->t('option_contactStatus_Disabled'),
        );
    }
}
