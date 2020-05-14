<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
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
 * - setSubmittedValues()
 */
class BatchForm extends Form
{
    /** @var \Siel\Acumulus\Shop\InvoiceManager */
    protected $invoiceManager;

    /** @var string[] */
    protected $screenLog;

    /**
     * @param \Siel\Acumulus\Shop\InvoiceManager $invoiceManager
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(
        InvoiceManager $invoiceManager,
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Translator $translator,
        Log $log
    )
    {
        parent::__construct($formHelper, $shopCapabilities, $config, $translator, $log);

        $translations = new InvoiceTranslations();
        $this->translator->add($translations);

        $translations = new BatchFormTranslations();
        $this->translator->add($translations);

        $this->screenLog = array();
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
        if (!empty($this->screenLog)) {
            $result['log'] = implode("\n", $this->screenLog);
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
            $this->addMessage($this->t('message_validate_batch_source_type_required'), Severity::Error, 'invoice_source_type');
        } elseif (!array_key_exists($this->submittedValues['invoice_source_type'], $invoiceSourceTypes)) {
            $this->addMessage($this->t('message_validate_batch_source_type_invalid'), Severity::Error, 'invoice_source_type');
        }

        if ($this->submittedValues['invoice_source_reference_from'] === '' && $this->submittedValues['date_from'] === '') {
            // Either a range of order id's or a range of dates should be entered.
            $this->addMessage(
                $this->t(count($invoiceSourceTypes) === 1 ? 'message_validate_batch_reference_or_date_1' : 'message_validate_batch_reference_or_date_2'),
                Severity::Error,
                'invoice_source_reference_from');
        } elseif ($this->submittedValues['invoice_source_reference_from'] !== '' && $this->submittedValues['date_from'] !== '') {
            // Not both ranges should be entered.
            $this->addMessage(
                $this->t(count($invoiceSourceTypes) === 1 ? 'message_validate_batch_reference_and_date_1' : 'message_validate_batch_reference_and_date_2'),
                Severity::Error,
                'date_from');
        } elseif ($this->submittedValues['invoice_source_reference_from'] !== '') {
            // Date from is empty, we go for a range of order ids.
            // (We ignore any date to value.)
            // Single id or range of ids?
            if ($this->submittedValues['invoice_source_reference_to'] !== ''
                && $this->submittedValues['invoice_source_reference_to'] < $this->submittedValues['invoice_source_reference_from']) {
                // order id to is smaller than order id from.
                $this->addMessage($this->t('message_validate_batch_bad_order_range'), Severity::Error, 'invoice_source_reference_to');
            }
        } else /*if ($this->submittedValues['date_to'] !== '') */ {
            // Range of dates has been filled in.
            // We ignore any order # to value.
            if (!DateTime::createFromFormat(API::DateFormat_Iso, $this->submittedValues['date_from'])) {
                // Date from not a valid date.
                $this->addMessage(sprintf($this->t('message_validate_batch_bad_date_from'), $this->t('date_format')),
                    Severity::Error, 'date_from');
            }
            if ($this->submittedValues['date_to']) {
                if (!DateTime::createFromFormat(API::DateFormat_Iso, $this->submittedValues['date_to'])) {
                    // Date to not a valid date.
                    $this->addMessage(sprintf($this->t('message_validate_batch_bad_date_to'), $this->t('date_format')),
                    Severity::Error, 'date_to');
                } elseif ($this->submittedValues['date_to'] < $this->submittedValues['date_from']) {
                    // date to is smaller than date from
                    $this->addMessage($this->t('message_validate_batch_bad_date_range'),
                    Severity::Error, 'date_to');
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
            // Retrieve by order/refund reference range.
            $from = $this->getFormValue('invoice_source_reference_from');
            $to = $this->getFormValue('invoice_source_reference_to') ? $this->getFormValue('invoice_source_reference_to') : $from;
            $this->screenLog['range'] = sprintf($this->t('message_form_range_reference'), $this->t("plural_{$type}_ref"), $from, $to);
            $invoiceSources = $this->invoiceManager->getInvoiceSourcesByReferenceRange($type, $from, $to);
            if (empty($invoiceSources)) {
                // Empty set when searching on references: retrieve by order/
                // refund id range.
                $invoiceSources = $this->invoiceManager->getInvoiceSourcesByIdRange($type, $from, $to);
                $this->screenLog['range'] = sprintf($this->t('message_form_range_reference'), $this->t("plural_{$type}_id"), $from, $to);
            }
        } else {
            // Retrieve by order date.
            $from = DateTime::createFromFormat(API::DateFormat_Iso, $this->getFormValue('date_from'));
            $from->setTime(0, 0, 0);
            $to = $this->getFormValue('date_to') ? DateTime::createFromFormat(API::DateFormat_Iso, $this->getFormValue('date_to')) : clone $from;
            $to->setTime(23, 59, 59);
            $this->screenLog['range'] = sprintf($this->t('message_form_range_date'), $this->t("plural_$type"), $from->format((API::DateFormat_Iso)), $to->format(API::DateFormat_Iso));
            $invoiceSources = $this->invoiceManager->getInvoiceSourcesByDateRange($type, $from, $to);
        }

        if (count($invoiceSources) === 0) {
            $rangeList = sprintf($this->t('message_form_range_empty'), $this->t($type));
            $this->screenLog[$type] = $rangeList;
            $this->setFormValue('result', $this->screenLog[$type]);
            $this->log->info('BatchForm::execute(): ' . $this->screenLog['range'] . $rangeList);
            $result = true;
        } else {
            $rangeList = sprintf($this->t('message_form_range_list'), $this->getInvoiceSourceReferenceList($invoiceSources));
            $sendMode = $this->getFormValue('send_mode');
            if ($sendMode === 'send_test_mode') {
                // Overrule debug setting for (the rest of) this run.
                $this->acumulusConfig->set('debug', PluginConfig::Send_TestMode);
            }
            // Do the sending (and some info/debug logging).
            $this->log->info('BatchForm::execute(): ' . $this->screenLog['range'] . ' ' . $rangeList);
            $result = $this->invoiceManager->sendMultiple($invoiceSources, $sendMode === 'send_force', (bool) $this->getFormValue('dry_run'), $this->screenLog);
        }

        // Set formValue for log in case form values are already queried.
        $logText = implode("\n", $this->screenLog);
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
                'dry_run_cb' => array(
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
                            'rows' => max(5, min(10, count($this->screenLog))),
                            'style' => 'box-sizing: border-box; width: 100%; min-width: 48em;',
                        ),
                    ),
                ),
            );
            if (!empty($this->screenLog)) {
                $logText = implode("\n", $this->screenLog);
                $this->formValues['log'] = $logText;
                $fields['batchLogHeader']['fields']['log']['value'] = $logText;
            }
        }

        // 3rd fieldset: Batch info.
        $fields['batchInfoHeader'] = array(
            'type' => 'details',
            'summary' => $this->t('batchInfoHeader'),
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

    /**
     * Returns a formatted string with the list of ids of the given sources.
     *
     * @param \Siel\Acumulus\Invoice\Source[] $invoiceSources
     *
     * @return string
     *   A loggable (formatted) string with a list of ids of the sources.
     */
    protected function getInvoiceSourceReferenceList(array $invoiceSources)
    {
        $result = array();
        foreach ($invoiceSources as $invoiceSource) {
            $result[] = $invoiceSource->getReference();
        }
        return '{' . implode(',', $result) . '}';
    }
}
