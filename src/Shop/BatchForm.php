<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTimeImmutable;
use Siel\Acumulus\Api;
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
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;

use function array_key_exists;
use function count;

/**
 * Provides batch form handling.
 *
 * Shop specific overrides should - of course - implement the abstract method:
 * - none
 * Should typically override:
 * - none
 * And may optionally (have to) override:
 * - setSubmittedValues()
 *
 * @todo: an additional filter (AND with other filters) on status is absolutely necessary
 * @nth: to prevent problems, add an additional confirmation step, including a list of
 *   sources that will be sent (with check boxes?).
 */
class BatchForm extends Form
{
    protected InvoiceManager $invoiceManager;
    /** @var string[] */
    protected array $screenLog;

    public function __construct(
        AboutForm $aboutForm,
        InvoiceManager $invoiceManager,
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

        $translations = new InvoiceTranslations();
        $this->translator->add($translations);

        $translations = new BatchFormTranslations();
        $this->translator->add($translations);

        $this->screenLog = [];
        $this->invoiceManager = $invoiceManager;
    }

    /**
     * {@inheritdoc}
     *
     * This override adds the log messages from the $log property to the log
     * field.
     */
    protected function getDefaultFormValues(): array
    {
        $result = parent::getDefaultFormValues();
        $result['send_mode'] = 'send_normal';
        if (count($this->screenLog) !== 0) {
            $result['log'] = implode("\n", $this->screenLog);
        }
        return $result;
    }

    protected function setSubmittedValues(): void
    {
        parent::setSubmittedValues();
        // Trim the from-to fields, can e.g. be copied from the order list,
        // and therefore contain spaces or tabs before or after the value.
        $this->submittedValues['invoice_source_reference_from'] = trim($this->submittedValues['invoice_source_reference_from']);
        $this->submittedValues['invoice_source_reference_to'] = trim($this->submittedValues['invoice_source_reference_to']);
        $this->submittedValues['date_from'] = trim($this->submittedValues['date_from']);
        $this->submittedValues['date_to'] = trim($this->submittedValues['date_to']);
    }

    protected function validate(): void
    {
        $invoiceSourceTypes = $this->shopCapabilities->getSupportedInvoiceSourceTypes();
        if (empty($this->submittedValues['invoice_source_type'])) {
            $this->addFormMessage($this->t('message_validate_batch_source_type_required'), Severity::Error, 'invoice_source_type');
        } elseif (!array_key_exists($this->submittedValues['invoice_source_type'], $invoiceSourceTypes)) {
            $this->addFormMessage($this->t('message_validate_batch_source_type_invalid'), Severity::Error, 'invoice_source_type');
        }

        if ($this->submittedValues['invoice_source_reference_from'] === '' && $this->submittedValues['date_from'] === '') {
            // Either a range of order id's or a range of dates should be entered.
            $this->addFormMessage(
                $this->t(
                    count($invoiceSourceTypes) === 1
                        ? 'message_validate_batch_reference_or_date_1'
                        : 'message_validate_batch_reference_or_date_2'
                ),
                Severity::Error,
                'invoice_source_reference_from'
            );
        } elseif ($this->submittedValues['invoice_source_reference_from'] !== '' && $this->submittedValues['date_from'] !== '') {
            // Not both ranges should be entered.
            $this->addFormMessage(
                $this->t(
                    count($invoiceSourceTypes) === 1
                        ? 'message_validate_batch_reference_and_date_1'
                        : 'message_validate_batch_reference_and_date_2'
                ),
                Severity::Error,
                'date_from'
            );
        } elseif ($this->submittedValues['invoice_source_reference_from'] !== '') {
            // Date from is empty, we go for a range of order ids.
            // (We ignore any date to value.)
            // Single id or range of ids?
            if ($this->submittedValues['invoice_source_reference_to'] !== ''
                && $this->submittedValues['invoice_source_reference_to'] < $this->submittedValues['invoice_source_reference_from']) {
                // "order id to" is smaller than "order id from".
                $this->addFormMessage($this->t('message_validate_batch_bad_order_range'), Severity::Error, 'invoice_source_reference_to');
            }
        } else /*if ($this->submittedValues['date_to'] !== '') */ {
            // Range of dates has been filled in.
            // We ignore any order # to value.
            if (!DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->submittedValues['date_from'])) {
                // Date from not a valid date.
                $this->addFormMessage(
                    sprintf($this->t('message_validate_batch_bad_date_from'), $this->t('date_format')),
                    Severity::Error,
                    'date_from'
                );
            }
            if ($this->submittedValues['date_to']) {
                if (!DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->submittedValues['date_to'])) {
                    // Date to not a valid date.
                    $this->addFormMessage(
                        sprintf($this->t('message_validate_batch_bad_date_to'), $this->t('date_format')),
                        Severity::Error,
                        'date_to'
                    );
                } elseif ($this->submittedValues['date_to'] < $this->submittedValues['date_from']) {
                    // date to is smaller than date from
                    $this->addFormMessage(
                        $this->t('message_validate_batch_bad_date_range'),
                        Severity::Error,
                        'date_to'
                    );
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * Sends the invoices as defined by the form values to Acumulus.
     */
    protected function execute(): bool
    {
        $type = (string) $this->getFormValue('invoice_source_type');
        if ($this->getFormValue('invoice_source_reference_from') !== '') {
            // Retrieve by order/refund reference range.
            $from = $this->getFormValue('invoice_source_reference_from');
            $to = $this->getFormValue('invoice_source_reference_to') ?: $from;
            $this->screenLog['range'] = sprintf($this->t('message_form_range_reference'), $this->t("plural_{$type}_ref"), $from, $to);
            $invoiceSources = $this->invoiceManager->getInvoiceSourcesByReferenceRange($type, $from, $to);
            if (count($invoiceSources) === 0) {
                // Empty set when searching on references: retrieve by order/
                // refund id range.
                $invoiceSources = $this->invoiceManager->getInvoiceSourcesByIdRange($type, (int) $from, (int) $to);
                $this->screenLog['range'] = sprintf($this->t('message_form_range_reference'), $this->t("plural_{$type}_id"), $from, $to);
            }
        } else {
            // Retrieve by order date.
            $from = DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->getFormValue('date_from'))->setTime(0, 0, 0);
            /** @noinspection PhpRedundantOptionalArgumentInspection */
            $to = $this->getFormValue('date_to')
                ? DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->getFormValue('date_to'))->setTime(23, 59, 59)
                : clone $from;
            $this->screenLog['range'] = sprintf(
                $this->t('message_form_range_date'),
                $this->t("plural_$type"),
                $from->format((Api::DateFormat_Iso)),
                $to->format(Api::DateFormat_Iso)
            );
            $invoiceSources = $this->invoiceManager->getInvoiceSourcesByDateRange($type, $from, $to);
        }

        if (count($invoiceSources) === 0) {
            $rangeList = sprintf($this->t('message_form_range_empty'), $this->t($type));
            $this->screenLog[$type] = $rangeList;
            $this->addFormMessage($rangeList, Severity::Warning, 'invoice_source_reference_from');
            $this->setFormValue('result', $this->screenLog[$type]);
            $this->log->info('BatchForm::execute(): ' . $this->screenLog['range'] . $rangeList);
            $result = true;
        } else {
            $rangeList = sprintf($this->t('message_form_range_list'), $this->getInvoiceSourceReferenceList($invoiceSources));
            $sendMode = $this->getFormValue('send_mode');
            if ($sendMode === 'send_test_mode') {
                // Overrule debug setting for (the rest of) this run.
                $this->acumulusConfig->set('debug', Config::Send_TestMode);
            }
            // Do the sending (and some info/debug logging).
            $this->log->info('BatchForm::execute(): ' . $this->screenLog['range'] . ' ' . $rangeList);
            $result = $this->invoiceManager->sendMultiple(
                $invoiceSources,
                $sendMode === 'send_force',
                (bool) $this->getFormValue('dry_run'),
                $this->screenLog
            );
            $plural = count($invoiceSources) > 1 ? 'plural_' : '';
            $translatedType = $this->t($plural . $type);
            $translatedIs = $this->t($plural . 'is');
            $message = sprintf($this->t('message_form_range_success'), $translatedType, $translatedIs, count($invoiceSources));
            $this->createAndAddMessage($message, Severity::Success);
        }

        // Set formValue for log in case form values are already queried.
        $logText = implode("\n", $this->screenLog);
        $this->setFormValue('log', $logText);
        return $result;
    }

    protected function getFieldDefinitions(): array
    {
        $fields = [];

        $invoiceSourceTypes = $this->shopCapabilities->getSupportedInvoiceSourceTypes();
        if (count($invoiceSourceTypes) === 1) {
            // Make it a hidden field.
            $invoiceSourceTypeField = [
                'type' => 'hidden',
                'value' => key($invoiceSourceTypes),
            ];
        } else {
            $invoiceSourceTypeField = [
                'type' => 'radio',
                'label' => $this->t('field_invoice_source_type'),
                'options' => $invoiceSourceTypes,
                'attributes' => [
                    'required' => true,
                ],
            ];
        }
        // 1st fieldset: Batch options.
        $fields['batchFields'] = [
            'type' => 'fieldset',
            'legend' => $this->t('batchFieldsHeader'),
            'fields' => [
                'invoice_source_type' => $invoiceSourceTypeField,
                'invoice_source_reference_from' => [
                    'type' => 'text',
                    'label' => $this->t('field_invoice_source_reference_from'),
                ],
                'invoice_source_reference_to' => [
                    'type' => 'text',
                    'label' => $this->t('field_invoice_source_reference_to'),
                    'description' => count($invoiceSourceTypes) === 1 ? $this->t('desc_invoice_source_reference_from_to_1') : $this->t(
                        'desc_invoice_source_reference_from_to_2'
                    ),
                ],
                'date_from' => [
                    'type' => 'date',
                    'label' => $this->t('field_date_from'),
                    // Placeholder only shown by MA2.
                    'attributes' => [
                        'placeholder' => $this->t('date_format'),
                    ],
                ],
                'date_to' => [
                    'type' => 'date',
                    'label' => $this->t('field_date_to'),
                    'attributes' => [
                        'placeholder' => $this->t('date_format'),
                    ],
                    'description' => $this->t('desc_date_from_to'),
                ],
                'send_mode' => [
                    'type' => 'radio',
                    'label' => $this->t('field_send_mode'),
                    'description' => $this->t('desc_send_mode'),
                    'attributes' => [
                        'required' => true,
                    ],
                    'options' => [
                        'send_normal' => $this->t('option_send_normal'),
                        'send_force' => $this->t('option_send_force'),
                        'send_test_mode' => $this->t('option_send_test_mode'),
                    ],
                ],
                'dry_run_cb' => [
                    'type' => 'checkbox',
                    'label' => $this->t('field_dry_run'),
                    'description' => $this->t('desc_dry_run'),
                    'options' => [
                        'dry_run' => $this->t('option_dry_run'),
                    ],
                ],
            ],
        ];

        // 2nd fieldset: Batch log.
        if ($this->isSubmitted() && count($this->submittedValues) !== 0 && $this->isValid()) {
            // Set formValue for log as value in case form values are not yet queried.
            $fields['batchLog'] = [
                'type' => 'fieldset',
                'legend' => $this->t('batchLogHeader'),
                'fields' => [
                    'log' => [
                        'type' => 'textarea',
                        'attributes' => [
                            'readonly' => true,
                            'rows' => max(5, min(15, count($this->screenLog))),
                            'style' => 'box-sizing: border-box; width: 100%; min-width: 48em;',
                        ],
                    ],
                ],
            ];
            if (count($this->screenLog) !== 0) {
                $logText = implode("\n", $this->screenLog);
                $this->formValues['log'] = $logText;
                $fields['batchLog']['fields']['log']['value'] = $logText;
            }
        }

        // 3rd fieldset: Batch info.
        $fields['batchInfo'] = [
            'type' => 'details',
            'summary' => $this->t('batchInfoHeader'),
            'fields' => [
                'info' => [
                    'type' => 'markup',
                    'value' => $this->t('batch_info'),
                    'attributes' => [
                        'readonly' => true,
                    ],
                ],
            ],
        ];

        // 4th fieldset: More Acumulus.
        $message = $this->checkAccountSettings();
        $accountStatus = $this->emptyCredentials() ? null : empty($message);
        $fields['versionInformation'] = $this->getAboutBlock($accountStatus);

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
    protected function getInvoiceSourceReferenceList(array $invoiceSources): string
    {
        $result = [];
        foreach ($invoiceSources as $invoiceSource) {
            $result[] = $invoiceSource->getReference();
        }
        return '{' . implode(',', $result) . '}';
    }
}
