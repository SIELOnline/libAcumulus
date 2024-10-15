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
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;

use function array_key_exists;
use function count;
use function in_array;
use function sprintf;

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
        $result['order_statuses'] = $this->acumulusConfig->get('triggerOrderStatus');
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
        $this->submittedValues['reference_from'] = trim($this->submittedValues['reference_from']);
        $this->submittedValues['reference_to'] = trim($this->submittedValues['reference_to']);
        $this->submittedValues['date_from'] = trim($this->submittedValues['date_from']);
        $this->submittedValues['date_to'] = trim($this->submittedValues['date_to']);
        if (!isset($this->submittedValues['order_statuses'])) {
            $this->submittedValues['order_statuses'] = ['0'];
        }
    }

    protected function validate(): void
    {
        $invoiceSourceTypes = $this->shopCapabilities->getSupportedInvoiceSourceTypes();
        if (empty($this->submittedValues['source_type'])) {
            $this->addFormMessage($this->t('message_validate_batch_source_type_required'), Severity::Error, 'source_type');
        } elseif (!array_key_exists($this->submittedValues['source_type'], $invoiceSourceTypes)) {
            $this->addFormMessage($this->t('message_validate_batch_source_type_invalid'), Severity::Error, 'source_type');
        }

        if ($this->submittedValues['reference_from'] === '' && $this->submittedValues['date_from'] === '') {
            // Either a range of order id's or a range of dates should be entered.
            $message = count($invoiceSourceTypes) === 1
                ? 'message_validate_batch_reference_or_date_1'
                : 'message_validate_batch_reference_or_date_2';
            $this->addFormMessage(
                $this->t($message),
                Severity::Error,
                'reference_from'
            );
        }

        if ($this->submittedValues['reference_from'] !== '') {
            // Single id or range of ids?
            if ($this->submittedValues['reference_to'] !== ''
                && $this->submittedValues['reference_to'] < $this->submittedValues['reference_from']) {
                // "order id to" is smaller than "order id from": we could swap the values
                // ourselves, but this may be a typing error: do not accept it.
                $this->addFormMessage($this->t('message_validate_batch_bad_order_range'), Severity::Error, 'reference_to');
            }
        }

        if ($this->submittedValues['date_from'] !== '') {
            if (!DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->submittedValues['date_from'])) {
                // Date from not a valid date.
                $this->addFormMessage(
                    sprintf($this->t('message_validate_batch_bad_date_from'), $this->t('date_format')),
                    Severity::Error,
                    'date_from'
                );
            }
            // Single date or range of dates?
            if ($this->submittedValues['date_to'] !== '') {
                // Range of dates has been filled in.
                if (!DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->submittedValues['date_to'])) {
                    // Date to not a valid date.
                    $this->addFormMessage(
                        sprintf($this->t('message_validate_batch_bad_date_to'), $this->t('date_format')),
                        Severity::Error,
                        'date_to'
                    );
                } elseif ($this->submittedValues['date_to'] < $this->submittedValues['date_from']) {
                    // date to is smaller than date from: we could swap the values
                    // ourselves, but this may be a typing error: do not accept it.
                    $this->addFormMessage($this->t('message_validate_batch_bad_date_range'), Severity::Error, 'date_to');
                }
            }
        }

        if (count($this->submittedValues['order_statuses']) > 0) {
            //  '0' option may not be combined with others
            if (in_array('0', $this->submittedValues['order_statuses'], true) && count($this->submittedValues['order_statuses']) >= 2) {
                // the "0" option ("do not filter on order status") is combined with other
                // real order statuses: do not accept it.
                $this->addFormMessage(
                    sprintf($this->t('message_validate_batch_order_status_0_not_alone'), $this->t('option_empty_order_statuses')),
                    Severity::Error,
                    'order_status'
                );
            }
            if (!in_array('0', $this->submittedValues['order_statuses'], true)) {
                $existing = array_intersect_key(
                    $this->submittedValues['order_statuses'],
                    array_keys($this->shopCapabilities->getShopOrderStatuses())
                );
                if (count($existing) !== count($this->submittedValues['order_statuses'])) {
                    // tampering:
                    $this->addFormMessage($this->t('message_validate_batch_order_status_existing'), Severity::Error, 'order_status');
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
        $type = (string) $this->getFormValue('source_type');
        $filters = [];
        $this->screenLog['filters'] = [];
        $this->screenLog['filters'][] = sprintf($this->t('message_form_filter_type'), $this->t("plural_$type"));
        if ($this->getFormValue('reference_from') !== '') {
            // Retrieve by order/refund reference range.
            $from = $this->getFormValue('reference_from');
            $to = $this->getFormValue('reference_to') ?: $from;
            $filters[] = ['reference_from' => $from, 'reference_to' => $to];
            $this->screenLog['filters'][] = sprintf($this->t('message_form_filter_reference'), $from, $to);
        }
        if ($this->getFormValue('date_from') !== '') {
            // Retrieve by order date.
            $from = DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->getFormValue('date_from'))->setTime(0, 0, 0);
            $to = $this->getFormValue('date_to')
                ? DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, $this->getFormValue('date_to'))->setTime(23, 59, 59)
                : clone $from;
            $filters[] = ['date_from' => $from, 'date_to' => $to];
            $this->screenLog['filters'][] = sprintf(
                $this->t('message_form_filter_date'),
                $from->format((Api::DateFormat_Iso)),
                $to->format(Api::DateFormat_Iso)
            );
        }

        if ($type === Source::Order) {
            $statuses = array_intersect_key(
                $this->getOrderStatusesList('option_empty_order_statuses'),
                array_fill_keys($this->getFormValue('order_statuses'), true)
            );
            unset($statuses['0']);
            if (count($statuses) > 0) {
                $filters[] = ['statuses' => $statuses];
                $this->screenLog['filters'][] = sprintf($this->t('message_form_filter_status'), implode("', '", $statuses));
            }
        }

        // @todo: can we indicate whether we filtered on references or ids? if so, use this in messages and logging.
        $invoiceSources = $this->invoiceManager->getInvoiceSourcesByFilters($type, $filters);

        if (count($invoiceSources) === 0) {
            $rangeList = sprintf($this->t('message_form_range_empty'), $this->t($type));
            $this->screenLog[$type] = $rangeList;
            $this->addFormMessage($rangeList, Severity::Warning, 'reference_from');
            $this->setFormValue('result', $this->screenLog[$type]);
            $this->log->info('BatchForm::execute(): ' . $this->getFiltersMessage() . $rangeList);
            $result = true;
        } else {
            $rangeList = sprintf($this->t('message_form_range_list'), $this->getInvoiceSourceReferenceList($invoiceSources));
            $sendMode = $this->getFormValue('send_mode');
            if ($sendMode === 'send_test_mode') {
                // Overrule debug setting for (the rest of) this run.
                $this->acumulusConfig->set('debug', Config::Send_TestMode);
            }
            // Do the sending (and some info/debug logging).
            $this->log->info('BatchForm::execute(): ' . $this->getFiltersMessage() . ' ' . $rangeList);
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
        $logText = $this->screenLogToString();
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
        $orderStatusesList = $this->getOrderStatusesList('option_empty_order_statuses');

        $fields['batchFields'] = [
            'type' => 'fieldset',
            'legend' => $this->t('batchFieldsHeader'),
            'fields' => [
                'source_type' => $invoiceSourceTypeField,
                'reference_from' => [
                    'type' => 'text',
                    'label' => $this->t('field_invoice_source_reference_from'),
                ],
                'reference_to' => [
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
                'order_statuses' => [
                    'name' => 'order_statuses[]',
                    'type' => 'select',
                    'label' => $this->t('field_order_statuses'),
                    'description' => $this->t('desc_order_statuses'),
                    'options' => $orderStatusesList,
                    'attributes' => [
                        'multiple' => true,
                        'size' => min(count($orderStatusesList), 8),
                    ],
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
                            'rows' => max(5, min(15, count($this->screenLog) + 1)),
                            'style' => 'box-sizing: border-box; width: 100%; min-width: 48em;',
                        ],
                    ],
                ],
            ];
            if (count($this->screenLog) > 0) {
                $logText = $this->screenLogToString();
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

    protected function getInvoiceSourceReferenceList(array $sources): string
    {
        $result = array_map(static function ($invoiceSource) {
            return $invoiceSource->getReference();
        }, $sources);
        return implode(', ', $result);
    }

    private function getFiltersMessage(bool $inline = true): string
    {
        $separatorHeader = $inline ? ' ' : "\n • ";
        $separatorLine = $inline ? ', ' : "\n • ";
        $header = $this->t('message_form_filter');
        $lines = implode($separatorLine, $this->screenLog['filters']);
        return "$header$separatorHeader$lines";
    }

    private function screenLogToString(): string
    {
        $result = $this->getFiltersMessage(false) . "\n";
        $filters = $this->screenLog['filters'];
        unset($this->screenLog['filters']);
        $result .= implode("\n", $this->screenLog);
        array_unshift($this->screenLog, $filters);
        return $result;
    }
}
