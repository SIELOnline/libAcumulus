<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;

/**
 * Class ConfirmUninstallForm
 */
class ConfirmUninstallForm extends Form
{
    public function __construct(
        Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct($acumulusApiClient, $formHelper, $shopCapabilities, $config, $environment,$translator, $log);

        $translations = new ConfirmUninstallFormTranslations();
        $this->translator->add($translations);
    }

    /**
     * Executes the form action on valid form submission.
     *
     * Override to implement the actual form handling, like saving values.
     */
    protected function execute(): bool
    {
        // @todo: Implement execute() method.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFieldDefinitions(): array
    {
        $fields = [];

        // 1st fieldset: Confirm uninstall message.
        $fields['uninstallHeader'] = [
            'type' => 'fieldset',
            'legend' => $this->t('uninstallHeader'),
            'fields' => [
                'uninstall_message' =>  [
                    'type' => 'markup',
                    'value' => $this->t('desc_uninstall'),
                ],
            ],
        ];

        return $fields;
    }
}
