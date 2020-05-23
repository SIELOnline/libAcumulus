<?php

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Tag;

/**
 * Class RegistrationForm implements a registration form to register for a
 * temporary free Acumulus account (which can be converted to a full account).
 *
 * @noinspection PhpUnused Instantiated by \Siel\Acumulus\Helpers\Container::getForm().
 */
class RegistrationForm extends Form
{
    /** @var \Siel\Acumulus\ApiClient\Acumulus */
    protected $acumulusApiClient;

    /**
     * RegistrationForm constructor.
     *
     * @param \Siel\Acumulus\ApiClient\Acumulus $acumulusApiClient
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Acumulus $acumulusApiClient, FormHelper $formHelper, ShopCapabilities $shopCapabilities, Config $config, Translator $translator, Log $log)
    {
        parent::__construct($formHelper, $shopCapabilities, $config, $translator, $log);

        $translations = new RegistrationFormTranslations();
        $this->translator->add($translations);

        $this->acumulusApiClient = $acumulusApiClient;
    }

    /**
     * @inheritDoc
     */
    protected function validate()
    {
        $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';

        // @todo: required, email, gender, postalcode, loginname?
        if (empty($this->submittedValues[Tag::CompanyName])) {
            $this->addMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_companyName')), Severity::Error, Tag::CompanyName);
        }
        if (empty($this->submittedValues[Tag::FullName])) {
            $this->addMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_fullName')), Severity::Error, Tag::FullName);
        }
        if (empty($this->submittedValues[Tag::LoginName])) {
            $this->addMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_loginName')), Severity::Error, Tag::LoginName);
        }
        if (empty($this->submittedValues[Tag::Address])) {
            $this->addMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_address')), Severity::Error, Tag::Address);
        }
        if (empty($this->submittedValues[Tag::PostalCode])) {
            $this->addMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_postalCode')), Severity::Error, Tag::PostalCode);
        } elseif (!preg_match('/^\d{4}\s*[a-zA-Z]{2}$/', $this->submittedValues[Tag::PostalCode])) {
            $this->addMessage($this->t('message_validate_postalCode_0'), Severity::Error, Tag::PostalCode);
        }
        if (empty($this->submittedValues[Tag::City])) {
            $this->addMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_city')), Severity::Error, Tag::City);
        }
        if (empty($this->submittedValues[Tag::Email])) {
            $this->addMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_email')), Severity::Error, Tag::Email);
        } elseif (!preg_match($regexpEmail, $this->submittedValues[Tag::Email])) {
            $this->addMessage($this->t('message_validate_email_0'), Severity::Error, Tag::Email);
        }
// required:  Tag::CompanyName,
//            Tag::FullName,
//            Tag::LoginName,
//            Tag::Address,
//            Tag::PostalCode,
//            Tag::City,
//            Tag::Email,

    }

    /**
     * @inheritDoc
     */
    protected function execute()
    {
        $tags = [
            Tag::CompanyName,
            Tag::FullName,
            Tag::LoginName,
            Tag::Gender,
            Tag::Address,
            Tag::PostalCode,
            Tag::City,
            Tag::Email,
            Tag::Telephone,
            Tag::BankAccount,
            Tag::Notes,
        ];
        $submittedValues = $this->submittedValues;
        $signUp = [];
        foreach ($tags as $tag) {
            $this->addIfIsset($signUp, $tag, $submittedValues);
        }

        // Complete $signUp with non-form values.
        if (empty($signUp[Tag::Gender])) {
            $signUp[Tag::Gender] = Api::Gender_Neutral;
        }
        $signUp[Tag::CreateApiUser] = Api::CreateApiUser_Yes;

        $result = $this->acumulusApiClient->signUp($signUp);

        $formSuccess = !$result->hasError();
        if ($formSuccess) {
            $this->setAccountInfo($result->getResponse());
        } else {
            $this->addMessages($result->getMessages(Severity::WarningOrWorse));
        }

        return $formSuccess;
    }

    /**
     * Processes the account info as received from Acumulus
     *
     * @param array $signUpResponse
     *   The new account info. A keyed array with the keys:
     *   - 'contractcode'
     *   - 'contractloginname'
     *   - 'contractpassword'
     *   - 'contractstartdate'
     *   - 'contractenddate'
     *   - 'contractapiuserloginname'
     *   - 'contractapiuserpassword'
     *   {@see https://www.siel.nl/acumulus/API/Sign_Up/Sign_Up/} for more
     *   details.
     *
     * @return bool
     *   True on success, false on failure.
     */
    protected function setAccountInfo(array $signUpResponse)
    {
        $accountValues = [
            Tag::ContractCode => $signUpResponse[Tag::ContractCode],
            Tag::UserName => $signUpResponse['contractapiuserloginname'],
            Tag::Password => $signUpResponse['contractapiuserpassword'],
            Tag::EmailOnError => $this->getSubmittedValue(Tag::Email),
        ];
        return $this->acumulusConfig->save($accountValues);
    }

    /**
     * @inheritDoc
     */
    protected function getFieldDefinitions()
    {
        $fields = [];
        $fields += [
            'introHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('introHeader'),
                'fields' => $this->getIntroFields(),
            ],
            'personSettingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('personSettingsHeader'),
                'fields' => $this->getPersonFields(),
            ],
            'companySettingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('companySettingsHeader'),
                'description' => $this->t('desc_companySettings'),
                'fields' => $this->getCompanyFields(),
            ],
            'notesSettingsHeader' => [
                'type' => 'fieldset',
                'legend' => $this->t('notesSettingsHeader'),
                'fields' => $this->getNotesFields(),
            ],
        ];
        return $fields;
    }

    /**
     * Returns the set of intro fields.
     *
     * The fields returned:
     * - intro
     *
     * @return array[]
     *   The set of intro fields.
     */
    protected function getIntroFields()
    {
        return [
            'intro' => [
                'type' => 'markup',
                'label' => '<img src="' . $this->getLogoUrl() . '" width="150"; height="150">',
                'value' => $this->t('registration_form_intro'),
                'attributes' => [
                    'label' => [
                        'html' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns the set of personal related fields.
     *
     * The fields returned:
     * - gender
     * - fullname
     * - loginname
     * - email
     * - telephone
     *
     * @return array[]
     *   The set of personal related fields.
     */
    protected function getPersonFields()
    {
        return [
            Tag::Gender => [
                'type' => 'radio',
                'label' => $this->t('field_gender'),
                'description' => $this->t('desc_gender'),
                'options' => [
                    Api::Gender_Female => $this->t('option_gender_female'),
                    Api::Gender_Male => $this->t('option_gender_male'),
                ],
            ],
            Tag::FullName => [
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName'),
                'attributes' => [
//                    'required' => true,
                    'size' => 40,
                ],
            ],
            Tag::LoginName => [
                'type' => 'text',
                'label' => $this->t('field_loginName'),
                'description' => sprintf($this->t('desc_loginName'), $this->t('module')),
                'attributes' => [
//                    'required' => true,
                    'size' => 20,
                ],
            ],
        ];
    }

    /**
     * Returns the set of company related fields.
     *
     * The fields returned:
     * - companyname
     * - address
     * - postalcode
     * - city
     * - bankaccount
     *
     * @return array[]
     *   The set of notes related fields.
     */
    protected function getCompanyFields()
    {
        return [
            Tag::CompanyName => [
                'type' => 'text',
                'label' => $this->t('field_companyName'),
                'attributes' => [
//                    'required' => true,
                    'size' => 40,
                ],
            ],
            Tag::Address => [
                'type' => 'text',
                'label' => $this->t('field_address'),
                'attributes' => [
//                    'required' => true,
                    'size' => 40,
                ],
            ],
            Tag::PostalCode => [
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'attributes' => [
//                    'required' => true,
                    'size' => 8,
                ],
            ],
            Tag::City => [
                'type' => 'text',
                'label' => $this->t('field_city'),
                'attributes' => [
//                    'required' => true,
                    'size' => 20,
                ],
            ],
            Tag::Email => [
                'type' => 'email',
                'label' => $this->t('field_emailRegistration'),
                'description' => sprintf($this->t('desc_emailRegistration'), $this->t('module')),
                'attributes' => [
//                    'required' => true,
                    'size' => 40,
                ],
            ],
            Tag::Telephone => [
                'type' => 'text',
                'label' => $this->t('field_telephone'),
                'description' => $this->t('desc_telephone'),
                'attributes' => [
                    'size' => 12,
                ],
            ],
            Tag::BankAccount => [
                'type' => 'text',
                'label' => $this->t('field_bankAccount'),
                'description' => $this->t('desc_bankAccount'),
                'attributes' => [
//                    'required' => true,
                    'size' => 20,
                ],
            ],

        ];
    }

    /**
     * Returns the set of notes related fields.
     *
     * The fields returned:
     * - notes
     *
     * @return array[]
     *   The set of notes related fields.
     */
    protected function getNotesFields()
    {
        return [
            Tag::Notes => [
                'type' => 'textarea',
                'label' => $this->t('field_notes'),
                'description' => sprintf($this->t('desc_notes'), $this->t('module')),
                'attributes' => array(
                    'rows' => 6,
                ),
            ],
        ];
    }
}
