<?php

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

/**
 * Defines a message that asks the user to rate the Acumulus plugin on the
 * webshop specific marketplace.
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
    /**
     * @var string
     */
    protected $action = '';

    /**
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Translator $translator,
        Log $log
    ) {
        parent::__construct(null, $formHelper, $shopCapabilities, $config, $translator, $log);
        $this->addMeta = false;
        $translations = new RatePluginFormTranslations();
        $this->translator->add($translations);
    }

    /**
     * {@inheritdoc}
     *
     * This override handles the case that this message is displayed on another
     * Acumulus form and the post thus is meant for that form not this one.
     */
    public function isSubmitted()
    {
        return parent::isSubmitted() && isset($_POST['clicked']);
    }

    /**
     * @inheritDoc
     *
     * This override adds sanitation to the values and already combines some of
     * the values to retrieve  a Source object
     */
    protected function setSubmittedValues()
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
    protected function execute()
    {
        $result = false;

        $this->action = $this->getSubmittedValue('service');
        switch ($this->action) {
            case 'later':
                $this->acumulusConfig->save(array('showRatePluginMessage' => time() + 7 * 24 * 60 * 60));
                break;
            case 'done':
                $this->acumulusConfig->save(array('showRatePluginMessage' => PHP_INT_MAX));
                $this->addMessage(sprintf($this->t('done_thanks'), $this->t('module')), Severity::Success);
                break;
            default:
                $this->addMessage(sprintf($this->t('unknown_action'), $this->action), Severity::Error);
                break;
        }

        return $result;
    }

    public function getFields()
    {
        if (empty($this->fields)) {
            $this->fields = $this->getFieldDefinitions();
        }
        return $this->fields;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFieldDefinitions()
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
    protected function getFieldDefinitionsFull()
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
