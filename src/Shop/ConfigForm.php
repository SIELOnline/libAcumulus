<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Tag;

/**
 * Provides basic config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - setSubmittedValues()
 */
class ConfigForm extends BaseConfigForm
{
    /**
     * @var array
     *   Array of key => value pairs that can be used in a select and that
     *   represents all cost centers defined in Acumulus for the given account.
     */
    protected $costCenterOptions;

    /**
     * @var array
     *   Array of key => value pairs that can be used in a select and that
     *   represents all bank accounts defined in Acumulus for the given account.
     */
    protected $accountNumberOptions;

    /**
     * {@inheritdoc}
     */
    protected function validate()
    {
        $this->validateAccountFields();
        $this->validateShopFields();
        $this->validateEmailAsPdfFields();
        $this->validateDocumentsFields();
    }

    /**
     * Validates fields in the account settings fieldset.
     */
    protected function validateAccountFields()
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
    protected function validateShopFields()
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
        if (empty($this->submittedValues['euVatClasses'])) {
            $field = sprintf($this->t('field_euVatClasses'), $this->t('vat_classes'));
            $message = sprintf($this->t('message_validate_eu_vat_classes_0'), $field);
            $this->addFormMessage($message, Severity::Error, 'euVatClasses');
        } else {
            // Check that Not applicable is not selected with other classes for EU vat classes
            if (count($this->submittedValues['euVatClasses']) >= 2 && in_array(Config::VatClass_NotApplicable, $this->submittedValues['euVatClasses'])) {
                $field = sprintf($this->t('field_euVatClasses'), $this->t('vat_classes'));
                $message = sprintf($this->t('message_validate_eu_vat_classes_1'), $field, $this->t('vat_class_not_applicable'));
                $this->addFormMessage($message, Severity::Error, 'euVatClasses');
            }
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

        // Check that vatFreeClass and zeroVatClass do not point to the same (real) vat class.
        if (!empty($this->submittedValues['vatFreeClass']) && !empty($this->submittedValues['zeroVatClass'])) {
            if (
                $this->submittedValues['zeroVatClass'] != Config::VatClass_NotApplicable
                && $this->submittedValues['vatFreeClass'] == $this->submittedValues['zeroVatClass']
            ) {
                $this->addFormMessage(sprintf($this->t('message_validate_zero_vat_class_0'), $this->t('vat_classes')),
                    Severity::Error, 'zeroVatClass');
            }
        }

        // Check the marginProducts setting in combination with other settings.
        // NOTE: it is debatable whether margin articles can be services, e.g.
        // selling 2nd hand software licenses. So the validations may be removed
        // in the future.
        if (isset($this->submittedValues['nature_shop']) && isset($this->submittedValues['marginProducts'])) {
            // If we only sell articles with nature Services, we cannot (also)
            // sell margin goods.
            if ($this->submittedValues['nature_shop'] == Config::Nature_Services && $this->submittedValues['marginProducts'] != Config::MarginProducts_No) {
                $this->addFormMessage($this->t('message_validate_conflicting_shop_options_1'), Severity::Error, 'nature_shop');
            }
            // If we only sell margin goods, the nature of all we sell is Products.
            if ($this->submittedValues['marginProducts'] == Config::MarginProducts_Only && $this->submittedValues['nature_shop'] != Config::Nature_Products) {
                $this->addFormMessage($this->t('message_validate_conflicting_shop_options_2'), Severity::Error, 'nature_shop');
            }
        }
    }

    /**
     * Validates fields in the "Email as pdf" settings fieldset.
     */
    protected function validateEmailAsPdfFields()
    {
        // Check if this fieldset was rendered.
        if (!$this->isKey('emailTo')) {
            return;
        }

        // Check for valid email address if no token syntax is used.
        if (!empty($this->submittedValues['emailTo'])
            && strpos($this->submittedValues['emailTo'], '[') === false
            && !$this->isEmailAddress($this->submittedValues['emailTo'], true)
        ) {
            $this->addFormMessage($this->t('message_validate_email_5'), Severity::Error, 'emailTo');
        }

        // Check for valid email addresses if no token syntax is used.
        if (!empty($this->submittedValues['emailBcc'])
            && strpos($this->submittedValues['emailBcc'], '[') === false
            && !$this->isEmailAddress($this->submittedValues['emailBcc'], true)
        ) {
            $this->addFormMessage($this->t('message_validate_email_3'), Severity::Error, 'emailBcc');
        }

        // Check for valid email address if no token syntax is used.
        if (!empty($this->submittedValues['emailFrom'])
            && strpos($this->submittedValues['emailFrom'], '[') === false
            && !$this->isEmailAddress($this->submittedValues['emailFrom'])
        ) {
            $this->addFormMessage($this->t('message_validate_email_4'), Severity::Error, 'emailFrom');
        }
    }

    /**
     * Validates fields in the "Acumulus documents" fieldset.
     */
    protected function validateDocumentsFields()
    {
        // Check if this fieldset was rendered.
        if (!$this->isKey('showInvoiceDetail')) {
            return;
        }

        // Check that a mail address has been filled in when the packing slip option has been set to a mail link.
        if (!empty($this->submittedValues['mailPackingSlipDetail']) || !empty($this->submittedValues['mailPackingSlipList'])) {
            if (empty($this->submittedValues['packingSlipEmailTo'])) {
                $this->addFormMessage($this->t('message_validate_packing_slip_email_0'), Severity::Error, 'packingSlipEmailTo');
            }
        }
        // Check that a valid mail address has been filled in.
        if (!empty($this->submittedValues['packingSlipEmailTo'])
            && strpos($this->submittedValues['packingSlipEmailTo'], '[') === false
            && !$this->isEmailAddress($this->submittedValues['packingSlipEmailTo'], true)
        ) {
                $this->addFormMessage($this->t('message_validate_packing_slip_email_1'), Severity::Error, 'packingSlipEmailTo');
        }

        // Check that valid bcc mail addresses have been filled in.
        if (!empty($this->submittedValues['packingSlipEmailBcc'])
            && strpos($this->submittedValues['packingSlipEmailBcc'], '[') === false
            && !$this->isEmailAddress($this->submittedValues['packingSlipEmailBcc'], true)
        ) {
                $this->addFormMessage($this->t('message_validate_packing_slip_email_2'), Severity::Error, 'packingSlipEmailBcc');
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the config form. At the minimum, this includes the
     * account settings. If these are OK, the other settings are included as
     * well.
     */
    public function getFieldDefinitions(): array
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
                'invoiceSettings' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('invoiceSettingsHeader'),
                    'fields' => $this->getInvoiceFields(),
                ],
            ];

            $paymentMethods = $this->shopCapabilities->getPaymentMethods();
            if (!empty($paymentMethods)) {
                $fields += [
                    'paymentMethodAccountNumberFieldset' => $this->getPaymentMethodsFieldset($paymentMethods, 'paymentMethodAccountNumber', $this->accountNumberOptions),
                    'paymentMethodCostCenterFieldset' => $this->getPaymentMethodsFieldset($paymentMethods, 'paymentMethodCostCenter', $this->costCenterOptions),
                ];
            }

            $fields += [
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

        $fields += [
            'pluginSettings' => [
                'type' => 'fieldset',
                'legend' => $this->t('pluginSettingsHeader'),
                'fields' => $this->getPluginFields(),
            ],
            'versionInformation' => $this->getAboutBlock($accountStatus),
        ];
        if ($accountStatus) {
            $fields += [
                'advancedConfig' => [
                    'type' => 'details',
                    'summary' => $this->t('advanced_form_header'),
                    'fields' => $this->getAdvancedConfigLinkFields(),
                ],
            ];
        }

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
     * - 'euVatClasses'
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
            'euVatClasses' => [
                'name' => 'euVatClasses[]',
                'type' => 'select',
                'label' => sprintf($this->t('field_euVatClasses'), $this->t('vat_classes')),
                'description' => sprintf($this->t('desc_euVatClasses'), $this->t('vat_classes'), $this->t('vat_class_not_applicable')),
                'options' => [
                                 Config::VatClass_NotApplicable => ucfirst($this->t('vat_class_not_applicable')),
                             ] + $vatClasses,
                'attributes' => [
                    'required' => true,
                    'multiple' => true,
                    'size' => min(count($vatClasses) + 1, 8),
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
     * - 'clientData'
     * - 'defaultAccountNumber'
     * - 'defaultCostCenter'
     * - 'defaultInvoiceTemplate'
     * - 'defaultInvoicePaidTemplate'
     *
     * @return array[]
     *   The set of invoice related fields.
     *
     * @noinspection PhpMemberCanBePulledUpInspection : AdvancedConfigForm has a
     *   method with the same name but a different result.
     */
    protected function getInvoiceFields(): array
    {
        $this->accountNumberOptions = $this->picklistToOptions($this->acumulusApiClient->getPicklistAccounts(), 0, $this->t('option_empty'));
        $this->costCenterOptions = $this->picklistToOptions($this->acumulusApiClient->getPicklistCostCenters(), 0, $this->t('option_empty'));

        return [
            'invoiceNrSource' => $this->getOptionsOrHiddenField('invoiceNrSource', 'radio'),
            'dateToUse' => $this->getOptionsOrHiddenField('dateToUse', 'radio'),
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
            'defaultInvoiceTemplate' => [
                'type' => 'select',
                'label' => $this->t('field_defaultInvoiceTemplate'),
                'options' => $this->picklistToOptions($invoiceTemplates = $this->acumulusApiClient->getPicklistInvoiceTemplates(), 0, $this->t('option_empty')),
            ],
            'defaultInvoicePaidTemplate' => [
                'type' => 'select',
                'label' => $this->t('field_defaultInvoicePaidTemplate'),
                'description' => $this->t('desc_defaultInvoiceTemplate'),
                'options' => $this->picklistToOptions($invoiceTemplates, 0, $this->t('option_same_template')),
            ],
        ];
    }

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

    protected function getDocumentsFields(): array
    {
        return $this->getInvoiceDocumentFields() + $this->getPackingSlipDocumentFields();
    }

    protected function getInvoiceDocumentFields(): array
    {
        $fields = [];
        $fields['invoiceSubHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . ucfirst($this->t("document_invoice")) . '</h3>',
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
        $fields += $this->getEmailAsPdfFields();
        return $fields;
    }

    protected function getPackingSlipDocumentFields(): array
    {
        $fields = [];
        $fields['packingSlipSubHeader'] = [
            'type' => 'markup',
            'value' => '<h3>' . ucfirst($this->t("document_packing_slip")) . '</h3>',
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
        $fields['packingSlipEmailTo'] = [
            'type' => 'text',
            'label' => $this->t('field_packingSlipEmailTo'),
            'description' => $this->t('desc_packingSlipEmailTo') . ' ' . $this->t('msg_token'),
            'attributes' => [
                'size' => 60,
            ],
        ];
        $fields['packingSlipEmailBcc'] = [
            'type' => 'text',
            'label' => $this->t('field_packingSlipEmailBcc'),
            'description' => $this->t('desc_packingSlipEmailBcc') . ' ' . $this->t('msg_token'),
            'attributes' => [
                'size' => 60,
            ],
        ];
        return $fields;
    }

    /**
     * Returns the set of "email invoice as PDF" related fields.
     *
     * The fields returned:
     * - 'emailAsPdf'
     * - 'emailFrom'
     * - 'emailBcc'
     * - 'subject'
     *
     * @return array[]
     *   The set of "email invoice as PDF" related fields.
     */
    protected function getEmailAsPdfFields(): array
    {
        return [
            'emailAsPdf_cb' => [
                'type' => 'checkbox',
                'label' => $this->t('field_emailAsPdf'),
                'description' => $this->t('desc_emailAsPdf'),
                'options' => [
                    'emailAsPdf' => $this->t('option_emailAsPdf'),
                ],
            ],
            'emailTo' => [
                'type' => 'text',
                'label' => $this->t('field_emailTo'),
                'description' => $this->t('desc_emailTo') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'emailBcc' => [
                'type' => 'text',
                'label' => $this->t('field_emailBcc'),
                'description' => $this->t('desc_emailBcc') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'multiple' => true,
                    'size' => 60,
                ],
            ],
            'emailFrom' => [
                'type' => 'text',
                'label' => $this->t('field_emailFrom'),
                'description' => $this->t('desc_emailFrom') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'subject' => [
                'type' => 'text',
                'label' => $this->t('field_subject'),
                'description' => $this->t('desc_subject') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
        ];
    }

    /**
     * Returns the set of plugin related fields.
     *
     * The fields returned:
     * - 'debug'
     * - 'logLevel'
     * - 'versionInformation'
     * - 'versionInformationDesc'
     *
     * @return array[]
     *   The set of plugin related fields.
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
     * Returns the set of fields introducing the advanced config forms.
     *
     * The fields returned:
     * - 'tellAboutAdvancedSettings'
     * - 'advancedSettingsLink'
     *
     * @return array[]
     *   The set of fields introducing the advanced config form.
     */
    protected function getAdvancedConfigLinkFields(): array
    {
        return [
            'tellAboutAdvancedSettings' => [
                'type' => 'markup',
                'value' => sprintf($this->t('desc_advancedSettings'), $this->t('advanced_form_link_text'), $this->t('menu_advancedSettings')),
            ],
            'advancedSettingsLink' => [
                'type' => 'markup',
                'value' => sprintf($this->t('button_link'), $this->t('advanced_form_link_text'), $this->shopCapabilities->getLink('advanced')),
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
