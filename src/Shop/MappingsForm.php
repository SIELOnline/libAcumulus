<?php
/**
 * @noinspection DuplicatedCode Yes, there is duplication from the former
 *   {@see \Siel\Acumulus\Shop\AdvancedConfigForm}.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;

use Siel\Acumulus\Helpers\Translator;

use function count;
use function is_array;

/**
 * Provides advanced config form handling.
 *
 * Shop specific may optionally (have to) override:
 * - setSubmittedValues()
 */
class MappingsForm extends Form
{
    protected Mappings $mappings;

    public function __construct(
        Mappings $mappings,
        AboutForm $aboutForm,
        Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct($acumulusApiClient, $formHelper, $shopCapabilities, $config, $environment, $translator, $log);
        $this->mappings = $mappings;
        $this->aboutForm = $aboutForm;
        $this->translator->add(new ConfigFormTranslations());
    }

    /**
     * {@inheritdoc}
     *
     * Saves the submitted and validated form values in the mappings store.
     */
    protected function execute(): bool
    {
        $submittedValues = $this->submittedValues;
        return $this->mappings->save($submittedValues);
    }

    protected function validate(): void
    {
        $this->validateEmailInvoiceFields();
    }

    /**
     * Validates fields in the "Email invoice pdf" mappings fieldset.
     */
    protected function validateEmailInvoiceFields(): void
    {
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
     * Validates fields in the "Email packing slip pdf" mappings fieldset.
     */
    protected function validateEmailPackingSlipFields(): void
    {
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

        // Check for valid email address if no token syntax is used.
        if (!empty($this->submittedValues['packingSlipEmailFrom'])
            && strpos($this->submittedValues['packingSlipEmailFrom'], '[') === false
            && !$this->isEmailAddress($this->submittedValues['packingSlipEmailFrom'])
        ) {
            $this->addFormMessage($this->t('message_validate_email_4'), Severity::Error, 'packingSlipEmailFrom');
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
        $fields = [
            'configHeader' => [
                'type' => 'details',
                'summary' => $this->t('config_form_header'),
                'fields' => $this->getConfigLinkFields(),
            ],
            'tokenHelpHeader' => [
                'type' => 'details',
                'summary' => $this->t('tokenHelpHeader'),
                'description' => $this->t('desc_tokens'),
                'fields' => $this->getTokenFields(),
            ],
            'relationMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('relationSettingsHeader'),
                'description' => $this->t('desc_relationSettingsHeader'),
                'fields' => $this->addNames($this->getRelationFields(), DataType::Customer),
            ],
            'invoiceAddressMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('invoiceAddressMappingsHeader'),
                'description' => $this->t('desc_invoiceAddressMappingsHeader'),
                'fields' => $this->addNames($this->getAddressFields(), AddressType::Invoice),
            ],
            'shippingAddressMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('shippingAddressMappingsHeader'),
                'description' => $this->t('desc_shippingAddressMappingsHeader'),
                'fields' => $this->addNames($this->getAddressFields(), AddressType::Shipping),
            ],
            'invoiceMappings' => [
                'type' => 'fieldset',
                'legend' => $this->t('invoiceMappingsHeader'),
                'fields' => $this->addNames($this->getInvoiceFields(), DataType::Invoice),
            ],
            'invoiceLinesMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('invoiceLinesMappingsHeader'),
                'fields' => $this->addNames($this->getInvoiceLinesFields(), LineType::Item),
            ],
            'emailInvoicePdfMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('emailInvoicePdfMappingsHeader'),
                'fields' => $this->addNames($this->getEmailInvoiceFields(), EmailAsPdfType::Invoice),
            ],
            'emailPackingSlipPdfMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('emailPackingSlipPdfMappingsHeader'),
                'fields' => $this->addNames($this->getEmailPackingSlipFields(), EmailAsPdfType::PackingSlip),
            ],
        ];

        // Last fieldset: More Acumulus.
        $message = $this->checkAccountSettings();
        $accountStatus = $this->emptyCredentials() ? null : empty($message);
        $fields['versionInformation'] = $this->getAboutBlock($accountStatus);

        return $fields;
    }

    /**
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
                    $value .= ' (' . $this->see2Lists(
                            'see_class_file',
                            'see_classes_files',
                            $variableInfo['class'],
                            $variableInfo['file']
                        ) . ')';
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
                    $value .= '<li>' . $this->seeList('see_class_more', 'see_classes_more', $variableInfo['class']) . '</li>';
                } elseif (!empty($variableInfo['table'])) {
                    $value .= '<li>' . $this->seeList('see_table_more', 'see_tables_more', $variableInfo['table']) . '</li>';
                }
            }
            $value .= '</ul>';
        }

        return [
            'type' => 'markup',
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
     * Returns the set of {@see \Siel\Acumulus\Data\Customer} mapping fields.
     */
    protected function getRelationFields(): array
    {
        return [
            'contactYourId' => [
                'type' => 'text',
                'label' => $this->t('field_contactYourId'),
                'description' => $this->t('desc_contactYourId'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'vatNumber' => [
                'type' => 'text',
                'label' => $this->t('field_vatNumber'),
                'description' => $this->t('desc_vatNumber'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'telephone' => [
                'type' => 'text',
                'label' => $this->t('field_telephone'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'telephone2' => [
                'type' => 'text',
                'label' => $this->t('field_telephone2'),
                'description' => $this->t('desc_telephone'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'fax' => [
                'type' => 'text',
                'label' => $this->t('field_fax'),
                'description' => $this->t('desc_fax'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'email' => [
                'type' => 'text',
                'label' => $this->t('field_email'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'mark' => [
                'type' => 'text',
                'label' => $this->t('field_mark'),
                'description' => $this->t('desc_mark'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
        ];
    }

    /**
     * Returns the set of {@see \Siel\Acumulus\Data\Address} fields.
     */
    protected function getAddressFields(): array
    {
        return [
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
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'fullName' => [
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'salutation' => [
                'type' => 'text',
                'label' => $this->t('field_salutation'),
                'description' => $this->t('desc_salutation'),
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
                'description' => $this->t('desc_address'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'postalCode' => [
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'city' => [
                'type' => 'text',
                'label' => $this->t('field_city'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'countryCode' => [
                'type' => 'text',
                'label' => $this->t('field_countryCode'),
                'description' => $this->t('desc_countryCode'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
        ];
    }

    /**
     * Returns the set of {@see \Siel\Acumulus\Data\Invoice} mapping fields.
     */
    protected function getInvoiceFields(): array
    {
        return [
            'description' => [
                'type' => 'text',
                'label' => $this->t('field_description'),
                'description' => $this->t('desc_description'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'descriptionText' => [
                'type' => 'textarea',
                'label' => $this->t('field_descriptionText'),
                'description' => $this->t('desc_descriptionText'),
                'attributes' => [
                    'size' => 60,
                    'rows' => 6,
                    'style' => 'box-sizing: border-box; width: 83%; min-width: 24em;',
                ],
            ],
            'invoiceNotes' => [
                'type' => 'textarea',
                'label' => $this->t('field_invoiceNotes'),
                'description' => $this->t('desc_invoiceNotes'),
                'attributes' => [
                    'size' => 60,
                    'rows' => 6,
                    'style' => 'box-sizing: border-box; width: 83%; min-width: 24em;',
                ],
            ],
        ];
    }

    /**
     * Returns the set of {@see \Siel\Acumulus\Data\Line} mapping fields.
     */
    protected function getInvoiceLinesFields(): array
    {
        return [
            'itemNumber' => [
                'type' => 'text',
                'label' => $this->t('field_itemNumber'),
                'description' => $this->t('desc_itemNumber'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'productName' => [
                'type' => 'text',
                'label' => $this->t('field_productName'),
                'description' => $this->t('desc_productName'),
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
                'description' => $this->t('desc_costPrice'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
        ];
    }

    protected function getEmailInvoiceFields(): array
    {
        return [
            'emailTo' => [
                'type' => 'text',
                'label' => $this->t('field_emailTo'),
                'description' => $this->t('desc_emailTo'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'emailBcc' => [
                'type' => 'text',
                'label' => $this->t('field_emailBcc'),
                'description' => $this->t('desc_emailBcc'),
                'attributes' => [
                    'multiple' => true,
                    'size' => 60,
                ],
            ],
            'emailFrom' => [
                'type' => 'text',
                'label' => $this->t('field_emailFrom'),
                'description' => $this->t('desc_emailFrom'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'subject' => [
                'type' => 'text',
                'label' => $this->t('field_subject'),
                'description' => $this->t('desc_subject'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
        ];
    }

    protected function getEmailPackingSlipFields(): array
    {
        return [
            'packingSlipEmailTo' => [
                'type' => 'text',
                'label' => $this->t('field_packingSlipEmailTo'),
                'description' => $this->t('desc_packingSlipEmailTo') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
                ],
            ],
            'packingSlipEmailBcc' => [
                'type' => 'text',
                'label' => $this->t('field_packingSlipEmailBcc'),
                'description' => $this->t('desc_packingSlipEmailBcc') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => 60,
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
                'value' => sprintf($this->t('button_link'), $this->t('config_form_link_text'), $this->shopCapabilities->getLink('config')),
            ],
        ];
    }

    /**
     * Changes the names and ids of a set of fields.
     *
     * - The id is prefixed with the data type to make it unique across different field
     *   sets with similar field names.
     * - The name is changed into array syntax where the original id becomes a key for an
     *   entry in an array named after the datatype.
     *
     * Example: field 'address1" for data type 'invoice' gets id = 'invoice_address1', and
     * name = 'invoice[address1]'.
     */
    protected function addNames(array $fields, string $dataType): array
    {
        $result = [];
        foreach ($fields as $id => $field) {
            $field['name'] = "{$dataType}[$id]";
            $result["{$dataType}_$id"] = $field;
        }
        return $result;
    }
}
