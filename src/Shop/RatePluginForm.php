<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

/**
 * Defines a message that asks the user to rate the Acumulus plugin on the web
 * shop specific marketplace.
 *
 * This form contains only text and 2 buttons.
 *
 * SECURITY REMARKS
 * ----------------
 * The only user provided value is the button clicked and that is sanitised as
 * it gets printed in an error message if not recognised (thus faulty user
 * input). For the rest it is only compared to hardcoded values.
 *
 * @noinspection PhpUnused
 */
class RatePluginForm extends Form
{
    protected string $action = '';

    public function __construct(
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct(null, $formHelper, $shopCapabilities, $config, $environment, $translator, $log);
        $this->addMeta = false;
        $this->isFullPage = false;
        $this->addSeverityClassToFields = false;
        $translations = new RatePluginFormTranslations();
        $this->translator->add($translations);
    }

    /**
     * {@inheritdoc}
     *
     * This override handles the case that this message is displayed on another
     * Acumulus form and the post thus is meant for that form not this one.
     */
    public function isSubmitted(): bool
    {
        return parent::isSubmitted() && isset($_POST['clicked']);
    }

    /**
     * @inheritDoc
     *
     * This override adds sanitation to the values and already combines some
     * values to retrieve a Source object
     */
    protected function setSubmittedValues(): void
    {
        parent::setSubmittedValues();

        // Sanitise service: lowercase ascii.
        $this->submittedValues['service'] = preg_replace('/[^a-z]/', '', $this->submittedValues['clicked']);
    }

    /**
     * {@inheritdoc}
     *
     * Performs the given action on the Acumulus invoice for the given Source.
     */
    protected function execute(): bool
    {
        $this->action = $this->getSubmittedValue('service');
        switch ($this->action) {
            case 'later':
                $result = $this->acumulusConfig->save(['showRatePluginMessage' => time() + 7 * 24 * 60 * 60]);
                break;
            case 'done':
                $result = $this->acumulusConfig->save(['showRatePluginMessage' => PHP_INT_MAX]);
                break;
            default:
                $this->createAndAddMessage(sprintf($this->t('unknown_action'), $this->action), Severity::Error);
                $result = false;
                break;
        }

        return $result;
    }

    protected function getFieldDefinitions(): array
    {
        switch ($this->action) {
            case 'done':
                $fields = [
                    'done' => [
                        'type' => 'markup',
                        'value' => sprintf($this->t('done_thanks'), $this->t('module')),
                    ],
                ];
                break;
            case 'later':
                $fields = [
                    'later' => [
                        'type' => 'markup',
                        'value' => $this->t('no_problem'),
                    ],
                ];
                break;
            default:
                $fields = $this->getFieldDefinitionsFull();
                break;
        }
        return $fields;
    }

    /**
     * Returns the field definitions when we are showing the full message.
     *
     * @return array[];
     */
    protected function getFieldDefinitionsFull(): array
    {
        return [
            'acumulus-rate' => [
                'type' => 'fieldset',
                'fields' => [
                    'logo' => [
                        'type' => 'markup',
                        'value' => $this->getLogo(100),
                    ],
                    'message' => [
                        'type' => 'markup',
                        'value' => sprintf($this->t('rate_acumulus_plugin'), $this->t('module'), $this->t('review_on_marketplace')),
                    ],
                    'done' => [
                        'type' => 'button',
                        'value' => $this->t('do'),
                        'attributes' => [
                            'onclick' => "window.open('" . $this->t('review_url') . "')",
                            'class' => 'acumulus-ajax',
                        ],
                    ],
                    'later' => [
                        'type' => 'button',
                        'value' => $this->t('later'),
                        'attributes' => [
                            'class' => 'acumulus-ajax',
                        ],
                    ],
                ],
            ],
        ];
    }
}
