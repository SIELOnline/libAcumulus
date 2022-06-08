<?php

namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Tag;

/**
 * Class RegisterForm implements a registration form to register for a
 * temporary free Acumulus account (which can be converted to a full account).
 * It is similar to the form on https://www.siel.nl/acumulus/proefaccount/,
 * though via the API we will also get authentication details for an API
 * account.
 *
 * @noinspection PhpUnused Instantiated by \Siel\Acumulus\Helpers\Container::getForm().
 */
class RegisterForm extends Form
{
    /**
     * @var array
     *   The response structure of a successful sign-up call,
     *   {@link https://www.siel.nl/acumulus/API/Sign_Up/Sign_Up/} for more
     *   details.
     */
    protected $signUpResponse;

    public function __construct(
        AboutForm $aboutForm,
        Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct(
            $acumulusApiClient,
            $formHelper,
            $shopCapabilities,
            $config,
            $environment,
            $translator,
            $log
        );
        $this->aboutForm = $aboutForm;
        $this->translator->add(new RegisterFormTranslations());
        $this->signUpResponse = null;
    }

    /**
     * @inheritDoc
     */
    protected function validate()
    {
        $regexpEmail = '/^[^@<>,; "\']+@([^.@ ,;]+\.)+[^.@ ,;]+$/';

        if (empty($this->submittedValues[Tag::Gender])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_gender')), Severity::Error, Tag::Gender);
        }

        if (empty($this->submittedValues[Tag::FullName])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_fullName')), Severity::Error, Tag::FullName);
        }

        if (empty($this->submittedValues[Tag::LoginName])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_loginName')), Severity::Error, Tag::LoginName);
        } elseif (mb_strlen($this->submittedValues[Tag::LoginName]) < 6) {
            $this->addFormMessage(sprintf($this->t('message_validate_loginname_0'), $this->t('field_loginName')), Severity::Error, Tag::LoginName);
        }

        if (empty($this->submittedValues[Tag::CompanyTypeId]) && $this->submittedValues[Tag::CompanyTypeId] !== 0) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_companyTypeId')), Severity::Error, Tag::CompanyTypeId);
        }

        if (empty($this->submittedValues[Tag::CompanyName])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_companyName')), Severity::Error, Tag::CompanyName);
        }

        if (empty($this->submittedValues[Tag::Address])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_address')), Severity::Error, Tag::Address);
        }

        if (empty($this->submittedValues[Tag::PostalCode])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_postalCode')), Severity::Error, Tag::PostalCode);
        } elseif (!preg_match('/^\d{4}\s*[a-zA-Z]{2}$/', $this->submittedValues[Tag::PostalCode])) {
            $this->addFormMessage($this->t('message_validate_postalCode_0'), Severity::Error, Tag::PostalCode);
        }

        if (empty($this->submittedValues[Tag::City])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_city')), Severity::Error, Tag::City);
        }

        if (empty($this->submittedValues[Tag::Email])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_email')), Severity::Error, Tag::Email);
        } elseif (!preg_match($regexpEmail, $this->submittedValues[Tag::Email])) {
            $this->addFormMessage($this->t('message_validate_email_0'), Severity::Error, Tag::Email);
        }
    }

    /**
     * @inheritDoc
     */
    protected function execute(): bool
    {
        $tags = [
            Tag::CompanyTypeId,
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

        $this->addMessages($result->getMessages(Severity::WarningOrWorse));
        $formSuccess = !$result->hasError();
        if ($formSuccess) {
            $this->signUpResponse = $result->getMainAcumulusResponse();
            $this->setAccountInfo($this->signUpResponse);
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
     *   {@link https://www.siel.nl/acumulus/API/Sign_Up/Sign_Up/} for more
     *   details.
     *
     * @return bool
     *   True on success, false on failure.
     */
    protected function setAccountInfo(array $signUpResponse): bool
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
    protected function getFieldDefinitions(): array
    {
        // Test success screen
//        $this->signUpResponse = [
//            'contractcode' => '218975',
//            'contractloginname' => 'erwind',
//            'contractpassword' => 'WCpAfaW8hABq',
//            'contractstartdate' => '2020-05-25',
//            'contractenddate' => '2020-06-24',
//            'contractapiuserloginname' => 'Acumulus-API-ce5bb',
//            'contractapiuserpassword' => 'OTAcu3eq5VjezM',
//        ];
//        $this->submittedValues[Tag::Email] = 'erwin@burorader.com';
        // End test success screen

        $fields = [];
        if ($this->signUpResponse === null) {
            // Not submitted or errors: render register form.
            $fields += [
                'introContainer' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('introHeader'),
                    'fields' => $this->getIntroFields(),
                ],
                'personSettings' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('personSettingsHeader'),
                    'fields' => $this->getPersonFields(),
                ],
                'companySettings' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('companySettingsHeader'),
                    'description' => $this->t('desc_companySettings'),
                    'fields' => $this->getCompanyFields(),
                ],
                'notesSettings' => [
                    'type' => 'fieldset',
                    'legend' => $this->t('notesSettingsHeader'),
                    'fields' => $this->getNotesFields(),
                ],
            ];
        } else {
            // Successfully submitted: show details of the created account.
            $this->isFullPage = false;
            $fields += $this->getCongratulationsFields();
            $fields += $this->getCreatedAccountFields();
            $fields += $this->getCreatedApiAccountFields();
            $fields += $this->getNextSteps();
            $fields['versionInformation'] = $this->getAboutBlock(null);
        }
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
    protected function getIntroFields(): array
    {
        return [
            'intro' => [
                'type' => 'markup',
                'label' => $this->getLogo(),
                'value' => $this->t('register_form_intro'),
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
     * - 'gender'
     * - 'fullname'
     * - 'loginname'
     * - 'email'
     * - 'telephone'
     *
     * @return array[]
     *   The set of personal related fields.
     */
    protected function getPersonFields(): array
    {
        return [
            Tag::Gender => [
                'type' => 'radio',
                'label' => $this->t('field_gender'),
                'description' => $this->t('desc_gender'),
                'options' => [
                    Api::Gender_Neutral => $this->t('option_gender_neutral'),
                    Api::Gender_Female => $this->t('option_gender_female'),
                    Api::Gender_Male => $this->t('option_gender_male'),
                ],
                'attributes' => [
                    'required' => true,
                ],
            ],
            Tag::FullName => [
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName'),
                'attributes' => [
                    'required' => true,
                    'size' => 40,
                ],
            ],
            Tag::LoginName => [
                'type' => 'text',
                'label' => $this->t('field_loginName'),
                'description' => sprintf($this->t('desc_loginName'), $this->t('module')),
                'attributes' => [
                    'required' => true,
                    'size' => 20,
                ],
            ],
        ];
    }

    /**
     * Returns the set of company related fields.
     *
     * The fields returned:
     * - 'companyname'
     * - 'address'
     * - 'postalcode'
     * - 'city'
     * - 'bankaccount'
     *
     * @return array[]
     *   The set of company related fields.
     */
    protected function getCompanyFields(): array
    {
        return [
            Tag::CompanyTypeId => [
                'type' => 'select',
                'label' => $this->t('field_companyTypeId'),
                'options' => $this->picklistToOptions(
                    $this->acumulusApiClient->getPicklistCompanyTypes(),
                    '',
                    $this->t('option_empty')
                ),
                'attributes' => [
                    'required' => true,
                ],
            ],
            Tag::CompanyName => [
                'type' => 'text',
                'label' => $this->t('field_companyName'),
                'attributes' => [
                    'required' => true,
                    'size' => 40,
                ],
            ],
            Tag::Address => [
                'type' => 'text',
                'label' => $this->t('field_address'),
                'attributes' => [
                    'required' => true,
                    'size' => 40,
                ],
            ],
            Tag::PostalCode => [
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'attributes' => [
                    'required' => true,
                    'size' => 8,
                ],
            ],
            Tag::City => [
                'type' => 'text',
                'label' => $this->t('field_city'),
                'attributes' => [
                    'required' => true,
                    'size' => 20,
                ],
            ],
            Tag::Email => [
                'type' => 'email',
                'label' => $this->t('field_emailRegistration'),
                'description' => sprintf($this->t('desc_emailRegistration'), $this->t('module')),
                'attributes' => [
                    'required' => true,
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
                    'size' => 20,
                ],
            ],
        ];
    }

    /**
     * Returns the set of notes related fields.
     *
     * The fields returned:
     * - 'notes'
     *
     * @return array[]
     *   The set of notes related fields.
     */
    protected function getNotesFields(): array
    {
        return [
            Tag::Notes => [
                'type' => 'textarea',
                'label' => $this->t('field_notes'),
                'description' => sprintf($this->t('desc_notes'), $this->t('module')),
                'attributes' => [
                    'rows' => 6,
                ],
            ],
        ];
    }

    /**
     * Returns text about the successful creation of the temporary account.
     *
     * @return array[]
     *   Markup that gives more information about the test account that has been
     *   created.
     */
    protected function getCongratulationsFields(): array
    {
        return [
            'congratulations' => [
                'type' => 'fieldset',
                'legend' => $this->t('congratulationsHeader'),
                'description' => sprintf(
                    $this->t('congratulationsDesc'),
                    DateTime::createFromFormat(Api::DateFormat_Iso, $this->signUpResponse['contractenddate'])
                        ->format('d-m-Y')
                ),
                'fields' => [],
            ],
        ];
    }

    /**
     * Returns explanatory text about the test account that has been created.
     *
     * @return array[]
     *   Markup that gives more information about the account that has been
     *   created.
     */
    protected function getCreatedAccountFields(): array
    {
        $line1 = sprintf(
            $this->t('loginDesc_1'),
            htmlspecialchars($this->getSubmittedValue(Tag::Email), ENT_NOQUOTES | ENT_HTML5, 'UTF-8')
        );
        $line2 = $this->t('loginDesc_2');
        return [
            'loginDetails' => [
                'type' => 'fieldset',
                'legend' => $this->t('loginHeader'),
                'description' => "$line1 $line2",
                'fields' => [
                    Tag::ContractCode => [
                        'type' => 'text',
                        'label' => $this->t('field_code'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 8,
                        ],
                        'value' => $this->signUpResponse[Tag::ContractCode],
                    ],
                    Tag::LoginName => [
                        'type' => 'text',
                        'label' => $this->t('field_loginName'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contract' . Tag::LoginName],
                    ],
                    Tag::Password => [
                        'type' => 'text',
                        'label' => $this->t('field_password'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contract' . Tag::Password],
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns explanatory text about the test account that has been created.
     *
     * @return array[]
     *   Markup that gives more information about the test account that has been
     *   created.
     */
    protected function getCreatedApiAccountFields(): array
    {
        $line1 = sprintf($this->t('apiLoginDesc'), $this->t('module'));
        return [
            'apiLoginDetails' => [
                'type' => 'fieldset',
                'legend' => sprintf($this->t('apiLoginHeader'), $this->t('module')),
                'description' => $line1,
                'fields' => [
                    'contractapiuser' . Tag::ContractCode => [
                        'type' => 'text',
                        'label' => $this->t('field_code'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 8,
                        ],
                        'value' => $this->signUpResponse[Tag::ContractCode],
                    ],
                    'contractapiuser' . Tag::LoginName => [
                        'type' => 'text',
                        'label' => $this->t('field_loginName'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contractapiuser' . Tag::LoginName],
                    ],
                    'contractapiuser' . Tag::Password => [
                        'type' => 'text',
                        'label' => $this->t('field_password'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contractapiuser' . Tag::Password],
                    ],
                    'apiLoginRemark' => [
                        'type' => 'markup',
                        'value' => sprintf($this->t('apiLoginRemark'), $this->t('module')),
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns explanatory text about what to do next.
     *
     * @return array[]
     *   Markup that explains what to do next.
     */
    protected function getNextSteps(): array
    {
        return [
            'whatsNext' => [
                'type' => 'fieldset',
                'legend' => $this->t('whatsNextHeader'),
                'fields' => [
                    'next1' => [
                        'type' => 'markup',
                        'value' => $this->t('register_form_success_configure_acumulus'),
                    ],
                    'loginLink' => [
                        'type' => 'markup',
                        'value' => sprintf($this->t('register_form_success_login_button'), $this->t('button_class')),
                    ],
                    'next2' => [
                        'type' => 'markup',
                        'value' => '<br>' . sprintf($this->t('register_form_success_configure_module'), $this->t('module')),
                    ],
                    'basicSettingsLink' => [
                        'type' => 'markup',
                        'value' => sprintf($this->t('register_form_success_config_button'), $this->t('module'), $this->shopCapabilities->getLink('config'), $this->t('button_class')),
                    ],
                    'next3' => [
                        'type' => 'markup',
                        'value' => sprintf($this->t('register_form_success_batch'), $this->t('module')),
                    ],
                ],
            ],
        ];
    }
}
