<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\CheckAccount;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

use function count;
use function sprintf;

/**
 * Shows a plugin version specific message that informs the user about changes in the new
 * Acumulus plugin version.
 *
 * This form contains only text and 2 buttons.
 *
 * SECURITY REMARKS
 * ----------------
 * The only user provided value is the name of the button clicked on POST and that is
 * sanitised as it gets printed in an error message if not recognised (thus faulty user
 * input). For the rest it is only compared to hardcoded values.
 *
 * @noinspection PhpUnused
 */
class MessageForm extends Form
{
    protected string $action = '';

    public function __construct(
        FormHelper $formHelper,
        CheckAccount $checkAccount,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct(null, $formHelper, $checkAccount, $shopCapabilities, $config, $environment, $translator, $log);
        $this->addMeta = false;
        $this->isFullPage = false;
        $this->addSeverityClassToFields = false;
        $this->translator->add(new MessageFormTranslations());
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
                $result = $this->acumulusConfig->save(['showPluginV8Message' => time() + 7 * 24 * 60 * 60]);
                break;
            case 'hide':
                $result = $this->acumulusConfig->save(['showPluginV8Message' => PHP_INT_MAX]);
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
        return match ($this->action) {
            'later', 'hide' => [
                $this->action => [
                    'type' => 'markup',
                    'value' => $this->t('no_problem'),
                ],
            ],
            default => $this->getFieldDefinitionsFull(),
        };
    }

    /**
     * Returns the field definitions when we are showing the full message.
     *
     * @return array[];
     */
    protected function getFieldDefinitionsFull(): array
    {
        $detail = '';
        $overriddenMappings = $this->acumulusConfig->get('showPluginV8MessageOverriddenMappings');
        if (count($overriddenMappings) > 0) {
            $detail = sprintf($this->t('plugin_v8_message_mappings_detail'), implode(', ', $overriddenMappings));
        }
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
                        'value' => sprintf($this->t('plugin_v8_message'),
                            'https://forum.acumulus.nl/index.php/topic,8444.0.html',
                            $this->shopCapabilities->getLink('settings'),
                            $this->shopCapabilities->getLink('mappings'),
                            $detail),
                    ],
                    'later' => [
                        'type' => 'button',
                        'value' => $this->t('later'),
                        'attributes' => [
                            'class' => 'acumulus-ajax',
                        ],
                    ],
                    'hide' => [
                        'type' => 'button',
                        'value' => $this->t('hide'),
                        'attributes' => [
                            'class' => 'acumulus-ajax',
                        ],
                    ],
                ],
            ],
        ];
    }
}
