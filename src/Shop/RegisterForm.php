<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTimeImmutable;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\CheckAccount;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

use function sprintf;

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
    protected array $signUpResponse;

    public function __construct(
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
        parent::__construct(
            $acumulusApiClient,
            $formHelper,
            $checkAccount,
            $shopCapabilities,
            $config,
            $environment,
            $translator,
            $log
        );
        $this->aboutForm = $aboutForm;
        $this->translator->add(new RegisterFormTranslations());
    }

    /**
     * @inheritDoc
     */
    protected function validate(): void
    {
        if (empty($this->submittedValues[Fld::Gender])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_gender')), Severity::Error, Fld::Gender);
        }

        if (empty($this->submittedValues[Fld::FullName])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_fullName')), Severity::Error, Fld::FullName);
        }

        if (empty($this->submittedValues[Fld::LoginName])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_loginName')), Severity::Error, Fld::LoginName);
        } elseif (mb_strlen($this->submittedValues[Fld::LoginName]) < 6) {
            $this->addFormMessage(sprintf($this->t('message_validate_loginname_0'), $this->t('field_loginName')), Severity::Error, Fld::LoginName);
        }

        if (empty($this->submittedValues[Fld::CompanyTypeId]) && $this->submittedValues[Fld::CompanyTypeId] !== 0) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_companyTypeId')), Severity::Error, Fld::CompanyTypeId);
        }

        if (empty($this->submittedValues[Fld::CompanyName])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_companyName')), Severity::Error, Fld::CompanyName);
        }

        if (empty($this->submittedValues[Fld::Address])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_address')), Severity::Error, Fld::Address);
        }

        if (empty($this->submittedValues[Fld::PostalCode])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_postalCode')), Severity::Error, Fld::PostalCode);
        } elseif (!preg_match('/^\d{4}\s*[a-zA-Z]{2}$/', $this->submittedValues[Fld::PostalCode])) {
            $this->addFormMessage($this->t('message_validate_postalCode_0'), Severity::Error, Fld::PostalCode);
        }

        if (empty($this->submittedValues[Fld::City])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_city')), Severity::Error, Fld::City);
        }

        if (empty($this->submittedValues[Fld::Email])) {
            $this->addFormMessage(sprintf($this->t('message_validate_required_field'), $this->t('field_email')), Severity::Error, Fld::Email);
        } elseif (!$this->isEmailAddress($this->submittedValues[Fld::Email])) {
            $this->addFormMessage($this->t('message_validate_email_0'), Severity::Error, Fld::Email);
        }
    }

    /**
     * @inheritDoc
     */
    protected function execute(): bool
    {
        $tags = [
            Fld::CompanyTypeId,
            Fld::CompanyName,
            Fld::FullName,
            Fld::LoginName,
            Fld::Gender,
            Fld::Address,
            Fld::PostalCode,
            Fld::City,
            Fld::Email,
            Fld::Telephone,
            Fld::BankAccount,
            Fld::Notes,
        ];
        $submittedValues = $this->submittedValues;
        $signUp = [];
        foreach ($tags as $tag) {
            $this->addIfIsset($signUp, $tag, $submittedValues);
        }

        // Complete $signUp with non-form values.
        if (empty($signUp[Fld::Gender])) {
            $signUp[Fld::Gender] = Api::Gender_Neutral;
        }
        $signUp[Fld::CreateApiUser] = Api::CreateApiUser_Yes;

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
            Fld::ContractCode => $signUpResponse[Fld::ContractCode],
            Fld::UserName => $signUpResponse['contractapiuserloginname'],
            Fld::Password => $signUpResponse['contractapiuserpassword'],
            Fld::EmailOnError => $this->getSubmittedValue(Fld::Email),
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
//        $this->submittedValues[Fld::Email] = 'erwin@burorader.com';
        // End test success screen

        $fields = [];
        if (!isset($this->signUpResponse)) {
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
            Fld::Gender => [
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
            Fld::FullName => [
                'type' => 'text',
                'label' => $this->t('field_fullName'),
                'description' => $this->t('desc_fullName'),
                'attributes' => [
                    'required' => true,
                    'size' => 40,
                ],
            ],
            Fld::LoginName => [
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
            Fld::CompanyTypeId => [
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
            Fld::CompanyName => [
                'type' => 'text',
                'label' => $this->t('field_companyName'),
                'attributes' => [
                    'required' => true,
                    'size' => 40,
                ],
            ],
            Fld::Address => [
                'type' => 'text',
                'label' => $this->t('field_address'),
                'attributes' => [
                    'required' => true,
                    'size' => 40,
                ],
            ],
            Fld::PostalCode => [
                'type' => 'text',
                'label' => $this->t('field_postalCode'),
                'attributes' => [
                    'required' => true,
                    'size' => 8,
                ],
            ],
            Fld::City => [
                'type' => 'text',
                'label' => $this->t('field_city'),
                'attributes' => [
                    'required' => true,
                    'size' => 20,
                ],
            ],
            Fld::Email => [
                'type' => 'email',
                'label' => $this->t('field_emailRegistration'),
                'description' => sprintf($this->t('desc_emailRegistration'), $this->t('module')),
                'attributes' => [
                    'required' => true,
                    'size' => 40,
                ],
            ],
            Fld::Telephone => [
                'type' => 'text',
                'label' => $this->t('field_telephoneRegistration'),
                'description' => $this->t('desc_telephoneRegistration'),
                'attributes' => [
                    'size' => 12,
                ],
            ],
            Fld::BankAccount => [
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
            Fld::Notes => [
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
                    DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->signUpResponse['contractenddate'])
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
            htmlspecialchars($this->getSubmittedValue(Fld::Email), ENT_NOQUOTES | ENT_HTML5, 'UTF-8')
        );
        $line2 = $this->t('loginDesc_2');
        return [
            'loginDetails' => [
                'type' => 'fieldset',
                'legend' => $this->t('loginHeader'),
                'description' => "$line1 $line2",
                'fields' => [
                    Fld::ContractCode => [
                        'type' => 'text',
                        'label' => $this->t('field_code'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 8,
                        ],
                        'value' => $this->signUpResponse[Fld::ContractCode],
                    ],
                    Fld::LoginName => [
                        'type' => 'text',
                        'label' => $this->t('field_loginName'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contract' . Fld::LoginName],
                    ],
                    Fld::Password => [
                        'type' => 'text',
                        'label' => $this->t('field_password'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contract' . Fld::Password],
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
                    'contractapiuser' . Fld::ContractCode => [
                        'type' => 'text',
                        'label' => $this->t('field_code'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 8,
                        ],
                        'value' => $this->signUpResponse[Fld::ContractCode],
                    ],
                    'contractapiuser' . Fld::LoginName => [
                        'type' => 'text',
                        'label' => $this->t('field_loginName'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contractapiuser' . Fld::LoginName],
                    ],
                    'contractapiuser' . Fld::Password => [
                        'type' => 'text',
                        'label' => $this->t('field_password'),
                        'attributes' => [
                            'readonly' => true,
                            'size' => 20,
                        ],
                        'value' => $this->signUpResponse['contractapiuser' . Fld::Password],
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
                        'value' => sprintf($this->t('register_form_success_config_button'), $this->t('module'), $this->shopCapabilities->getLink('settings'), $this->t('button_class')),
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
