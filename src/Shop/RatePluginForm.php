<?php

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;

/**
 * Defines the Acumulus invoice status overview form.
 *
 * This form is mostly informative but contains some buttons and a few fields
 * to update the invoice in Acumulus.
 *
 * SECURITY REMARKS
 * ----------------
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
        parent::__construct($formHelper, $shopCapabilities, $config, $translator, $log);

        $translations = new RatePluginFormTranslations();
        $this->translator->add($translations);
    }

    /**
     * Returns the url to the logo.
     *
     * @return string
     */
    protected function getLogoUrl()
    {
        return $this->shopCapabilities->getLink('logo');
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
                $this->addSuccessMessage(sprintf($this->t('done_thanks'), $this->t('module')));
                break;
            default:
                $this->addErrorMessages(sprintf($this->t('unknown_action'), $this->action));
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
                $fields = array(
                    'done' => array(
                        'type' => 'markup',
                        'value' => sprintf($this->t('done_thanks'), $this->t('module')),
                    ),
                );
                break;
            case 'later':
                $fields = array(
                    'later' => array(
                        'type' => 'markup',
                        'value' => $this->t('no_problem'),
                    ),
                );
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
        $img = '<img src="' . $this->getLogoUrl() . '" alt="Logo SIEL Acumulus" title="SIEL Acumulus" width="100" height="100">';
        return array(
            'acumulus-rate-plugin' => array(
                'type' => 'fieldset',
                'fields' => array(
                    'logo' => array(
                        'type' => 'markup',
                        'value' => $img,
                    ),
                    'message' => array(
                        'type' => 'markup',
                        'value' => sprintf($this->t('rate_acumulus_plugin'), $this->t('module'), $this->t('review_on_marketplace')),
                    ),
                    'done' => array(
                        'type' => 'button',
                        'value' => $this->t('do'),
                        'attributes' => array(
                            'onclick' => "window.open('" . $this->t('review_url') . "')",
                            'class' => 'acumulus-ajax',
                        ),
                    ),
                    'later' => array(
                        'type' => 'button',
                        'value' => $this->t('later'),
                        'attributes' => array(
                            'class' => 'acumulus-ajax',
                        ),
                    ),
                ),
            ),
        );
    }
}
