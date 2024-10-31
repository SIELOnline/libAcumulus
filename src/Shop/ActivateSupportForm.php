<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\CheckAccount;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

use function sprintf;

/**
 * Provides the "Activate pro support" form.
 *
 * Shop specific overrides should - of course - implement the abstract method:
 * - none
 * Should typically override:
 * - none
 * And may optionally (have to) override:
 * - none
 */
class ActivateSupportForm extends Form
{
    protected const RegExp_Token = '[0-9a-zA-Z]{32}';

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

        $translations = new ActivateSupportFormTranslations();
        $this->translator->add($translations);
    }

    protected function validate(): void
    {
        $hostName = $this->environment->get()['hostName'];
        if (preg_match('/^' . self::RegExp_Token . '$/', $this->submittedValues['support_token']) === false) {
            $this->addFormMessage($this->t('message_validate_invalid_token'), Severity::Error, 'support_token');
        }
        if ($this->submittedValues['support_website'] !== $hostName) {
            $this->addFormMessage($this->t('message_validate_activate_hostname_changed'), Severity::Error, 'support_website');
        }
    }

    /**
     * {@inheritdoc}
     *
     * Registers the support token for this website with Acumulus.
     */
    protected function execute(): bool
    {
        $token = $this->submittedValues['support_token'];
        $location = $this->submittedValues['support_website'];
        $result = $this->acumulusApiClient->registerSupport($token, $location);
        $this->addMessages($result->getMessages(Severity::WarningOrWorse));
        // Support has been registered, we don't need the response of this API
        // call, as we use the response of my_acumulus to list activated tokens
        // in the "About" block.
        return !$result->hasError();
    }

    protected function getFieldDefinitions(): array
    {
        $fields = [];

        // 1st fieldset: Batch options.
        $fields['activateFields'] = [
            'type' => 'fieldset',
            'legend' => sprintf($this->t('activateFieldsHeader'), $this->t('module')),
            'fields' => [
                'support_token' => [
                    'type' => 'text',
                    'label' => $this->t('field_activate_token'),
                    'description' => sprintf($this->t('desc_activate_token'), $this->t('module')),
                    'attributes' => [
                        'required' => true,
                        'size' => 40,
                        'pattern' => self::RegExp_Token,
                        'title' => 'Kopieer & plak hier de ontvangen code'
                    ],
                ],
                'support_website' => [
                    'type' => 'text',
                    'label' => $this->t('field_activate_website'),
                    'description' => $this->t('desc_activate_website'),
                    'attributes' => [
                        'readonly' => true,
                    ],
                    'value' => $this->environment->get()['hostName'],
                ],
            ],
        ];

        // 2nd fieldset: About.
        $accountStatus = $this->getAccountStatus();
        $fields['versionInformation'] = $this->getAboutBlock($accountStatus);

        return $fields;
    }
}
