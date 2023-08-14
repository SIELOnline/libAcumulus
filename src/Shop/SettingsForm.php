<?php
/**
 * @noinspection DuplicatedCode  This started as a duplicate of ConfigForm.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;

use function count;
use function in_array;

/**
 * Provides the settings form handling.
 *
 * Shop specific may optionally (have to) override:
 * - setSubmittedValues()
 *
 * @noinspection PhpUnused
 */
class SettingsForm extends BaseConfigForm
{
    /**
     * @var array
     *   Array of key => value pairs that can be used in a select and that
     *   represents all cost centers defined in Acumulus for the given account.
     */
    protected array $costCenterOptions;

    /**
     * @var array
     *   Array of key => value pairs that can be used in a select and that
     *   represents all bank accounts defined in Acumulus for the given account.
     */
    protected array $accountNumberOptions;

    protected function validate(): void
    {
        $this->validateAccountFields();
        $this->validateShopFields();
    }

    /**
     * Validates fields in the account settings fieldset.
     *
     * @noinspection InvertedIfElseConstructsInspection
     */
    protected function validateAccountFields(): void
    {

        if (empty($this->submittedValues[Tag::ContractCode])) {
            $this->addFormMessage($this->t('message_validate_contractcode_0'), Severity::Error, Tag::ContractCode);
        } elseif (!ctype_digit($this->submittedValues[Tag::ContractCode])) {
            $this->addFormMessage($this->t('message_validate_contractcode_1'), Severity::Error, Tag::ContractCode);
        } else {
            // Prevent errors where a copy & paste of the contract code from the
            // welcome mail includes spaces or tabs before or after the code.
            $this->submittedValues[Tag::ContractCode] = trim($this->submittedValues[Tag::ContractCode]);
        }

        if (empty($this->submittedValues[Tag::UserName])) {
            $this->addFormMessage($this->t('message_validate_username_0'), Severity::Error, Tag::UserName);
        } elseif ($this->submittedValues[Tag::UserName] !== trim($this->submittedValues[Tag::UserName])) {
            $this->addFormMessage($this->t('message_validate_username_1'), Severity::Warning, Tag::UserName);
        }

        if (empty($this->submittedValues[Tag::Password])) {
            $this->addFormMessage($this->t('message_validate_password_0'), Severity::Error, Tag::Password);
        } elseif ($this->submittedValues[Tag::Password] !== trim($this->submittedValues[Tag::Password])) {
            $this->addFormMessage($this->t('message_validate_password_1'), Severity::Warning, Tag::Password);
        } elseif (strpbrk($this->submittedValues[Tag::Password], '`\'"#%&;<>\\') !== false) {
            $this->addFormMessage($this->t('message_validate_password_2'), Severity::Warning, Tag::Password);
        }

        if (empty($this->submittedValues[Tag::EmailOnError])) {
            $this->addFormMessage($this->t('message_validate_email_1'), Severity::Error, Tag::EmailOnError);
        } elseif (!$this->isEmailAddress($this->submittedValues[Tag::EmailOnError])) {
            $this->addFormMessage($this->t('message_validate_email_0'), Severity::Error, Tag::EmailOnError);
        }
    }

    /**
     * Validates fields in the shop settings fieldset.
     */
    protected function validateShopFields(): void
    {
        // Check if this fieldset was rendered.
        if (!$this->isKey('nature_shop')) {
            return;
        }

        // Check that required fields are filled.
        if (!isset($this->submittedValues['nature_shop'])) {
            $message = sprintf($this->t('message_validate_required_field'), $this->t('field_nature_shop'));
            $this->addFormMessage($message, Severity::Error, 'nature_shop');
        }
        if (!isset($this->submittedValues['marginProducts'])) {
            $message = sprintf($this->t('message_validate_required_field'), $this->t('field_marginProducts'));
            $this->addFormMessage($message, Severity::Error, 'marginProducts');
        }
        if (empty($this->submittedValues['euVat'])) {
            $field = $this->t('field_euVat');
            $message = sprintf($this->t('message_validate_eu_vat_0'), $field);
            $this->addFormMessage($message, Severity::Error, 'euVat');
        }

        if (empty($this->submittedValues['vatFreeClass'])) {
            $field = sprintf($this->t('field_vatFreeClass'), $this->t('vat_class'));
            $message = sprintf($this->t('message_validate_required_field'), $field);
            $this->addFormMessage($message, Severity::Error, 'vatFreeClass');
        }
        if (empty($this->submittedValues['zeroVatClass'])) {
            $field = sprintf($this->t('field_zeroVatClass'), $this->t('vat_class'));
            $message = sprintf($this->t('message_validate_required_field'), $field);
            $this->addFormMessage($message, Severity::Error, 'zeroVatClass');
        }

        // Check that vatFreeClass and zeroVatClass do not point to the same
        // (real) vat class.
        /** @noinspection TypeUnsafeComparisonInspection */
        if (!empty($this->submittedValues['vatFreeClass'])
            && !empty($this->submittedValues['zeroVatClass'])
            && $this->submittedValues['zeroVatClass'] != Config::VatClass_NotApplicable
            && $this->submittedValues['vatFreeClass'] == $this->submittedValues['zeroVatClass']
        ) {
            $this->addFormMessage(
                sprintf($this->t('message_validate_zero_vat_class_0'), $this->t('vat_classes')),
                Severity::Error, 'zeroVatClass');
        }

        // Check the marginProducts setting in combination with other settings.
        // NOTE: it is debatable whether margin articles can be services, e.g.
        // selling 2nd hand software licences. So the validations may be removed
        // in the future.
        if (isset($this->submittedValues['nature_shop'], $this->submittedValues['marginProducts'])) {
            // If we only sell articles with nature Services, we cannot (also)
            // sell margin goods.
            /** @noinspection TypeUnsafeComparisonInspection */
            if ($this->submittedValues['nature_shop'] == Config::Nature_Services
                && $this->submittedValues['marginProducts'] != Config::MarginProducts_No
            ) {
                $this->addFormMessage($this->t('message_validate_conflicting_shop_options_1'), Severity::Error, 'nature_shop');
            }
            // If we only sell margin goods, the nature of all we sell is Products.
            /** @noinspection TypeUnsafeComparisonInspection */
            if ($this->submittedValues['marginProducts'] == Config::MarginProducts_Only
                && $this->submittedValues['nature_shop'] != Config::Nature_Products
            ) {
                $this->addFormMessage($this->t('message_validate_conflicting_shop_options_2'), Severity::Error, 'nature_shop');
            }
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

        //  Acumulus account settings.
        $fields['accountSettings'] = [
            'type' => 'fieldset',
            'legend' => $this->t('accountSettingsHeader'),
            'fields' => $this->getAccountFields($accountStatus, $message),
        ];

        if ($accountStatus === false) {
            $fields['accountSettingsMessage'] = [
                'type' => 'fieldset',
                'legend' => $this->t('message_error_header'),
                'fields' => [
                    'invoiceMessage' => [
                        'type' => 'markup',
                        'value' => $this->translateAccountMessage($message),
                    ],
                ],
            ];
        }

        if ($accountStatus) {
            $fields += [
                'shopSettings' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('shopSettingsHeader'),
                    'description' => $this->t('desc_shopSettings'),
                    'fields' => $this->getShopFields(),
                ],
                'triggerSettings' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('triggerSettingsHeader'),
                    'description' => sprintf($this->t('desc_triggerSettings'), $this->shopCapabilities->getLink('batch')),
                    'fields' => $this->getTriggerFields(),
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
            ];

            $paymentMethods = $this->shopCapabilities->getPaymentMethods();
            if (count($paymentMethods) !== 0) {
                $fields += [
                    'paymentMethodAccountNumberFieldset' => $this->getPaymentMethodsFieldset(
                        $paymentMethods,
                        'paymentMethodAccountNumber',
                        $this->accountNumberOptions
                    ),
                    'paymentMethodCostCenterFieldset' => $this->getPaymentMethodsFieldset(
                        $paymentMethods,
                        'paymentMethodCostCenter',
                        $this->costCenterOptions
                    ),
                ];
            }

            $fields += [
                'optionsSettingsHeader' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('optionsSettingsHeader'),
                    'description' => $this->t('desc_optionsSettingsHeader'),
                    'fields' => $this->getOptionsFields(),
                ],
                'invoiceStatusScreenSettingsHeader' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceStatusScreenSettingsHeader'),
                    'description' => $this->t('desc_invoiceStatusScreenSettings') . ' ' . $this->t('desc_invoiceStatusScreenSettings2'),
                    'fields' => $this->getInvoiceStatusScreenFields(),
                ],
                'invoiceDocumentsSettingsHeader' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('documentsSettingsHeader'),
                    'description' => $this->t('desc_documentsSettings'),
                    'fields' => $this->getDocumentsFields(),
                ],
            ];
        }

        $fields['pluginSettings'] = [
            'type' => 'fieldset',
            'legend' => $this->t('pluginSettingsHeader'),
            'fields' => $this->getPluginFields(),
        ];
        if ($accountStatus) {
            $fields['advancedConfig'] = [
                'type' => 'details',
                'summary' => $this->t('mappings_form_header'),
                'fields' => $this->getMappingsLinkFields(),
            ];
        }
        $fields['versionInformation'] = $this->getAboutBlock($accountStatus);

        return $fields;
    }

    /**
     * Returns the set of account related fields.
     * The fields returned:
     * - optional: 'Register' button + explanation.
     * - 'description': replaces the legend, as it should come below the
     *   optional 'Register' button.
     * - 'contractcode'
     * - 'username'
     * - 'password'
     * - 'emailonerror'
     *
     * @param bool|null $accountStatus
     *   null: no account settings filled in yet.
     *   true: account settings OK.
     *   false: authentication error using the given account settings.
     * @param string $message
     *   The message code, only filled when $accountStatus = false.
     *
     * @return array[]
     *   The set of account related fields.
     */
    protected function getAccountFields(?bool $accountStatus, string $message): array
    {
        $desc2 = '';
        if ($accountStatus === null) {
            $description = 'desc_accountSettings_N';
        } elseif ($accountStatus === true) {
            $description = 'desc_accountSettings_T';
        } else {
            $description = 'desc_accountSettings_F';
            if ($message === 'message_error_auth') {
                $desc2 = 'desc_accountSettings_auth';
            }
        }
        // 'desc_accountSettings_T' uses plugin/module/extension in its message.
        $description = sprintf($this->t($description), $this->t('module'));
        if (!empty($desc2)) {
            $description .= ' ' . sprintf($this->t($desc2), $this->shopCapabilities->getLink('register'));
        }

        $fields = [];
        if ($accountStatus === null) {
            $fields += $this->getRegisterFields();
        }
        $fields += [
            'descAccountSettings' => [
                'type' => 'markup',
                'value' => $description,
            ],
            Tag::ContractCode => [
                'type' => 'text',
                'label' => $this->t('field_code'),
                'attributes' => [
                    'required' => true,
                    'size' => 10,
                ],
            ],
            Tag::UserName => [
                'type' => 'text',
                'label' => $this->t('field_username'),
                'description' => $this->t('desc_username'),
                'attributes' => [
                    'required' => true,
                    'size' => 20,
                ],
            ],
            Tag::Password => [
                'type' => 'password',
                'label' => $this->t('field_password'),
                'attributes' => [
                    'required' => true,
                    'size' => 20,
                ],
            ],
            Tag::EmailOnError => [
                'type' => 'email',
                'label' => $this->t('field_emailonerror'),
                'description' => $this->t('desc_emailonerror'),
                'attributes' => [
                    'required' => true,
                    'size' => 30,
                ],
            ],
        ];
        return $fields;
    }

    /**
     * Returns the set of shop related fields.
     *
     * The fields returned:
     * - nature_shop
     * - 'marginProducts'
     * - 'euVat'
     * - 'vatFreeClass'
     * - 'zeroVatClass'
     *
     * @return array[]
     *   The set of shop related fields.
     */
    protected function getShopFields(): array
    {
        $vatClasses = $this->shopCapabilities->getVatClasses();

        return [
            'nature_shop' => [
                'type' => 'radio',
                'label' => $this->t('field_nature_shop'),
                'description' => $this->t('desc_nature_shop'),
                'options' => $this->getNatureOptions(),
                'attributes' => [
                    'required' => true,
                ],
            ],
            'marginProducts' => [
                'type' => 'radio',
                'label' => $this->t('field_marginProducts'),
                'description' => $this->t('desc_marginProducts'),
                'options' => $this->getMarginProductsOptions(),
                'attributes' => [
                    'required' => true,
                ],
            ],
            'euVat' => [
                'type' => 'radio',
                'label' => $this->t('field_euVat'),
                'description' => $this->t('desc_euVat'),
                'options' => $this->getEuVatOptions(),
                'attributes' => [
                    'required' => true,
                ],
            ],
            'vatFreeClass' => [
                'type' => 'select',
                'label' => sprintf($this->t('field_vatFreeClass'), $this->t('vat_class')),
                'description' => sprintf($this->t('desc_vatFreeClass'),
                    $this->t('vat_class'),
                    $this->t('vat_class_not_applicable'),
                    sprintf($this->t('vat_class_left_empty'), $this->t('vat_class'))
                ),
                'options' => [
                                 0 => $this->t('option_empty'),
                                 Config::VatClass_NotApplicable => ucfirst($this->t('vat_class_not_applicable')),
                                 Config::VatClass_Null => ucfirst(sprintf($this->t('vat_class_left_empty'), $this->t('vat_class'))),
                             ] + $vatClasses,
                'attributes' => [
                    'required' => true,
                    'multiple' => false,
                ],
            ],
            'zeroVatClass' => [
                'type' => 'select',
                'label' => sprintf($this->t('field_zeroVatClass'), $this->t('vat_class')),
                'description' => sprintf($this->t('desc_zeroVatClass'), $this->t('vat_class'), $this->t('vat_class_not_applicable')),
                'options' => [
                                 0 => $this->t('option_empty'),
                                 Config::VatClass_NotApplicable => ucfirst($this->t('vat_class_not_applicable')),
                             ] + $vatClasses,
                'attributes' => [
                    'required' => true,
                    'multiple' => false,
                ],
            ],
        ];
    }

    /**
     * Returns the set of trigger related fields.
     *
     * The fields returned:
     * - 'triggerOrderStatus'
     * - 'triggerInvoiceEvent'
     *
     * @return array[]
     *   The set of trigger related fields.
     */
    protected function getTriggerFields(): array
    {
        $orderStatusesList = $this->getOrderStatusesList();

        return [
            'triggerOrderStatus' => [
                'name' => 'triggerOrderStatus[]',
                'type' => 'select',
                'label' => $this->t('field_triggerOrderStatus'),
                'description' => $this->t('desc_triggerOrderStatus'),
                'options' => $orderStatusesList,
                'attributes' => [
                    'multiple' => true,
                    'size' => min(count($orderStatusesList), 8),
                ],
            ],
            'triggerInvoiceEvent' => $this->getOptionsOrHiddenField('triggerInvoiceEvent', 'radio', false),
            'triggerCreditNoteEvent' => $this->getOptionsOrHiddenField('triggerCreditNoteEvent', 'radio', false),
        ];
    }

    /**
     * Returns the set of invoice related fields.
     * The fields returned:
     * - 'invoiceNrSource'
     * - 'dateToUse'
     * - 'defaultCustomerType'
     * - 'salutation'
     * - 'defaultAccountNumber'
     * - 'defaultCostCenter'
     * - 'defaultInvoiceTemplate'
     * - 'defaultInvoicePaidTemplate'
     * - 'concept'
     * - 'euCommerceThreshold'
     * - 'sendEmptyInvoice'
     * - 'sendEmptyShipping'
     *
     * @return array[]
     *   The set of invoice related fields.
     *
     * @noinspection PhpMemberCanBePulledUpInspection : AdvancedConfigForm has a
     *   method with the same name but a different result.
     */
    protected function getInvoiceFields(): array
    {
        $this->accountNumberOptions = $this->picklistToOptions(
            $this->acumulusApiClient->getPicklistAccounts(),
            0,
            $this->t('option_empty')
        );
        $this->costCenterOptions = $this->picklistToOptions(
            $this->acumulusApiClient->getPicklistCostCenters(),
            0,
            $this->t('option_empty')
        );
        $fiscalAddressSetting = $this->shopCapabilities->getFiscalAddressSetting();
        if (in_array($fiscalAddressSetting, [AddressType::Invoice, AddressType::Shipping], true)) {
            // Shop uses a fixed address.
            $descMainAdressAdditional = sprintf($this->t('desc_mainAddress_shopUses'), $this->t($fiscalAddressSetting));
        } else {
            // Shop uses a setting to define which address to use.
            $descMainAdressAdditional = sprintf($this->t('desc_mainAddress_shopSetting'),
                $this->t('fiscal_address_setting'),
                $this->shopCapabilities->getLink('fiscal-address-setting')
            );
        }

        return [
            'mainAddress' => [
                'type' => 'radio',
                'label' => $this->t('field_mainAdress'),
                'description' => $this->t('desc_mainAddress') . ' ' . $descMainAdressAdditional,
                'options' => $this->getMainAddressOptions(),
            ],
            'invoiceNrSource' => $this->getOptionsOrHiddenField('invoiceNrSource', 'radio'),
            'dateToUse' => $this->getOptionsOrHiddenField('dateToUse', 'radio'),
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
            'defaultInvoiceTemplate' => [
                'type' => 'select',
                'label' => $this->t('field_defaultInvoiceTemplate'),
                'options' => $this->picklistToOptions(
                    $invoiceTemplates = $this->acumulusApiClient->getPicklistInvoiceTemplates(),
                    0,
                    $this->t('option_empty')
                ),
            ],
            'defaultInvoicePaidTemplate' => [
                'type' => 'select',
                'label' => $this->t('field_defaultInvoicePaidTemplate'),
                'description' => $this->t('desc_defaultInvoiceTemplate'),
                'options' => $this->picklistToOptions($invoiceTemplates, 0, $this->t('option_same_template')),
            ],
            'defaultAccountNumber' => [
                'type' => 'select',
                'label' => $this->t('field_defaultAccountNumber'),
                'description' => $this->t('desc_defaultAccountNumber'),
                'options' => $this->accountNumberOptions,
            ],
            'defaultCostCenter' => [
                'type' => 'select',
                'label' => $this->t('field_defaultCostCenter'),
                'description' => $this->t('desc_defaultCostCenter'),
                'options' => $this->costCenterOptions,
            ],
        ];
    }

    /**
     * Returns the set of relation management fields.
     *
     * The fields returned:
     * - 'clientData': 'sendCustomer' and 'overwriteIfExists'
     * - 'defaultCustomerType'
     * - 'contactStatus'
     *
     * @return array[]
     *   The set of relation management fields.
     */
    protected function getRelationFields(): array
    {
        return [
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
        ];
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
     *
     * @todo: deze velden samenvoegen met "verzend gratis vezending regels" in een
     *   fieldset Factuurregels?
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
     * Returns the fields related to the invoice status screen.
     *
     * @return array[]
     */
    protected function getInvoiceStatusScreenFields(): array
    {
        $fields = [];
        if ($this->shopCapabilities->hasInvoiceStatusScreen()) {
            $fields['invoiceStatusScreen'] = [
                'type' => 'checkbox',
                'label' => $this->t('field_invoiceStatusScreen'),
                'description' => $this->t('desc_invoiceStatusScreen'),
                'options' => [
                    'showInvoiceStatus' => $this->t('option_showInvoiceStatus'),
                ],
            ];
        }
        return $fields;
    }

    /**
     * Returns the fields related to the documents, invoice and packing slip, options.
     *
     * @return array[]
     */
    protected function getDocumentsFields(): array
    {
        return $this->getInvoiceDocumentFields() + $this->getPackingSlipDocumentFields();
    }

    /**
     * Returns the fields related to the invoice document.
     *
     * @return array[]
     */
    protected function getInvoiceDocumentFields(): array
    {
        $fields = [];
        $fields['invoiceSubHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . ucfirst($this->t('document_invoice')) . '</h3>',
        ];
        $fields['emailAsPdf_cb'] = [
            'type' => 'checkbox',
            'label' => $this->t('field_emailAsPdf'),
            'description' => $this->t('desc_emailAsPdf'),
            'options' => [
                'emailAsPdf' => $this->t('option_emailAsPdf'),
            ],
        ];
        $fields['detailInvoice'] = [
            'type' => 'checkbox',
            'label' => $this->t('field_detailPage'),
            'description' => $this->t('desc_detailPage'),
            'options' => $this->getDocumentsOptions('Detail', 'invoice'),
        ];
        if ($this->shopCapabilities->hasOrderList()) {
            $fields['listInvoice'] = [
                'type' => 'checkbox',
                'label' => $this->t('field_listPage'),
                'description' => $this->t('desc_listPage'),
                'options' => $this->getDocumentsOptions('List', 'invoice'),
            ];
        }
        return $fields;
    }

    /**
     * Returns the fields related to the packing slip document.
     *
     * @return array[]
     */
    protected function getPackingSlipDocumentFields(): array
    {
        $fields = [];
        $fields['packingSlipSubHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . ucfirst($this->t('document_packing_slip')) . '</h3>',
        ];
        $fields['detailPackingSlip'] = [
            'type' => 'checkbox',
            'label' => $this->t('field_detailPage'),
            'description' => $this->t('desc_detailPage'),
            'options' => $this->getDocumentsOptions('Detail', 'packing_slip'),
        ];
        if ($this->shopCapabilities->hasOrderList()) {
            $fields['listPackingSlip'] = [
                'type' => 'checkbox',
                'label' => $this->t('field_listPage'),
                'description' => $this->t('desc_listPage'),
                'options' => $this->getDocumentsOptions('List', 'packing_slip'),
            ];
        }
        return $fields;
    }

    /**
     * Returns the set of plugin related fields.
     *
     * The fields returned:
     * - 'debug'
     * - 'logLevel'
     *
     * @return array[]
     */
    protected function getPluginFields(): array
    {
        return [
            'debug' => [
                'type' => 'radio',
                'label' => $this->t('field_debug'),
                'description' => $this->t('desc_debug'),
                'options' => [
                    Config::Send_SendAndMailOnError => $this->t('option_debug_1'),
                    Config::Send_SendAndMail => $this->t('option_debug_2'),
                    Config::Send_TestMode => $this->t('option_debug_3'),
                ],
                'attributes' => [
                    'required' => true,
                ],
            ],
            'logLevel' => [
                'type' => 'radio',
                'label' => $this->t('field_logLevel'),
                'description' => $this->t('desc_logLevel'),
                'options' => [
                    Severity::Notice => $this->t('option_logLevel_3'),
                    Severity::Info => $this->t('option_logLevel_4'),
                    Severity::Log => $this->t('option_logLevel_5'),
                ],
                'attributes' => [
                    'required' => true,
                ],
            ],
        ];
    }

    /**
     * Returns the set of fields introducing the mappings form.
     *
     * The fields returned:
     * - 'tellAboutMappings'
     * - 'mappingsLink'
     *
     * @return array[]
     *   The set of fields introducing the mappings form.
     */
    protected function getMappingsLinkFields(): array
    {
        return [
            'tellAboutMappings' => [
                'type' => 'markup',
                'value' => sprintf(
                    $this->t('desc_mappings'),
                    $this->t('mappings_form_link_text'),
                    $this->t('menu_mappings')
                ),
            ],
            'mappingsLink' => [
                'type' => 'markup',
                'value' => sprintf(
                    $this->t('button_link'),
                    $this->t('mappings_form_link_text'),
                    $this->shopCapabilities->getLink('mappings')
                ),
            ],
        ];
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
    protected function getPaymentMethodsFieldset(array $paymentMethods, string $key, array $options): array
    {
        $fieldset = [
            'type' => 'fieldset',
            'legend' => $this->t("{$key}Fieldset"),
            'description' => $this->t("desc_{$key}Fieldset"),
            'fields' => [],
        ];

        $options[0] = $this->t('option_use_default');
        foreach ($paymentMethods as $paymentMethodId => $paymentMethodLabel) {
            /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
            $fieldset['fields']["{$key}[{$paymentMethodId}]"] = [
                'type' => 'select',
                'label' => $paymentMethodLabel,
                'options' => $options,
            ];
        }
        return $fieldset;
    }

    /**
     * Returns a list of options for the nature field.
     *
     * @return string[]
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    protected function getNatureOptions(): array
    {
        return [
            Config::Nature_Both => $this->t('option_nature_1'),
            Config::Nature_Products => $this->t('option_nature_2'),
            Config::Nature_Services => $this->t('option_nature_3'),
        ];
    }

    /**
     * Returns a list of options for the margin products field.
     *
     * @return string[]
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    protected function getMarginProductsOptions(): array
    {
        return [
            Config::MarginProducts_Both => $this->t('option_marginProducts_1'),
            Config::MarginProducts_No => $this->t('option_marginProducts_2'),
            Config::MarginProducts_Only => $this->t('option_marginProducts_3'),
        ];
    }

    /**
     * Returns a list of options for the EU vat field.
     *
     * @return string[]
     *   An array keyed by the option values and having translated descriptions
     *   as values.
     */
    protected function getEuVatOptions(): array
    {
        return [
            Config::EuVat_Yes => $this->t('option_euVat_1'),
            Config::EuVat_SwitchOnLimit => $this->t('option_euVat_2'),
            Config::EuVat_No => $this->t('option_euVat_3'),
        ];
    }

    protected function getMainAddressOptions(): array
    {
        return [
            Config::MainAddress_FollowShop => $this->t('option_mainAddress_shop'),
            Config::MainAddress_Invoice => ucfirst($this->t(AddressType::Invoice)),
            Config::MainAddress_Shipping => ucfirst($this->t(AddressType::Shipping)),
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

    /**
     * @param string $page
     *   'Detail' or 'List'.
     * @param string $document
     *   'invoice' or 'packing_slip'.
     *
     * @return array
     *   Array with 2 options (value => label entries).
     */
    protected function getDocumentsOptions(string $page, string $document): array
    {
        $label = $this->t("document_$document");
        $show = $this->t('option_document_show');
        $mail = $this->t('option_document_mail');

        // Change to "camel case".
        $document =  str_replace('_', '', ucwords($document, '_'));
        return [
            "show$document$page" => sprintf($this->t('option_document'), $label, $show),
            "mail$document$page" => sprintf($this->t('option_document'), $label, $mail),
        ];
    }
}
