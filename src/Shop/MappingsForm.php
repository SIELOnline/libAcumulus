<?php
/**
 * @noinspection DuplicatedCode This form started as a duplicate of
 *   {@see \Siel\Acumulus\Shop\AdvancedConfigForm}.
 * @todo: Change form field names to constants from the Fld class.
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
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\CheckAccount;
use Siel\Acumulus\Helpers\FieldExpanderHelp;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Meta;

use function sprintf;

/**
 * Provides form handling for the mappings.
 *
 * Shop specific may optionally (have to) override:
 * - setSubmittedValues()
 *
 * @noinspection EfferentObjectCouplingInspection
 * @noinspection PhpUnused
 */
class MappingsForm extends Form
{
    protected const Size = 70;
    protected const SizeLong = 150;

    protected Mappings $mappings;
    protected FieldExpanderHelp $fieldExpanderHelp;

    public function __construct(
        Mappings $mappings,
        FieldExpanderHelp $fieldExpanderHelp,
        AboutForm $aboutForm,
        Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        CheckAccount $checkAccount,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct($acumulusApiClient, $formHelper, $checkAccount, $shopCapabilities, $config, $environment, $translator, $log);
        $this->mappings = $mappings;
        $this->fieldExpanderHelp = $fieldExpanderHelp;
        $this->aboutForm = $aboutForm;
        $this->translator->add(new ConfigFormTranslations());
    }

    /**
     * {@inheritdoc}
     *
     * This is the set of values as are stored in the config.
     *
     * @noinspection PhpMissingParentCallCommonInspection Parent returns an empty array.
     */
    protected function getDefaultFormValues(): array
    {
        return $this->mappings->getAll();
    }

    /**
     * {@inheritdoc}
     *
     * Saves the submitted and validated form values in the mappings store.
     */
    protected function execute(): bool
    {
        return $this->mappings->save($this->submittedValues);
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection  Parent is empty.
     */
    protected function validate(): void
    {
        $this->validateEmailInvoiceFields();
        $this->validateEmailPackingSlipFields();
    }

    /**
     * Validates fields in the "Email invoice pdf" mappings fieldset.
     */
    protected function validateEmailInvoiceFields(): void
    {
        // Check for valid email address if no token syntax is used.
        if (!empty($this->submittedValues['emailTo'])
            && !str_contains($this->submittedValues['emailTo'], '[')
            && !$this->isEmailAddress($this->submittedValues['emailTo'], true)
        ) {
            $this->addFormMessage($this->t('message_validate_email_5'), Severity::Error, 'emailTo');
        }

        // Check for valid email addresses if no token syntax is used.
        if (!empty($this->submittedValues['emailBcc'])
            && !str_contains($this->submittedValues['emailBcc'], '[')
            && !$this->isEmailAddress($this->submittedValues['emailBcc'], true)
        ) {
            $this->addFormMessage($this->t('message_validate_email_3'), Severity::Error, 'emailBcc');
        }

        // Check for valid email address if no token syntax is used.
        if (!empty($this->submittedValues['emailFrom'])
            && !str_contains($this->submittedValues['emailFrom'], '[')
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
            && !str_contains($this->submittedValues['packingSlipEmailTo'], '[')
            && !$this->isEmailAddress($this->submittedValues['packingSlipEmailTo'], true)
        ) {
            $this->addFormMessage($this->t('message_validate_packing_slip_email_1'), Severity::Error, 'packingSlipEmailTo');
        }

        // Check that valid bcc mail addresses have been filled in.
        if (!empty($this->submittedValues['packingSlipEmailBcc'])
            && !str_contains($this->submittedValues['packingSlipEmailBcc'], '[')
            && !$this->isEmailAddress($this->submittedValues['packingSlipEmailBcc'], true)
        ) {
            $this->addFormMessage($this->t('message_validate_packing_slip_email_2'), Severity::Error, 'packingSlipEmailBcc');
        }

        // Check for valid email address if no token syntax is used.
        if (!empty($this->submittedValues['packingSlipEmailFrom'])
            && !str_contains($this->submittedValues['packingSlipEmailFrom'], '[')
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
     *
     * @throws \JsonException|\RuntimeException
     */
    protected function getFieldDefinitions(): array
    {
        $accountStatus = $this->getAccountStatus();

        $fields = [
            'configHeader' => [
                'type' => 'details',
                'summary' => $this->t('config_form_header'),
                'fields' => $this->getSettingsLinkFields(),
            ],
            'fieldExpanderHelpHeader' => [
                'type' => 'details',
                'summary' => $this->t('tokenHelpHeader'),
                'description' => $this->t('desc_tokens'),
                'fields' => $this->getFieldExpanderHelp(),
            ],
            'relationMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('relationMappingsHeader'),
                'description' => $this->t('desc_relationMappingsHeader'),
                'fields' => $this->makeArrayFields($this->getRelationFields(), DataType::Customer),
            ],
            'invoiceAddressMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('invoiceAddressMappingsHeader'),
                'description' => $this->t('desc_invoiceAddressMappingsHeader'),
                'fields' => $this->makeArrayFields($this->getAddressFields(), AddressType::Invoice),
            ],
            'shippingAddressMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('shippingAddressMappingsHeader'),
                'description' => $this->t('desc_shippingAddressMappingsHeader'),
                'fields' => $this->makeArrayFields($this->getAddressFields(), AddressType::Shipping),
            ],
            'invoiceMappings' => [
                'type' => 'fieldset',
                'legend' => $this->t('invoiceMappingsHeader'),
                'fields' => $this->makeArrayFields($this->getInvoiceFields(), DataType::Invoice),
            ],
            'invoiceLinesMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('invoiceLinesMappingsHeader'),
                'fields' => $this->makeArrayFields($this->getInvoiceLinesFields(), LineType::Item),
            ],
            'emailInvoicePdfMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('emailInvoicePdfMappingsHeader'),
                'fields' => $this->makeArrayFields($this->getEmailInvoiceFields(), EmailAsPdfType::Invoice),
            ],
            'emailPackingSlipPdfMappingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('emailPackingSlipPdfMappingsHeader'),
                'fields' => $this->makeArrayFields($this->getEmailPackingSlipFields(), EmailAsPdfType::PackingSlip),
            ],
        ];
        // We could only show this fieldset if stock management has been enabled, but then
        // we should do the same for the email PDF fieldsets.
        if ($this->shopCapabilities->hasStockManagement() /*&& $this->acumulusConfig->get('stockManagementEnabled')*/) {
            $fields['productMappingsHeader'] = [
                'type' => 'fieldset',
                'legend' => $this->t('productMappingsHeader'),
                'fields' => $this->getStockManagementFields(),
            ];
        }
        $fields['versionInformation'] = $this->getAboutBlock($accountStatus);
        return $fields;
    }

    /**
     * @return array
     *   The set of possible tokens per object.
     *
     * @throws \JsonException|\RuntimeException
     */
    protected function getFieldExpanderHelp(): array
    {
        return [
            'fieldExpanderHelp' => [
                'type' => 'markup',
                'value' => $this->fieldExpanderHelp->getHelp(),
            ],
        ];
    }

    /**
     * Returns the set of {@see \Siel\Acumulus\Data\Customer} mapping fields.
     */
    protected function getRelationFields(): array
    {
        return [
            Fld::ContactYourId => [
                'type' => 'text',
                'label' => $this->t('field_contactYourId'),
                'description' => $this->t('desc_contactYourId'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Salutation => [
                'type' => 'text',
                'label' => $this->t('field_salutation'),
                'description' => $this->t('desc_salutation'),
                'attributes' => [
                    'size' => self::SizeLong,
                ],
            ],
            Fld::VatNumber => [
                'type' => 'text',
                'label' => $this->t('field_vatNumber'),
                'description' => $this->t('desc_vatNumber'),
                'attributes' => [
                    'size' => self::SizeLong,
                ],
            ],
            Fld::Telephone => [
                'type' => 'text',
                'label' => $this->t('field_telephone1'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Telephone2 => [
                'type' => 'text',
                'label' => $this->t('field_telephone2'),
                'description' => $this->t('desc_telephone12'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Fax => [
                'type' => 'text',
                'label' => $this->t('field_fax'),
                'description' => $this->t('desc_fax1'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Email => [
                'type' => 'text',
                'label' => $this->t('field_email'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Mark => [
                'type' => 'text',
                'label' => $this->t('field_mark'),
                'description' => $this->t('desc_mark'),
                'attributes' => [
                    'size' => self::Size,
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
            Fld::CompanyName1 => [
                'type' => 'text',
                'label' => $this->t('field_companyName1'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::CompanyName2 => [
                'type' => 'text',
                'label' => $this->t('field_companyName2'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::FullName => [
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName'),
                'attributes' => [
                    'size' => self::SizeLong,
                ],
            ],
            Fld::Address1 => [
                'type' => 'text',
                'label' => $this->t('field_address1'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Address2 => [
                'type' => 'text',
                'label' => $this->t('field_address2'),
                'description' => $this->t('desc_address'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::PostalCode => [
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::City => [
                'type' => 'text',
                'label' => $this->t('field_city'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::CountryCode => [
                'type' => 'text',
                'label' => $this->t('field_countryCode'),
                'description' => $this->t('desc_countryCode'),
                'attributes' => [
                    'size' => self::Size,
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
            Fld::Description => [
                'type' => 'text',
                'label' => $this->t('field_description'),
                'description' => $this->t('desc_description'),
                'attributes' => [
                    'size' => self::SizeLong,
                ],
            ],
            Fld::DescriptionText => [
                'type' => 'textarea',
                'label' => $this->t('field_descriptionText'),
                'description' => $this->t('desc_descriptionText'),
                'attributes' => [
                    'size' => self::SizeLong,
                    'rows' => 6,
                    'style' => 'box-sizing: border-box; width: 83%; min-width: 24em;',
                ],
            ],
            Fld::InvoiceNotes => [
                'type' => 'textarea',
                'label' => $this->t('field_invoiceNotes'),
                'description' => $this->t('desc_invoiceNotes'),
                'attributes' => [
                    'size' => self::SizeLong,
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
            Fld::ItemNumber => [
                'type' => 'text',
                'label' => $this->t('field_itemNumber'),
                'description' => $this->t('desc_itemNumber'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Product => [
                'type' => 'text',
                'label' => $this->t('field_productName'),
                'description' => $this->t('desc_productName'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Nature => [
                'type' => 'text',
                'label' => $this->t('field_nature'),
                'description' => $this->t('desc_nature'),
                'attributes' => [
                    'size' => 30,
                ],
            ],
            Fld::CostPrice => [
                'type' => 'text',
                'label' => $this->t('field_costPrice'),
                'description' => $this->t('desc_costPrice'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
        ];
    }

    protected function getEmailInvoiceFields(): array
    {
        return [
            Fld::EmailTo => [
                'type' => 'text',
                'label' => $this->t('field_emailTo'),
                'description' => $this->t('desc_emailTo'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::EmailBcc => [
                'type' => 'text',
                'label' => $this->t('field_emailBcc'),
                'description' => $this->t('desc_emailBcc'),
                'attributes' => [
                    'multiple' => true,
                    'size' => self::Size,
                ],
            ],
            Fld::EmailFrom => [
                'type' => 'text',
                'label' => $this->t('field_emailFrom'),
                'description' => $this->t('desc_emailFrom'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::Subject => [
                'type' => 'text',
                'label' => $this->t('field_subject'),
                'description' => $this->t('desc_subject'),
                'attributes' => [
                    'size' => self::SizeLong,
                ],
            ],
        ];
    }

    protected function getEmailPackingSlipFields(): array
    {
        return [
            Fld::EmailTo => [
                'type' => 'text',
                'label' => $this->t('field_packingSlipEmailTo'),
                'description' => $this->t('desc_packingSlipEmailTo') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
            Fld::EmailBcc => [
                'type' => 'text',
                'label' => $this->t('field_packingSlipEmailBcc'),
                'description' => $this->t('desc_packingSlipEmailBcc') . ' ' . $this->t('msg_token'),
                'attributes' => [
                    'size' => self::Size,
                ],
            ],
        ];
    }

    protected function getStockManagementFields(): array
    {
        return $this->makeArrayFields([
                Meta::MatchShopFieldSpecification => [
                    'type' => 'text',
                    'label' => $this->t('field_matchShopFieldSpecification'),
                    'description' => $this->t('desc_matchShopFieldSpecification') . ' ' . $this->t(
                            'desc_matchShopFieldSpecificationExample'
                        ),
                    'attributes' => [
                        'size' => self::Size,
                    ],
                ],
            ], DataType::Product)
            + $this->makeArrayFields([
                Fld::StockDescription => [
                    'type' => 'text',
                    'label' => $this->t('field_stockDescription'),
                    'description' => $this->t('desc_stockDescription') . ' ' . $this->t('msg_token'),
                    'attributes' => [
                        'size' => self::Size,
                    ],
                ],
            ], DataType::StockTransaction);

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
    protected function getSettingsLinkFields(): array
    {
        return [
            'tellAboutSettings' => [
                'type' => 'markup',
                'value' => sprintf($this->t('desc_settings'), $this->t('settings_form_link_text'), $this->t('menu_settings')),
            ],
            'basicSettingsLink' => [
                'type' => 'markup',
                'value' => sprintf(
                    $this->t('button_link'),
                    $this->t('settings_form_link_text'),
                    $this->shopCapabilities->getLink('settings', null)
                ),
            ],
        ];
    }

    /**
     * Changes the form field names into array names.
     *
     * The id as well as the name are changed:
     * - The id is prefixed with the data type to make it unique across different field
     *   sets with similar field names.
     * - The name is changed into array syntax where the original id becomes a key for an
     *   entry in an array named after the datatype.
     *
     * Example: field {@see Fld::Address1} for {@see DataType::Invoice} gets
     * - id = 'invoice_address1'
     * - name = 'invoice[address1]'.
     */
    protected function makeArrayFields(array $fields, string $dataType): array
    {
        $result = [];
        foreach ($fields as $id => $field) {
            $field['name'] = "{$dataType}[$id]";
            $field['id'] = "{$dataType}_$id";
            $result[$field['name']] = $field;
        }
        return $result;
    }
}
