<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Config\Config;

use function count;
use function is_array;

/**
 * Provides advanced config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - setSubmittedValues()
 */
class AdvancedConfigForm extends BaseConfigForm
{
    protected function validate(): void
    {
        $this->validateInvoiceFields();
        $this->validateOptionsFields();
    }

    /**
     * Validates fields in the "Invoice" settings fieldset.
     */
    protected function validateInvoiceFields(): void
    {
        if (!empty($this->submittedValues['euCommerceThresholdPercentage'])) {
            $regex = '/^((\d{1,2}(\.\d{1,3})?)|(100))%?$/';
            if (preg_match($regex, $this->submittedValues['euCommerceThresholdPercentage']) !== 1) {
                $message = sprintf($this->t('message_validate_percentage_0'), $this->t('field_eu_commerce_threshold_percentage'));
                $this->addFormMessage($message, Severity::Error, 'euCommerceThresholdPercentage');
            }
        }
    }

    /**
     * Validates fields in the "Options" settings fieldset.
     */
    protected function validateOptionsFields(): void
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        if ($this->submittedValues['optionsAllOn1Line'] == PHP_INT_MAX && $this->submittedValues['optionsAllOnOwnLine'] == 1) {
            $this->addFormMessage($this->t('message_validate_options_0'), Severity::Error, 'optionsAllOnOwnLine');
        }
        if ($this->submittedValues['optionsAllOn1Line'] > $this->submittedValues['optionsAllOnOwnLine'] && $this->submittedValues['optionsAllOnOwnLine'] > 1) {
            $this->addFormMessage($this->t('message_validate_options_1'), Severity::Error, 'optionsAllOnOwnLine');
        }

        if (isset($this->submittedValues['optionsMaxLength']) && !ctype_digit($this->submittedValues['optionsMaxLength'])) {
            $this->addFormMessage($this->t('message_validate_options_2'), Severity::Error, 'optionsMaxLength');
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the config form. At the minimum, this includes the
     * account settings. If these are OK, the other settings are included as
     * well.
     */
    protected function getFieldDefinitions(): array
    {
        $fields = [];

        $message = $this->checkAccountSettings();
        $accountStatus = $this->emptyCredentials() ? null : empty($message);

        // Message fieldset: if account settings have not been filled in.
        if ($accountStatus !== true) {
            if ($accountStatus === null) {
                $aubFields = $this->getRegisterFields();
            } else /* $accountStatus === false */ {
                $desc = ' ' . sprintf($this->t('desc_accountSettings_auth'), $this->shopCapabilities->getLink('register'));
                $aubFields = [
                    'invoiceMessage' => [
                        'type' => 'markup',
                        'value' => $this->translateAccountMessage($message) . $desc,
                    ]
                ];
            }
            $fields['accountSettings'] = [
              'type' => 'fieldset',
              'legend' => $this->t('message_error_header'),
              'fields' => $aubFields,
            ];
        }

        // 1st fieldset: Link to config form.
        $fields['configHeader'] = [
            'type' => 'details',
            'summary' => $this->t('config_form_header'),
            'fields' => $this->getConfigLinkFields(),
        ];

        if ($accountStatus) {
            $fields += [
                'tokenHelpHeader' => [
                    'type' => 'details',
                    'summary' => $this->t('tokenHelpHeader'),
                    'description' => $this->t('desc_tokens'),
                    'fields' => $this->getTokenFields(),
                ],
                'relationSettingsHeader' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('relationSettingsHeader'),
                    'description' => $this->t('desc_relationSettingsHeader'),
                    'fields' => $this->getRelationFields(),
                ],
                'invoiceSettings' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceSettingsHeader'),
                    'fields' => $this->getInvoiceFields(),
                ],
                'invoiceLinesSettingsHeader' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceLinesSettingsHeader'),
                    'fields' => $this->getInvoiceLinesFields(),
                ],
                'optionsSettingsHeader' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('optionsSettingsHeader'),
                    'description' => $this->t('desc_optionsSettingsHeader'),
                    'fields' => $this->getOptionsFields(),
                ],
            ];
        }

        $fields['versionInformation'] = $this->getAboutBlock($accountStatus);

        return $fields;
    }

    /**
     *
     *
     *
     * @return array
     *   The set of possible tokens per object
     */
    protected function getTokenFields(): array
    {
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
    protected function tokenInfo2Fields(array $tokenInfo): array
    {
        $fields = [];
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
    protected function get1TokenField(string $variableName, array $variableInfo): array
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

        return [
            'type'=> 'markup',
            'value' => $value,
        ];
    }

    /**
     * Converts the contents of $list1 and $list2 to a human-readable string.
     *
     * @param string $keySingle
     * @param string $keyPlural
     * @param string|string[] $list1
     * @param string|string[] $list2
     *
     * @return string
     */
    protected function see2Lists(string $keySingle, string $keyPlural, $list1, $list2): string
    {
        $sList1 = $this->listToString($list1);
        $sList2 = $this->listToString($list2);
        $key = is_array($list1) && count($list1) > 1 ? $keyPlural : $keySingle;
        return sprintf($this->t($key), $sList1, $sList2);
    }

    /**
     * Converts the contents of $list to a human-readable string.
     *
     * @param string $keySingle
     * @param string $keyPlural
     * @param string|string[] $list
     *
     * @return string
     */
    protected function seeList(string $keySingle, string $keyPlural, $list): string
    {
        $key = is_array($list) && count($list) > 1 ? $keyPlural : $keySingle;
        $sList = $this->listToString($list);
        return sprintf($this->t($key), $sList);
    }

    /**
     * Returns $list as a grammatically correct and nice string.
     *
     * @param string|string[] $list
     *
     * @return string
     */
    protected function listToString($list): string
    {
        if (is_array($list)) {
            if (count($list) > 1) {
                $listLast = array_pop($list);
                $listBeforeLast = array_pop($list);
                $list[] = $listBeforeLast . ' ' . $this->t('and') . ' ' . $listLast;
            }
        } else {
            $list = [$list];
        }
        return implode(', ', $list);
    }

    /**
     * Returns the set of relation management fields.
     *
     * The fields returned:
     * - 'sendCustomer'
     * - 'overwriteIfExists'
     * - 'defaultCustomerType'
     * - 'contactStatus'
     * - 'contactYourId'
     * - 'companyName1'
     * - 'companyName2'
     * - 'vatNumber'
     * - 'fullName'
     * - 'salutation'
     * - 'address1'
     * - 'address2'
     * - 'postalCode'
     * - 'city'
     * - 'telephone'
     * - 'fax'
     * - 'email'
     * - 'mark'
     *
     * @return array[]
     *   The set of relation management fields.
     */
    protected function getRelationFields(): array
    {
        $fields = [
            'clientData' => [
                'type' => 'checkbox',
                'label' => $this->t('field_clientData'),
                'description' => $this->t('desc_clientData'),
                'options' => [
                    'sendCustomer' => $this->t('option_sendCustomer'),
                    'overwriteIfExists' => $this->t('option_overwriteIfExists'),
                ],
            ],
            'defaultCustomerType' => [
                'type' => 'select',
                'label' => $this->t('field_defaultCustomerType'),
                'options' => $this->picklistToOptions($this->acumulusApiClient->getPicklistContactTypes(), 0, $this->t('option_empty')),
            ],
            'contactStatus' => [
                'type' => 'radio',
                'label' => $this->t('field_contactStatus'),
                'description' => $this->t('desc_contactStatus'),
                'options' => $this->getContactStatusOptions(),
            ],
            'contactYourId' => [
                'type' => 'text',
                'label' => $this->t('field_contactYourId'),
                'description' => $this->t('desc_contactYourId') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'companyName1' => [
                'type' => 'text',
                'label' => $this->t('field_companyName1'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'companyName2' => [
                'type' => 'text',
                'label' => $this->t('field_companyName2'),
                'description' => $this->t('msg_tokens'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'vatNumber' => [
                'type' => 'text',
                'label' => $this->t('field_vatNumber'),
                'description' => $this->t('desc_vatNumber') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'fullName' => [
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'salutation' => [
                'type' => 'text',
                'label' => $this->t('field_salutation'),
                'description' => $this->t('desc_salutation') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'address1' => [
                'type' => 'text',
                'label' => $this->t('field_address1'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'address2' => [
                'type' => 'text',
                'label' => $this->t('field_address2'),
                'description' => $this->t('desc_address') . ' ' . $this->t('msg_tokens'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'postalCode' => [
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'description' => $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'city' => [
                'type' => 'text',
                'label' => $this->t('field_city'),
                'description' => $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'telephone' => [
                'type' => 'text',
                'label' => $this->t('field_telephone'),
                'description' => $this->t('desc_telephone') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'fax' => [
                'type' => 'text',
                'label' => $this->t('field_fax'),
                'description' => $this->t('desc_fax') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'email' => [
                'type' => 'text',
                'label' => $this->t('field_email'),
                'description' => $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'mark' => [
                'type' => 'text',
                'label' => $this->t('field_mark'),
                'description' => $this->t('desc_mark') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
        ];
        if ($this->shopCapabilities->usesNewCode()) {
            $fieldsToHide = [
                'contactYourId',
                'companyName1',
                'companyName2',
                'vatNumber',
                'fullName',
                'salutation',
                'address1',
                'address2',
                'postalCode',
                'city',
                'telephone',
                'fax',
                'email',
                'mark',
            ];
            foreach ($fieldsToHide as $field) {
                $fields[$field]['type'] = 'hidden';
            }
        }
        return $fields;
    }

    /**
     * Returns the set of invoice related fields.
     *
     * The fields returned:
     * - 'sendEmptyInvoice'
     * - 'sendEmptyShipping'
     * - 'description'
     * - 'descriptionText'
     * - 'invoiceNotes'
     *
     * @return array[]
     *   The set of invoice related fields.
     *
     * @noinspection PhpMemberCanBePulledUpInspection : ConfigForm has a
     *   method with the same name but a different result.
     */
    protected function getInvoiceFields(): array
    {
        $fields = [
            'concept' => [
                'type' => 'radio',
                'label' => $this->t('field_concept'),
                'description' => $this->t('desc_concept'),
                'options' => [
                    Config::Concept_Plugin => $this->t('option_concept_2'),
                    Api::Concept_No => $this->t('option_concept_0'),
                    Api::Concept_Yes => $this->t('option_concept_1'),
                ],
                'attributes' => [
                    'required' => true,
                ],
            ],
            'euCommerceThresholdPercentage' => [
                'type' => 'text',
                'label' => $this->t('field_eu_commerce_threshold_percentage'),
                'description' => sprintf($this->t('desc_eu_commerce_threshold_percentage'), $this->t('module')),
                'attributes' => [
                    'size' => 10,
                ],
            ],
            'missingAmount' => [
                'type' => 'radio',
                'label' => $this->t('field_missing_amount'),
                'description' => $this->t('desc_missing_amount'),
                'options' => [
                    Config::MissingAmount_Warn => $this->t('option_missing_amount_2'),
                    Config::MissingAmount_AddLine => $this->t('option_missing_amount_3'),
                    Config::MissingAmount_Ignore => $this->t('option_missing_amount_1'),
                ],
            ],
            'sendWhat' => [
                'type' => 'checkbox',
                'label' => $this->t('field_sendWhat'),
                'description' => $this->t('desc_sendWhat'),
                'options' => [
                    'sendEmptyInvoice' => $this->t('option_sendEmptyInvoice'),
                    'sendEmptyShipping' => $this->t('option_sendEmptyShipping'),
                ],
            ],
            'description' => [
                'type' => 'text',
                'label' => $this->t('field_description'),
                'description' => $this->t('desc_description') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'descriptionText' => [
                'type' => 'textarea',
                'label' => $this->t('field_descriptionText'),
                'description' => $this->t('desc_descriptionText') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                    'rows' => 6,
                    'style' => 'box-sizing: border-box; width: 83%; min-width: 24em;',
                ],
            ],
            'invoiceNotes' => [
                'type' => 'textarea',
                'label' => $this->t('field_invoiceNotes'),
                'description' => $this->t('desc_invoiceNotes') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                    'rows' => 6,
                    'style' => 'box-sizing: border-box; width: 83%; min-width: 24em;',
                ],
            ],
        ];
        if ($this->shopCapabilities->usesNewCode()) {
            $fieldsToHide = [
                'description',
                'descriptionText',
                'invoiceNotes',
            ];
            foreach ($fieldsToHide as $field) {
                $fields[$field]['type'] = 'hidden';
            }
        }
        return $fields;
    }

    /**
     * Returns the set of invoice line related fields.
     *
     * The fields returned:
     * - 'itemNumber'
     * - 'productName'
     * - 'nature'
     * - 'costPrice'
     *
     * @return array[]
     *   The set of invoice line related fields.
     */
    protected function getInvoiceLinesFields(): array
    {
        $fields = [
            'itemNumber' => [
                'type' => 'text',
                'label' => $this->t('field_itemNumber'),
                'description' => $this->t('desc_itemNumber') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'productName' => [
                'type' => 'text',
                'label' => $this->t('field_productName'),
                'description' => $this->t('desc_productName') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'nature' => [
                'type' => 'text',
                'label' => $this->t('field_nature'),
                'description' => $this->t('desc_nature'),
                'attributes' => [
                    'size' => 30,
                ],
            ],
            'costPrice' => [
                'type' => 'text',
                'label' => $this->t('field_costPrice'),
                'description' => $this->t('desc_costPrice') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
        ];
        if ($this->shopCapabilities->usesNewCode()) {
            $fieldsToHide = [
                /* @todo  List the fields that are mapped when item lines gets collected.
                'itemName',
                'productName',
                'quantity',
                'nature',
                'costPrice',
                 */
            ];
            foreach ($fieldsToHide as $field) {
                $fields[$field]['type'] = 'hidden';
            }
        }
        return $fields;
    }

    /**
     * Returns the set of options related fields.
     *
     * The fields returned:
     * - 'optionsAllOn1Line'
     * - 'optionsAllOnOwnLine'
     * - 'optionsMaxLength'
     *
     * @return array[]
     *   The set of options related fields.
     */
    protected function getOptionsFields(): array
    {
        return [
            'showOptions' => [
                'type' => 'checkbox',
                'label' => $this->t('field_showOptions'),
                'description' => $this->t('desc_showOptions'),
                'options' => [
                    'optionsShow' => $this->t('option_optionsShow'),
                ],
            ],
            'optionsAllOn1Line' => [
                'type' => 'select',
                'label' => $this->t('field_optionsAllOn1Line'),
                'options' => [
                    0 => $this->t('option_do_not_use'),
                    PHP_INT_MAX => $this->t('option_always'),
                             ] + array_combine(range(1, 10), range(1, 10)),
            ],
            'optionsAllOnOwnLine' => [
                'type' => 'select',
                'label' => $this->t('field_optionsAllOnOwnLine'),
                'options' => [
                    PHP_INT_MAX => $this->t('option_do_not_use'),
                    1 => $this->t('option_always'),
                             ] + array_combine(range(2, 10), range(2, 10)),
            ],
            'optionsMaxLength' => [
                'type' => 'number',
                'label' => $this->t('field_optionsMaxLength'),
                'description' => $this->t('desc_optionsMaxLength'),
                'attributes' => [
                    'min' => 1,
                ],
            ],
        ];
    }

    /**
     * Returns the set of fields introducing the advanced config forms.
     *
     * The fields returned:
     * - 'tellAboutAdvancedSettings'
     * - 'advancedSettingsLink'
     *
     * @return array[]
     *   The set of fields introducing the advanced config form.
     */
    protected function getConfigLinkFields(): array
    {
        return [
            'tellAboutBasicSettings' => [
                'type' => 'markup',
                'value' => sprintf($this->t('desc_basicSettings'), $this->t('config_form_link_text'), $this->t('menu_basicSettings')),
            ],
            'basicSettingsLink' => [
                'type' => 'markup',
                'value' => sprintf($this->t('button_link'), $this->t('config_form_link_text') , $this->shopCapabilities->getLink('config')),
            ],
        ];
    }

    /**
     * Returns the list of possible values for the contact status field.
     *
     * @return array
     *   The list of possible values for the contact status field keyed by the
     *   value to use in the API and translated labels as the values.
     *
     */
    protected function getContactStatusOptions(): array
    {
        return [
            Api::ContactStatus_Active => $this->t('option_contactStatus_Active'),
            Api::ContactStatus_Disabled => $this->t('option_contactStatus_Disabled'),
        ];
    }
}
