<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Config\ShopCapabilitiesInterface;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;
use Siel\Acumulus\PluginConfig;

/**
 * Provides batch form handling.
 *
 * Shop specific overrides should - of course - implement the abstract method:
 * - none
 * Should typically override:
 * - none
 * And may optionally (have to) override:
 * - systemValidate()
 * - getDateFormat
 * - getShopDateFormat()
 * - isSubmitted()
 * - setSubmittedValues()
 */
class BatchForm extends Form
{

    const DateFormat = 'Y-m-d';

    /** @var \Siel\Acumulus\Config\ShopCapabilitiesInterface */
    protected $shopCapabilities;

    /** @var \Siel\Acumulus\Shop\InvoiceManager */
    protected $invoiceManager;

    /** @var array */
    protected $log;

    /**
     * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Config\ShopCapabilitiesInterface $shopCapabilities
     * @param \Siel\Acumulus\Shop\InvoiceManager $invoiceManager
     */
    public function __construct(TranslatorInterface $translator, ConfigInterface $config, ShopCapabilitiesInterface $shopCapabilities, InvoiceManager $invoiceManager)
    {
        parent::__construct($translator, $config);

        $translations = new InvoiceTranslations();
        $this->translator->add($translations);

        $translations = new BatchFormTranslations();
        $this->translator->add($translations);

        $this->log = array();
        $this->shopCapabilities = $shopCapabilities;
        $this->invoiceManager = $invoiceManager;
    }

    /**
     * {@inheritdoc}
     *
     * This override adds the log messages from the $log property to the log
     * field.
     */
    protected function getDefaultFormValues()
    {
        $result = parent::getDefaultFormValues();
        $result['send_mode'] = 'send_normal';
        if (!empty($this->log)) {
            $result['log'] = implode("\n", $this->log);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function validate()
    {
        $invoiceSourceTypes = $this->shopCapabilities->getSupportedInvoiceSourceTypes();
        if (empty($this->submittedValues['invoice_source_type'])) {
            $this->errorMessages['invoice_source_type'] = $this->t('message_validate_batch_source_type_required');
        } elseif (!array_key_exists($this->submittedValues['invoice_source_type'], $invoiceSourceTypes)) {
            $this->errorMessages['invoice_source_type'] = $this->t('message_validate_batch_source_type_invalid');
        }

        if ($this->submittedValues['invoice_source_reference_from'] === '' && $this->submittedValues['date_from'] === '') {
            // Either a range of order id's or a range of dates should be entered.
            $this->errorMessages['invoice_source_reference_from'] = $this->t(count($invoiceSourceTypes) === 1 ? 'message_validate_batch_reference_or_date_1' : 'message_validate_batch_reference_or_date_2');
        } elseif ($this->submittedValues['invoice_source_reference_from'] !== '' && $this->submittedValues['date_from'] !== '') {
            // Not both ranges should be entered.
            $this->errorMessages['date_from'] = $this->t(count($invoiceSourceTypes) === 1 ? 'message_validate_batch_reference_and_date_1' : 'message_validate_batch_reference_and_date_2');
        } elseif ($this->submittedValues['invoice_source_reference_from'] !== '') {
            // Date from is empty, we go for a range of order ids.
            // (We ignore any date to value.)
            // Single id or range of ids?
            if ($this->submittedValues['invoice_source_reference_to'] !== '' && $this->submittedValues['invoice_source_reference_to'] < $this->submittedValues['invoice_source_reference_from']) {
                // order id to is smaller than order id from.
                $this->errorMessages['invoice_source_reference_to'] = $this->t('message_validate_batch_bad_order_range');
            }
        } else /*if ($this->submittedValues['date_to'] !== '') */ {
            // Range of dates has been filled in.
            // We ignore any order # to value.
            if (!DateTime::createFromFormat(static::DateFormat, $this->submittedValues['date_from'])) {
                // Date from not a valid date.
                $this->errorMessages['date_from'] = sprintf($this->t('message_validate_batch_bad_date_from'), $this->t('date_format'));
            }
            if ($this->submittedValues['date_to']) {
                if (!DateTime::createFromFormat(static::DateFormat, $this->submittedValues['date_to'])) {
                    // Date to not a valid date.
                    $this->errorMessages['date_to'] = sprintf($this->t('message_validate_batch_bad_date_to'), $this->t('date_format'));
                } elseif ($this->submittedValues['date_to'] < $this->submittedValues['date_from']) {
                    // date to is smaller than date from
                    $this->errorMessages['date_to'] = $this->t('message_validate_batch_bad_date_range');
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * Sends the invoices as defined by the form values to Acumulus.
     */
    protected function execute()
    {
        $type = $this->getFormValue('invoice_source_type');
        if ($this->getFormValue('invoice_source_reference_from') !== '') {
            // Retrieve by order reference range.
            $from = $this->getFormValue('invoice_source_reference_from');
            $to = $this->getFormValue('invoice_source_reference_to') ? $this->getFormValue('invoice_source_reference_to') : $from;
            $this->log['range'] = sprintf($this->t('message_form_range_reference'), $this->t("plural_{$type}_ref"), $from, $to);
            $invoiceSources = $this->invoiceManager->getInvoiceSourcesByReferenceRange($type, $from, $to);
            if (empty($invoiceSources)) {
                $invoiceSources = $this->invoiceManager->getInvoiceSourcesByIdRange($type, $from, $to);
                $this->log['range'] = sprintf($this->t('message_form_range_reference'), $this->t("plural_{$type}_id"), $from, $to);
            }
        } else {
            // Retrieve by order date.
            $from = DateTime::createFromFormat(static::DateFormat, $this->getFormValue('date_from'));
            $from->setTime(0, 0, 0);
            $to = $this->getFormValue('date_to') ? DateTime::createFromFormat(static::DateFormat, $this->getFormValue('date_to')) : clone $from;
            $to->setTime(23, 59, 59);
            $this->log['range'] = sprintf($this->t('message_form_range_date'), $this->t("plural_$type"), $from->format((static::DateFormat)), $to->format(static::DateFormat));
            $invoiceSources = $this->invoiceManager->getInvoiceSourcesByDateRange($type, $from, $to);
        }

        if (count($invoiceSources) === 0) {
            $this->log[$type] = sprintf($this->t('message_form_range_empty'), $this->t($type));
            $this->setFormValue('result', $this->log[$type]);
            $result = true;
        } else {
            $sendMode = $this->getFormValue('send_mode');
            if ($sendMode === 'send_test_mode') {
                // Overrule debug setting for (the rest of) this run.
                $this->acumulusConfig->set('debug', PluginConfig::Send_TestMode);
            }
            $result = $this->invoiceManager->sendMultiple($invoiceSources, $sendMode === 'send_force', (bool) $this->getFormValue('dry_run'), $this->log);
        }

        // Set formValue for log in case form values are already queried.
        $logText = implode("\n", $this->log);
        $this->setFormValue('log', $logText);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDefinitions()
    {
        $fields = array();

        $invoiceSourceTypes = $this->shopCapabilities->getSupportedInvoiceSourceTypes();
        if (count($invoiceSourceTypes) === 1) {
            // Make it a hidden field.
            $invoiceSourceTypeField = array(
                'type' => 'hidden',
                'value' => key($invoiceSourceTypes),
            );
        } else {
            $invoiceSourceTypeField = array(
                'type' => 'radio',
                'label' => $this->t('field_invoice_source_type'),
                'options' => $invoiceSourceTypes,
                'attributes' => array(
                    'required' => true,
                ),
            );
        }
        // 1st fieldset: Batch options.
        $fields['batchFieldsHeader'] = array(
            'type' => 'fieldset',
            'legend' => $this->t('batchFieldsHeader'),
            'fields' => array(
                'invoice_source_type' => $invoiceSourceTypeField,
                'invoice_source_reference_from' => array(
                    'type' => 'text',
                    'label' => $this->t('field_invoice_source_reference_from'),
                ),
                'invoice_source_reference_to' => array(
                    'type' => 'text',
                    'label' => $this->t('field_invoice_source_reference_to'),
                    'description' => count($invoiceSourceTypes) === 1 ? $this->t('desc_invoice_source_reference_from_to_1') : $this->t('desc_invoice_source_reference_from_to_2'),
                ),
                'date_from' => array(
                    'type' => 'date',
                    'label' => $this->t('field_date_from'),
                    'attributes' => array(
                        'placeholder' => $this->t('date_format'),
                    ),
                ),
                'date_to' => array(
                    'type' => 'date',
                    'label' => $this->t('field_date_to'),
                    'attributes' => array(
                        'placeholder' => $this->t('date_format'),
                    ),
                    'description' => $this->t('desc_date_from_to'),
                ),
                'send_mode' => array(
                    'type' => 'radio',
                    'label' => $this->t('field_send_mode'),
                    'description' => $this->t('desc_send_mode'),
                    'attributes' => array(
                        'required' => true,
                    ),
                    'options' => array(
                        'send_normal' => $this->t('option_send_normal'),
                        'send_force' => $this->t('option_send_force'),
                        'send_test_mode' => $this->t('option_send_test_mode'),
                    ),
                ),
                'dry_run' => array(
                    'type' => 'checkbox',
                    'label' => $this->t('field_dry_run'),
                    'description' => $this->t('desc_dry_run'),
                    'options' => array(
                        'dry_run' => $this->t('option_dry_run'),
                    ),
                ),
            ),
        );

        // 2nd fieldset: Batch log.
        if ($this->isSubmitted() && !empty($this->submittedValues) && $this->isValid()) {
            // Set formValue for log as value in case form values are not yet queried.
            $fields['batchLogHeader'] = array(
                'type' => 'fieldset',
                'legend' => $this->t('batchLogHeader'),
                'fields' => array(
                    'log' => array(
                        'type' => 'textarea',
                        'attributes' => array(
                            'readonly' => true,
                            'rows' => max(5, min(10, count($this->log))),
                            'style' => 'box-sizing: border-box; width: 100%; min-width: 48em;',
                        ),
                    ),
                ),
            );
            if (!empty($this->log)) {
                $logText = implode("\n", $this->log);
                $this->formValues['log'] = $logText;
                $fields['batchLogHeader']['fields']['log']['value'] = $logText;
            }
        }

        // 3rd fieldset: Batch info.
        $fields['batchInfoHeader'] = array(
            'type' => 'fieldset',
            'legend' => $this->t('batchInfoHeader'),
            'fields' => array(
                'info' => array(
                    'type' => 'markup',
                    'value' => $this->t('batch_info'),
                    'attributes' => array(
                        'readonly' => true,
                    ),
                ),
            ),
        );

        return $fields;
    }
}
