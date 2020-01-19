<?php
namespace Siel\Acumulus\WooCommerce\Shop;

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
    )
    {
        parent::__construct($formHelper, $shopCapabilities, $config, $translator, $log);

        $translations = new RatePluginFormTranslations();
        $this->translator->add($translations);
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

        // Sanitise service: lowercase ascii characters, numbers, _ and -.
        $this->submittedValues['service'] = preg_replace('/[^a-z0-9_\-]/', '', $this->submittedValues['service']);
    }

    /**
     * {@inheritdoc}
     *
     * Performs the given action on the Acumulus invoice for the given Source.
     */
    protected function execute()
    {
        $result = false;

        $service = $this->getSubmittedValue('service');
        switch ($service) {
            case 'later':
                $result = set_transient('acumulus_rate_plugin', time() + 7 * 24 * 60 * 60);
                break;
            case 'done':
                $result = set_transient('acumulus_rate_plugin', 'done');
                break;
            default:
                $this->addErrorMessages(sprintf($this->t('unknown_action'), $service));
                break;
        }

        return false;
//        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFieldDefinitions()
    {
        $img = '<img src="' . home_url('wp-content/plugins/acumulus/siel-logo.svg') . '" alt="Logo SIEL Acumulus" title="SIEL Acumulus" width="100" height="100">';
        $fields = array(
            'acumulus-logo' => array(
                'type' => 'fieldset',
                'fields' => array(
                    'logo-img' => array(
                        'type' => 'markup',
                        'value' => $img,
                    ),
                ),
            ),
            'acumulus-rate-plugin' => array(
                'type' => 'fieldset',
                'fields' => array(
                    'message' => array(
                        'type' => 'markup',
                        'value' => $this->t('rate_acumulus_plugin'),
                    ),
                    'do' => array(
                        'type' => 'button',
                        'ajax' => array(
                            'service' => 'done',
                        ),
                        'attributes' => array(
                            'onclick' => 'window.open("' . $this->t('review_url') . '")',
                        ),
                        'value' => $this->t('do'),
                    ),
                    'later' => array(
                        'type' => 'button',
                        'ajax' => array(
                            'service' => 'later',
                        ),
                        'value' => $this->t('later'),
                    ),
                ),
            ),
        );

        return $fields;
    }

}
