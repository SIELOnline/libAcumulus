<?php
/**
 * @noinspection PhpLackOfCohesionInspection
 * @noinspection EfferentObjectCouplingInspection
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpConcatenationWithEmptyStringCanBeInlinedInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use DateTime;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Helpers\Severity;

use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * Defines the Acumulus invoice status overview form.
 *
 * This form is mostly informative but contains some buttons and a few fields
 * to update the invoice in Acumulus.
 *
 * SECURITY REMARKS
 * ----------------
 * The info received from an external API call should not be trusted, so it
 * should be sanitised. As most info from this API call is placed in markup
 * fields we cannot rely on the FormRenderer or the web shop's form API, who do
 * not sanitise markup fields. so we do this in this form.
 *
 * This form uses ajax calls, values received from an ajax call are to be
 * treated as user input and thus should be sanitised and checked as all user
 * input.
 */
class InvoiceStatusForm extends Form
{
    // Constants representing the status of the Acumulus invoice for a given
    // shop order or refund.
    public const Invoice_NotSent = 'invoice_not_sent';
    public const Invoice_Sent = 'invoice_sent';
    public const Invoice_SentConcept = 'invoice_sent_concept';
    public const Invoice_SentConceptNoInvoice = 'invoice_sent_concept_no_invoice';
    public const Invoice_Deleted = 'invoice_deleted';
    public const Invoice_NonExisting = 'invoice_non_existing';
    public const Invoice_CommunicationError = 'invoice_communication_error';
    public const Invoice_LocalError = 'invoice_local_error';

    public const Status_Unknown = 0;
    public const Status_Success = 1;
    public const Status_Info = 2;
    public const Status_Warning = 3;
    public const Status_Error = 4;

    protected Container $container;
    protected InvoiceManager $invoiceManager;
    protected AcumulusEntryManager $acumulusEntryManager;
    /**
     * The main Source for this form.
     *
     * This form can handle an order and its credit notes at the same time, the
     * order being the "main" source.
     */
    protected ?Source $source = null;
    /**
     * The submitted source for this execution.
     *
     * This form can handle an order and its credit notes at the same time, the
     * order being the "main" source, the submitted source being the Source to
     * act on.
     */
    protected ?Source $submittedSource = null;
    /**
     * One of the Result::Status_... constants.
     */
    protected int $status;
    /**
     * A message indicating why the status is not OK.
     */
    protected string $statusMessage;

    public function __construct(
        InvoiceManager $invoiceManager,
        AcumulusEntryManager $acumulusEntryManager,
        Acumulus $acumulusApiClient,
        Container $container,
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Environment $environment,
        Translator $translator,
        Log $log
    ) {
        parent::__construct(
            $acumulusApiClient,
            $formHelper,
            $shopCapabilities,
            $config,
            $environment,
            $translator,
            $log
        );
        $this->addMeta = false;
        $this->isFullPage = false;

        $translations = new InvoiceTranslations();
        $this->translator->add($translations);

        $translations = new InvoiceStatusFormTranslations();
        $this->translator->add($translations);

        $this->container = $container;
        $this->acumulusEntryManager = $acumulusEntryManager;
        $this->invoiceManager = $invoiceManager;
        $this->resetStatus();
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }

    /**
     * @return bool
     *   Whether the form has a source set.
     *
     * @noinspection PhpUnused
     */
    public function hasSource(): bool
    {
        return $this->source !== null;
    }

    public function getSubmittedSource(): ?Source
    {
        return $this->submittedSource;
    }

    public function setSubmittedSource(?Source $submittedSource): void
    {
        $this->submittedSource = $submittedSource;
    }


    /**
     * Sets the status, but only if it is "worse" than the current status.
     *
     * @param int $status
     *   The status to set.
     * @param string $message
     *   Optionally, a message indicating what is wrong may be given.
     */
    protected function setStatus(int $status, string $message): void
    {
        if ($status > $this->status) {
            $this->status = $status;
            // Save the message belonging to this worse state.
            if (!empty($message)) {
                $this->statusMessage = $message;
            }
        }
    }

    /**
     * Resets the status.
     */
    protected function resetStatus(): void
    {
        $this->status = static::Status_Unknown;
        $this->statusMessage = '';
    }

    /**
     * Returns a string to use as css class for the current status.
     */
    public function getStatusClass(int $status): string
    {
        switch ($status) {
            case static::Status_Success:
                $result = 'success';
                break;
            case static::Status_Info:
                $result = 'info';
                break;
            case static::Status_Warning:
                $result = 'warning';
                break;
            case static::Status_Error:
            default:
                $result = 'error';
                break;
        }
        return $result;
    }

    /**
     * Returns an icon character that represents the current status.
     *
     * @return string
     *   An icon character that represents the status.
     */
    protected function getStatusIcon(int $status): string
    {
        switch ($status) {
            case static::Status_Success:
                // Heavy check mark: json_decode('"\u2714"')
                $result = '✔';
                break;
            case static::Status_Info:
            case static::Status_Warning:
                $result = '!';
                break;
            case static::Status_Error:
            default:
                // Heavy multiplication: \u2716
                $result = '✖';
                break;
        }
        return $result;
    }

    /**
     * Returns a set of label attributes for the current status.
     *
     * @return array
     *   A set of attributes to add to the label.
     */
    protected function getStatusLabelAttributes(int $status, string $statusMessage): array
    {
        $statusClass = $this->getStatusClass($status);
        $attributes = [
            'class' => ['notice', 'notice-' . $statusClass],
        ];
        if (!empty($statusMessage)) {
            $attributes['title'] = $statusMessage;
        }
        return $attributes;
    }

    /**
     * Returns a description of the amount status.
     *
     * @return string
     *   A description of the amount status.
     */
    protected function getAmountStatusTitle(int $status): string
    {
        $result = '';
        if ($status > static::Status_Success) {
            $result = $this->t('amount_status_' . $status);
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * This override handles the case that also the initial form load may be
     * done via ajax, thus being a post but not submitted.
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

        // Split the service, as it is prefixed with the source type and id, it
        // looks like "Type_id_service".
        $this->setServiceAndSubmittedSource();
    }

    /**
     * Extracts the source on which the submitted action is targeted.
     */
    protected function setServiceAndSubmittedSource(): void
    {
        // Get base source. The action may be on one of its children, a credit
        // note, but we also have to set the base source,so we can fully
        // process and render the form.
        $type = $this->getSubmittedValue('acumulus_main_source_type');
        $id = (int) $this->getSubmittedValue('acumulus_main_source_id');
        $mainSource = $this->container->createSource($type, $id);
        if ($mainSource->getSource()) {
            $this->setSource($mainSource);
        }

        // Get actual source ($this->source is the main source, not the source
        // to execute on). Do so without trusting the input.
        $parts = explode('_', $this->submittedValues['clicked'], 3);
        if (count($parts) === 3) {
            $this->submittedValues['service'] = $parts[2];
            $this->submittedValues['source_type'] = $parts[0];
            $this->submittedValues['source_id'] = $parts[1];
            $this->setSubmittedSource(null);
            if ($this->source->getType() === $this->getSubmittedValue('source_type')
                && $this->source->getId() === (int) $this->getSubmittedValue('source_id')
            ) {
                $this->setSubmittedSource($this->source);
            } else {
                $creditNotes = $this->source->getCreditNotes();
                foreach ($creditNotes as $creditNote) {
                    if ($creditNote->getType() === $this->getSubmittedValue('source_type')
                        && $creditNote->getId() === (int) $this->getSubmittedValue('source_id')) {
                        $this->setSubmittedSource($creditNote);
                    }
                }
            }
        } else {
            $this->submittedValues['service'] = '';
            $this->submittedValues['source_type'] = 'unknown';
            $this->submittedValues['source_id'] = 'unknown';
            $this->setSubmittedSource(null);
        }
    }

    /**
     * @inheritDoc
     */
    protected function validate(): void
    {
        if ($this->source === null) {
            // Use a basic filtering on the wrong user input.
            $this->addFormMessage(sprintf($this->t('unknown_source'),
                preg_replace('/[^a-z\d_\-]/', '', $this->getSubmittedValue('acumulus_main_source_type')),
                preg_replace('/[^a-z\d_\-]/', '', $this->getSubmittedValue('acumulus_main_source_id'))),
                Severity::Error);
        } elseif ($this->getSubmittedValue('service') !== 'invoice_show') {
            if ($this->getSubmittedSource() === null) {
                // Use a basic filtering on the wrong user input.
                $this->addFormMessage(sprintf($this->t('unknown_source'),
                    preg_replace('/[^a-z\d_\-]/', '', $this->getSubmittedValue('source_type')),
                    preg_replace('/[^a-z\d_\-]/', '', $this->getSubmittedValue('source_id'))),
                    Severity::Error);
            } elseif ($this->getSubmittedValue('service') === 'invoice_paymentstatus_set') {
                /** @var Source $source */
                $source = $this->getSubmittedSource();
                $idPrefix = $this->getIdPrefix($source);
                if ((int) $this->getSubmittedValue($idPrefix . 'payment_status_new') === Api::PaymentStatus_Paid) {
                    $dateFieldName = $idPrefix . 'payment_date';
                    if (!DateTime::createFromFormat(Api::DateFormat_Iso, $this->getSubmittedValue($dateFieldName))) {
                        // Date is not a valid date.
                        $this->addFormMessage(sprintf($this->t('message_validate_batch_bad_payment_date'), $this->t('date_format')),
                            Severity::Error,
                            $dateFieldName);
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * Performs the given action on the Acumulus invoice for the given Source.
     */
    protected function execute(): bool
    {
        $success = false;

        try {
            $service = $this->getSubmittedValue('service');
            /** @var Source $source */
            $source = $this->getSubmittedSource();
            $idPrefix = $this->getIdPrefix($source);
            switch ($service) {
                case 'invoice_show':
                    // Just show(/refresh) the form (Ajax based lazy load).
                    $success = true;
                    break;

                case 'invoice_add':
                    $forceSend = (bool) $this->getSubmittedValue($idPrefix . 'force_send');
                    $invoiceAddResult = $this->invoiceManager->send1($source, $forceSend);
                    $this->addMessages($invoiceAddResult->getMessages());
                    $success = !$invoiceAddResult->hasError();
                    break;

                case 'invoice_paymentstatus_set':
                    $localEntry = $this->acumulusEntryManager->getByInvoiceSource($source);
                    if ($localEntry) {
                        if ((int) $this->getSubmittedValue($idPrefix . 'payment_status_new') === Api::PaymentStatus_Paid) {
                            $paymentStatus = Api::PaymentStatus_Paid;
                            $paymentDate = $this->getSubmittedValue($idPrefix . 'payment_date');
                        } else {
                            $paymentStatus = Api::PaymentStatus_Due;
                            $paymentDate = '';
                        }
                        $acumulusResult = $this->acumulusApiClient->setPaymentStatus(
                            $localEntry->getToken(),
                            $paymentStatus,
                            $paymentDate
                        );
                        $this->addMessages($acumulusResult->getMessages());
                        $success = !$acumulusResult->hasError();
                    } else {
                        $this->createAndAddMessage(
                            sprintf($this->t('unknown_entry'), strtolower($this->t($source->getType())), $source->getId()),
                            Severity::Error
                        );
                    }
                    break;

                case 'invoice_mail':
                    $mailResult = $this->invoiceManager->emailInvoiceAsPdf($source);
                    $this->addMessages($mailResult->getMessages());
                    $success = !$mailResult->hasError();
                    break;

                case 'packing_slip_mail':
                    $mailResult = $this->invoiceManager->emailPackingSlipAsPdf($source);
                    $this->addMessages($mailResult->getMessages());
                    $success = !$mailResult->hasError();
                    break;

                case 'entry_deletestatus_set':
                    $localEntry = $this->acumulusEntryManager->getByInvoiceSource($source);
                    if ($localEntry && $localEntry->getEntryId() !== null) {
                        $deleteStatus = (int) $this->getSubmittedValue($idPrefix . 'delete_status') === Api::Entry_Delete
                            ? Api::Entry_Delete
                            : Api::Entry_UnDelete;
                        $acumulusResult = $this->acumulusApiClient->setDeleteStatus($localEntry->getEntryId(), $deleteStatus);
                        $this->addMessages($acumulusResult->getMessages());
                        $success = !$acumulusResult->hasError();
                    } else {
                        $this->createAndAddMessage(
                            sprintf($this->t('unknown_entry'), strtolower($this->t($source->getType())), $source->getId()),
                            Severity::Error
                        );
                    }
                    break;

                default:
                    // Use a basic filtering on the wrong user input.
                    $this->createAndAddMessage(
                        sprintf($this->t('unknown_action'), preg_replace('/[^a-z\d_\-]/', '', $service)),
                        Severity::Error
                    );
                    break;
            }
        } catch (RuntimeException $e) {
            $this->addException($e);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     * @noinspection InvertedIfElseConstructsInspection
     */
    protected function getFieldDefinitions(): array
    {
        $fields = [];
        $source = $this->source;

        // Add base information in hidden fields:
        // - Source (type and id) for the main source on this form. This will be
        //   an order. Field sets with children, credit notes, may follow.
        $fields['acumulus_main_source_type'] = $this->getHiddenField($source->getType());
        $fields['acumulus_main_source_id'] = $this->getHiddenField($source->getId());

        $idPrefix = $this->getIdPrefix($source);
        if (!$this->isSubmitted()) {
            // We are loading the form for the first time: just add a "show"
            // button to lazy load it (so no calls to the Acumulus API).
            $fields[$idPrefix . 'invoice_show'] = [
                'type' => 'button',
                'value' => $this->t('show'),
                'attributes' => [
                    'class' => ['acumulus-ajax', 'acumulus-auto-click'],
                ],
            ];
        } else {
            // 1st fieldset: the order (the main source).
            $localEntry = $this->acumulusEntryManager->getByInvoiceSource($source);
            $idPrefix = $this->getIdPrefix($source);
            $fields1Source = $this->addIdPrefix($this->getFields1Source($source, $localEntry), $idPrefix);
            $fields[$idPrefix] = [
                'type' => 'fieldset',
                'fields' => $fields1Source,
            ];

            // Other field sets: credit notes.
            $creditNotes = $source->getCreditNotes();
            foreach ($creditNotes as $creditNote) {
                $localEntry = $this->acumulusEntryManager->getByInvoiceSource($creditNote);
                $idPrefix = $this->getIdPrefix($creditNote);
                $fields1Source = $this->addIdPrefix($this->getFields1Source($creditNote, $localEntry), $idPrefix);
                $fields[$idPrefix] = [
                    'type' => 'details',
                    'summary' => ucfirst($this->t($creditNote->getType())) . ' ' . $creditNote->getReference(),
                    'fields' => $fields1Source,
                ];
            }
        }
        return $fields;
    }


    /**
     * Returns the overview for 1 source.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntry
     *
     * @return array[]
     *   The fields that describe the status for 1 source.
     */
    protected function getFields1Source(Source $source, ?AcumulusEntry $localEntry): array
    {
        $this->resetStatus();
        // Get invoice status field and other invoice status related info.
        $invoiceInfo = $this->getInvoiceInfo($source, $localEntry);

        $this->setStatus($invoiceInfo['severity'], $invoiceInfo['severity-message']);
        /** @var string $invoiceStatus */
        $invoiceStatus = $invoiceInfo['status'];
        /** @var string $statusText */
        $statusText = $invoiceInfo['text'];
        /** @var string $statusDescription */
        $statusDescription = $invoiceInfo['description'];
        /** @var AcumulusResult|null $result */
        $result = $invoiceInfo['result'];
        /** @var array $entry */
        $entry = $invoiceInfo['entry'];

        // Create and add additional fields based on invoice status.
        switch ($invoiceStatus) {
            case static::Invoice_NotSent:
                $additionalFields = $this->getNotSentFields();
                break;
            case static::Invoice_SentConcept:
            case static::Invoice_SentConceptNoInvoice:
                $additionalFields = $this->getConceptFields();
                break;
            case static::Invoice_CommunicationError:
                $additionalFields = $this->getCommunicationErrorFields($result);
                break;
            case static::Invoice_NonExisting:
                $additionalFields = $this->getNonExistingFields();
                break;
            case static::Invoice_Deleted:
                $additionalFields = $this->getDeletedFields();
                break;
            case static::Invoice_Sent:
                $additionalFields = $this->getEntryFields($source, $entry);
                break;
            case static::Invoice_LocalError:
                $additionalFields = [];
                break;
            default:
                $additionalFields = [
                    'unknown' => [
                        'type' => 'markup',
                        'value' => sprintf($this->t('invoice_status_unknown'), $invoiceStatus),
                    ]
                ];
                break;
        }

        // Create main status field after we have the other fields, so we can
        // use the results in rendering the overall status.
        return [
            'status' => [
                'type' => 'markup',
                'label' => $this->getStatusIcon($this->status),
                'attributes' => [
                    'class' => str_replace('_', '-', $invoiceStatus),
                    'label' => $this->getStatusLabelAttributes($this->status, $this->statusMessage),
                ],
                'value' => $statusText,
                'description' => $statusDescription,
            ],
               ] + $additionalFields;
    }

    /**
     * Returns (remote and local) invoice related information.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntry
     *   Passed by reference as it may have to be renewed when a concept was
     *   made definitive.
     *
     * @return array
     *   Keyed array with keys:
     *   - 'status' (string): 1 of the InvoiceStatusForm::Status_ constants.
     *   - 'send-status' (string): 1 of the InvoiceStatusForm::Invoice_ constants.
     *   - 'result' (\Siel\Acumulus\ApiClient\Result?): result of the getEntry API call.
     *   - 'entry' (array?): the (main) response part of the getEntry API call.
     *   - 'statusField' (array): a form field array representing the status.
     * @noinspection InvertedIfElseConstructsInspection
     */
    protected function getInvoiceInfo(Source $source, ?AcumulusEntry &$localEntry): array
    {
        $result = null;
        $entry = null;
        $arg1 = null;
        $arg2 = null;
        $description = '';
        $statusMessage = '';
        if ($localEntry === null) {
            $invoiceStatus = static::Invoice_NotSent;
            $statusSeverity = static::Status_Info;
        } else {
            $arg1 = $this->getDate($localEntry->getUpdated());
            if ($localEntry->getConceptId() !== null) {
                // Invoice was sent as an invoice: can we find out if it has
                // been turned into a definitive invoice?
                if ($localEntry->getConceptId() === AcumulusEntry::conceptIdUnknown) {
                    // Old entry: no concept id stored, we cannot show more
                    // information.
                    $invoiceStatus = static::Invoice_SentConcept;
                    $description = 'concept_no_conceptid';
                    $statusSeverity = static::Status_Warning;
                } else {
                    // Entry saved with support for concept ids.
                    // Has the concept been changed into an invoice?
                    $result = $this->acumulusApiClient->getConceptInfo($localEntry->getConceptId());
                    if ($result->hasError()) {
                        if ($result->isNotFound()) {
                            $invoiceStatus = static::Invoice_SentConcept;
                            $statusSeverity = static::Status_Warning;
                            $description = 'concept_conceptid_deleted';
                            // Prevent this API call in the future, it will return
                            // the same result.
                            $this->acumulusEntryManager->save($source, null, null);
                        } elseif ($result->getByCodeTag('FGYBSN048') !== null) {
                            //  'Helaas kan van deze conceptfactuur niet meer informatie getoond worden, ook niet als u deze definitief gemaakt heeft.'
                            $invoiceStatus = static::Invoice_SentConcept;
                            $statusSeverity = static::Status_Warning;
                            $description = 'concept_no_conceptid';
                        } else  {
                            $invoiceStatus = static::Invoice_CommunicationError;
                            $statusSeverity = static::Status_Error;
                        }
                    } else {
                        $conceptInfo = $this->sanitiseConceptInfo($result->getMainAcumulusResponse());
                        if (is_numeric($conceptInfo['entryid'])) {
                            // Concept turned into 1 definitive invoice: update the
                            // local acumulus entry, so it refers to that invoice.
                            $result = $this->acumulusApiClient->getEntry($conceptInfo['entryid']);
                            if (!$result->hasError()) {
                                $entry = $this->sanitiseEntry($result->getMainAcumulusResponse());
                                if ($this->acumulusEntryManager->save($source, $conceptInfo['entryid'], $entry['token'])) {
                                    $newLocalEntry = $this->acumulusEntryManager->getByInvoiceSource($source);
                                    if ($newLocalEntry === null) {
                                        $invoiceStatus = static::Invoice_LocalError;
                                        $statusSeverity = static::Status_Error;
                                        $description = 'entry_concept_not_loaded';
                                    } else {
                                        $localEntry = $newLocalEntry;
                                        // Status and severity will be overwritten
                                        // below based on the found real invoice.
                                    }
                                } else {
                                    $invoiceStatus = static::Invoice_LocalError;
                                    $statusSeverity = static::Status_Error;
                                    $description = 'entry_concept_not_updated';
                                }
                            } else {
                                $invoiceStatus = static::Invoice_CommunicationError;
                                $statusSeverity = static::Status_Error;
                                $entry = [];
                            }
                        } elseif (count($conceptInfo['entryid']) === 0) {
                            // Concept has not yet been turned into a definitive
                            // invoice.
                            $invoiceStatus = static::Invoice_SentConceptNoInvoice;
                            $description = 'concept_no_invoice';
                            $statusSeverity = static::Status_Warning;
                        } else /*if (count($conceptInfo['entryid']) >= 2)*/ {
                            // Multiple real invoices created out of this concept:
                            // cannot link concept to just 1 invoice.
                            // @nth: unless all but 1 are deleted ...
                            $invoiceStatus = static::Invoice_SentConcept;
                            $description = 'concept_multiple_invoiceid';
                            $statusSeverity = static::Status_Warning;
                        }
                    }
                }
            }

            if ($localEntry->getEntryId() !== null) {
                $result = $this->acumulusApiClient->getEntry($localEntry->getEntryId());
                if (!$result->hasError()) {
                    $entry = $this->sanitiseEntry($result->getMainAcumulusResponse());
                    if ($entry['deleted'] instanceof DateTime) {
                        // Entry has status "deleted".
                        $invoiceStatus = static::Invoice_Deleted;
                        $statusSeverity = static::Status_Warning;
                        $arg2 = $entry['deleted']->format(Api::Format_TimeStamp);
                    } else {
                        // Normal entry, still existing.
                        $invoiceStatus = static::Invoice_Sent;
                        $arg1 = $entry['invoicenumber'];
                        $arg2 = $entry['entrydate'];
                        $statusSeverity = static::Status_Success;
                        $statusMessage = $this->t('invoice_status_ok');
                    }
                } elseif ($result->isNotFound()) {
                    // Entry is no(t) (longer) existing.
                    $invoiceStatus = static::Invoice_NonExisting;
                    $statusSeverity = static::Status_Error;
                    // To prevent this error in the future, we delete the
                    // local entry.
                    $this->acumulusEntryManager->delete($localEntry, $source);
                } elseif (empty($entry)) {
                    // Other error.
                    $invoiceStatus = static::Invoice_CommunicationError;
                    $statusSeverity = static::Status_Error;
                }
            } elseif (empty($invoiceStatus)) {
                $invoiceStatus = static::Invoice_LocalError;
                $statusSeverity = static::Status_Error;
                $description = 'entry_concept_not_id';
            }
        }

        /** @noinspection PhpUndefinedVariableInspection */
        return [
            'severity' => $statusSeverity,
            'severity-message' => $statusMessage,
            'status' => $invoiceStatus,
            'result' => $result,
            'entry' => $entry,
            'text' => sprintf($this->t($invoiceStatus), $arg1, $arg2),
            'description' => $this->t($description),
        ];
    }

    /**
     * Returns additional form fields to show when the invoice has not yet been
     * sent.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getNotSentFields(): array
    {
        return [
            'invoice_add' => [
                'type' => 'button',
                'value' => $this->t('send_now'),
                'attributes' => [
                    'class' => 'acumulus-ajax',
                ],
            ],
            'force_send' => $this->getHiddenField(0),
        ];
    }

    /**
     * Returns additional form fields to show when the invoice has been sent as
     * concept.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getConceptFields(): array
    {
        return $this->getSendAgainFields();
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * a communication error occurred in retrieving the entry.
     *
     * @param \Siel\Acumulus\ApiClient\AcumulusResult $result
     *   The result that details the error.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getCommunicationErrorFields(AcumulusResult $result): array
    {
        return [
            'messages' => [
                'type' => 'markup',
                'label' => $this->t('messages'),
                'value' => $result->formatMessages(Message::Format_PlainListWithSeverity, Severity::RealMessages),
            ],
        ];
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * does no longer exist.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getNonExistingFields(): array
    {
        return $this->getSendAgainFields();
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * subsequently has been deleted in Acumulus.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getDeletedFields(): array
    {
        return [
                   'entry_deletestatus_set' => [
                       'type' => 'button',
                       'value' => $this->t('undelete'),
                       'attributes' => [
                           'class' => 'acumulus-ajax',
                       ],
                   ],
                   'delete_status' => $this->getHiddenField(Api::Entry_UnDelete),
               ]
               + $this->getSendAgainFields();
    }

    /**
     * Returns additional form fields to show when the invoice is still there.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param array $entry
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getEntryFields(Source $source, array $entry): array
    {
        $fields = $this->getVatTypeField($entry)
               + $this->getAmountFields($source, $entry)
               + $this->getPaymentStatusFields($source, $entry)
               + $this->getDocumentsFields($entry['token']);
        if ($this->status >= self::Status_Warning) {
            $fields += $this->getSendAgainFields();
        }
        return $fields;
    }

    /**
     * Returns the vat type field.
     */
    protected function getVatTypeField(array $entry): array
    {
        if (!empty($entry['vatreversecharge'])) {
            if (!empty($entry['foreigneu'])) {
                $vatType = Api::VatType_EuReversed;
            } else {
                $vatType = Api::VatType_NationalReversed;
            }
        } elseif (!empty($entry['marginscheme'])) {
            $vatType = Api::VatType_MarginScheme;
        } elseif (!empty($entry['foreignvat'])) {
            $vatType = Api::VatType_EuVat;
        } elseif (!empty($entry['foreignnoneu'])) {
            $vatType = Api::VatType_RestOfWorld;
        } else {
            $vatType = Api::VatType_National;
        }
        return [
            'vat_type' => [
            'type' => 'markup',
            'label' => ucfirst($this->t('vat_type')),
            'value' => $this->t('vat_type_' . $vatType),
            ],
        ];
    }

    /**
     * Returns the payment status fields for the given Acumulus invoice.
     *
     * @return array[]
     *   An array with form fields:
     *   - Actual payment status (and date if paid) of the invoice in Acumulus.
     *   - [Optional] date field to define the date to set the payment date to.
     *   - Button to change the payment status of the invoice in Acumulus.
     */
    protected function getPaymentStatusFields(Source $source, array $entry): array
    {
        $fields = [];
        $paymentStatus = $entry['paymentstatus'];
        $paymentDate = $entry['paymentdate'];
        $defaultPaymentDate = date(Api::DateFormat_Iso);

        $paymentStatusText = $paymentStatus !== 0 ? ('payment_status_' . $paymentStatus) : 'unknown';
        if ($paymentStatus === Api::PaymentStatus_Paid && !empty($paymentDate)) {
            $paymentStatusText .= '_date';
        }
        $paymentStatusText = sprintf($this->t($paymentStatusText), $paymentDate);

        $localPaymentStatus = $source->getPaymentStatus();
        if ($localPaymentStatus !== $paymentStatus) {
            $paymentCompareStatus = static::Status_Warning;
            $paymentCompareStatusText = $this->t('payment_status_not_equal');
            $this->setStatus($paymentCompareStatus, $paymentCompareStatusText);
            if ($localPaymentStatus === Api::PaymentStatus_Paid) {
                $shopSettings = $this->acumulusConfig->getShopSettings();
                $dateToUse = $shopSettings['dateToUse'];
                if ($dateToUse !== Config::IssueDateSource_Transfer) {
                    $defaultPaymentDate = $source->getInvoiceDate();
                    if ($dateToUse !== Config::IssueDateSource_InvoiceCreate || empty($defaultPaymentDate)) {
                        $defaultPaymentDate = $source->getDate();
                    }
                }
            }
        } else {
            $paymentCompareStatus = static::Status_Success;
        }

        $paymentStatusMarkup = sprintf('<span class="notice-%s">%s</span>', $this->getStatusClass($paymentCompareStatus), $paymentStatusText);
        $fields['payment_status'] = [
            'type' => 'markup',
            'label' => $this->t('payment_status'),
            'value' => $paymentStatusMarkup,
        ];
        if (!empty($paymentCompareStatusText)) {
            $fields['payment_status']['attributes'] = [
                'title' => $paymentCompareStatusText,
            ];
        }

        if ($paymentStatus === Api::PaymentStatus_Paid) {
            $fields += [
                'invoice_paymentstatus_set' => [
                    'type' => 'button',
                    'label' => '',
                    'value' => $this->t('set_due'),
                    'attributes' => [
                        'class' => 'acumulus-ajax',
                    ],
                ],
                'payment_status_new' => $this->getHiddenField(Api::PaymentStatus_Due),
            ];
        } else {
            $fields += [
                'payment_date' => [
                    'type' => 'date',
                    'label' => $this->t('payment_date'),
                    'attributes' => [
                        'placeholder' => $this->t('date_format'),
                        'required' => true,
                    ],
                    'value' => $defaultPaymentDate,
                ],
                'invoice_paymentstatus_set' => [
                    'type' => 'button',
                    'value' => $this->t('set_paid'),
                    'attributes' => [
                        'class' => 'acumulus-ajax',
                    ],
                ],
                'payment_status_new' => $this->getHiddenField(Api::PaymentStatus_Paid),
            ];
        }

        return $fields;
    }

    /**
     * Returns the amounts of this invoice.
     *
     * To check if the amounts match we have to treat local and EU vat the
     * same, which Acumulus didn't in the past, but does since the new EU
     * commerce rules:
     * New order:
     *   'foreignvat': '1',
     *   'totalvalueexclvat': '161.14',
     *   'totalvalue': '193.37',
     *   'totalvalueforeignvat': '32.23',
     * Old order:
     *   'foreignvat': '1',
     *   'totalvalueexclvat': '193.37',
     *   'totalvalue': '193.37',
     *   'totalvalueforeignvat': '32.23',
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param array $entry
     *   The sanitised remote entry, i.e. the response of the getEntry API call.
     *
     * @return array[]
     *   Array with form fields with the invoice amounts.
     */
    protected function getAmountFields(Source $source, array $entry): array
    {
        $fields = [];
        if (!empty($entry['totalvalue']) && !empty($entry['totalvalueexclvat'])) {
            // Get Acumulus amounts.
            // @todo: invoice status form:
            //   - getEntry API call also handles other foreign vat?
            //   - $entry['foreignvat'] what does it contain for UK invoice?
            $amountExAcumulus = (float) $entry['totalvalueexclvat'];
            $amountIncAcumulus = (float) $entry['totalvalue'];
            $amountVatAcumulus = $amountIncAcumulus - $amountExAcumulus;
            if ($entry['foreignvat']) {
                $vatType = $this->t('foreign_vat');
                $amountForeignEuVatAcumulus = $entry['totalvalueforeignvat'];
                // Old or new way of storage?
                if (Number::isZero($amountVatAcumulus)) {
                    // Old (or zero vat): correct the vat and ex amount.
                    $amountVatAcumulus += $amountForeignEuVatAcumulus;
                    $amountExAcumulus -= $amountForeignEuVatAcumulus;
                } elseif (!Number::floatsAreEqual($amountVatAcumulus, $amountForeignEuVatAcumulus)) {
                    // New (or mixed): the difference should be all tax paid.
                    // We just do a check on mixed vat, which, I think, should
                    // not be possible.
                    $vatType = $this->t('foreign_national_vat');
                }
            } else {
                $vatType = $this->t('vat');
            }

            // Get local amounts.
            $localTotals = $source->getTotals();

            // Compare.
            $amountExStatus = $this->getAmountStatus($amountExAcumulus, $localTotals->amountEx);
            $amountIncStatus = $this->getAmountStatus($amountIncAcumulus, $localTotals->amountInc);
            $amountVatStatus = $this->getAmountStatus($amountVatAcumulus, $localTotals->amountVat);

            $amountEx = $this->getFormattedAmount($amountExAcumulus, $amountExStatus);
            $amountInc = $this->getFormattedAmount($amountIncAcumulus, $amountIncStatus);
            $amountVat = $this->getFormattedAmount($amountVatAcumulus, $amountVatStatus);

            $fields['invoice_amount'] = [
                'type' => 'markup',
                'label' => $this->t('invoice_amount'),
                'value' => sprintf('<div class="acumulus-amount">%1$s%2$s %4$s%3$s</div>', $amountEx, $amountVat, $amountInc, $vatType),
            ];
        }
        return $fields;
    }

    /**
     * Returns the status of an amount by comparing it with its local value.
     * If the amounts differ:
     * - < 0.5 cent, they are considered equal and 'success' will be returned.
     * - < 2 cents, it is considered a mere rounding error and 'info' will be returned.
     * - < 5 cents, it is considered a probable error and 'warning' will be returned.
     * - >= 5 cents, it is considered an error and 'error' will be returned.
     *
     * @return int
     *   One of the {@see InvoiceStatusForm}::Status_... constants.
     */
    protected function getAmountStatus(float $amount, float $amountLocal): int
    {
        if (Number::floatsAreEqual($amount, $amountLocal)) {
            $status = static::Status_Success;
        } elseif (Number::floatsAreEqual($amount, $amountLocal, 0.02)) {
            $status = static::Status_Info;
        } elseif (Number::floatsAreEqual($amount, $amountLocal, 0.05)) {
            $status = static::Status_Warning;
        } else {
            $status = static::Status_Error;
        }
        return $status;
    }

    /**
     * Formats an amount in html, adding classes given the status.
     *
     * @param int $status
     *   One of the {@see InvoiceStatusForm}::Status_... constants.
     *
     * @return string
     *   An HTML string representing the amount and its status.
     */
    protected function getFormattedAmount(float $amount, int $status): string
    {
        $currency = '€';
        $sign = $amount < 0.0 ? '-' : '';
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $amount = abs($amount);
        $statusClass = $this->getStatusClass($status);
        $statusMessage = $this->getAmountStatusTitle($status);
        $this->setStatus($status, $statusMessage);
        if (!empty($statusMessage)) {
            $statusMessage = " title=\"$statusMessage\"";
        }

        $result = '';
        $result .= '<span class="sign">' . $sign . '</span>';
        $result .= '<span class="currency">' . $currency . '</span>';
        $result .= number_format($amount, 2, ',', '.');
        // Prevents warning "There should be a space between attribute ..."
        $wrapperBegin = "<span class=\"amount notice-$statusClass\"" . $statusMessage . '>';
        $result = $wrapperBegin . $result . '</span>';

        return $result;
    }

    /**
     * Returns links and buttons to the invoice and packing slip documents.
     *
     * @return array[]
     *   Form field array that, depending on config and availability contains:
     *   - links to show the documents related to this order.
     *   - buttons to mail the documents related to this order.
     */
    protected function getDocumentsFields(string $token): array
    {
        $result = [];
        $actions = [];
        $links = [];
        $documentsSettings = $this->container->getConfig()->getDocumentsSettings();

        $document = ucfirst($this->t('document_invoice'));
        if ($documentsSettings['showInvoiceDetail']) {
            $uri = $this->acumulusApiClient->getInvoicePdfUri($token);
            $text = sprintf($this->t('document_show'), $document);
            $title = sprintf($this->t('document_show_title'), $document);
            /** @noinspection HtmlUnknownTarget */
            $links['invoice_show'] = [
                'type' => 'markup',
                'value' => sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>',
                    $uri, $text, $title, 'acumulus-document-invoice'),
            ];
        }
        if ($documentsSettings['mailInvoiceDetail']) {
            $actions['invoice_mail'] = [
                'type' => 'button',
                'value' => sprintf($this->t('document_mail'), $document),
                'attributes' => [
                    'class' => ['acumulus-ajax', 'acumulus-document-invoice'],
                    'title' => sprintf($this->t('document_mail_title'), $document),
                ],
            ];
        }

        $document = ucfirst($this->t('document_packing_slip'));
        if ($documentsSettings['showPackingSlipDetail']) {
            $uri = $this->acumulusApiClient->getPackingSlipPdfUri($token);
            $text = sprintf($this->t('document_show'), $document);
            $title = sprintf($this->t('document_show_title'), $document);
            /** @noinspection HtmlUnknownTarget */
            $links['packing_slip_show'] = [
                'type' => 'markup',
                'value' => sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>',
                    $uri, $text, $title, 'acumulus-document-packing-slip'),
            ];
        }
        if ($documentsSettings['mailPackingSlipDetail']) {
            $actions['packing_slip_mail'] = [
                'type' => 'button',
                'value' => sprintf($this->t('document_mail'), $document),
                'attributes' => [
                    'class' => ['acumulus-ajax', 'acumulus-document-packing-slip'],
                    'title' => sprintf($this->t('document_mail_title'), $document),
                ],
            ];
        }

        if (count($actions) !== 0 || count($links) !== 0) {
            $result['documents'] = [
                'type' => 'collection',
                'label' => $this->t(count($links) === 1 ? 'document' : 'documents'),
                'fields' => [],
            ];
            if (count($links) !== 0) {
                $result['documents']['fields']['linksBefore'] = [
                    'type' => 'markup',
                    'value' => '<div class="acumulus-links">',
                ];
                $result['documents']['fields'] += $links;
                $result['documents']['fields']['linksAfter'] = [
                    'type' => 'markup',
                    'value' => '</div>',
                ];
            }
            if (count($actions) !== 0) {
                $result['documents']['fields'] += $actions;
            }
        }

        return $result;
    }

    /**
     * Returns additional form fields to send the invoice again.
     *
     * This will be shown when the invoice was sent as a concept, has been
     * deleted or does not exist at all in Acumulus, or if there is a warning
     * or error status.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getSendAgainFields(): array
    {
        return [
            'invoice_add' => [
                'type' => 'button',
                'value' => $this->t('send_again'),
                'attributes' => [
                    'class' => 'acumulus-ajax',
                ],
            ],
            'force_send' => $this->getHiddenField(1),
        ];
    }

    /**
     * Returns a hidden field.
     *
     * @param string|int $value
     *   The value for the hidden field.
     */
    protected function getHiddenField($value): array
    {
        return [
            'type' => 'hidden',
            'value' => $value,
        ];
    }

    /**
     * Returns a formatted date.
     */
    protected function getDate(DateTime $date): string
    {
        return $date->format(Api::DateFormat_Iso);
    }

    /**
     * Returns a prefix for ids and names to make them unique if multiple
     * invoices (an order and its credit notes) are shown at the same time.
     */
    protected function getIdPrefix(Source $source): string
    {
        return $source->getType() . '_' . $source->getId() . '_';
    }

    /**
     * Adds a prefix to all keys in the set of $fields.
     * This is done to ensure unique id's in case of repeating fieldsets.
     *
     * @param array[] $fields
     * @param string $idPrefix
     *
     * @return array[]
     *   The set of fields with their ids prefixed.
     */
    protected function addIdPrefix(array $fields, string $idPrefix): array
    {
        $result = [];
        foreach ($fields as $key => $field) {
            $newKey = $idPrefix . $key;
            $result[$newKey] = $field;
            if (isset($field['fields'])) {
                $result[$newKey]['fields'] = $this->addIdPrefix($field['fields'], $idPrefix);
            }
            if (isset($field['inputs'])) {
                $result[$newKey]['inputs'] = $this->addIdPrefix($field['inputs'], $idPrefix);
            }
        }
        return $result;
    }

    /**
     * sanitises an entry struct received via a getEntry API call.
     *
     * The info received from an external API call should not be trusted, so it
     * should be sanitised. As most info from this API call is placed in markup
     * fields we cannot rely on the FormRenderer or the web shop's form API
     * (which do not sanitise markup fields).
     *
     * So we sanitise the values in the struct itself before using them:
     * - Int, float, and bool fields are cast to their proper type.
     * - Date strings are parsed to a DateTime and formatted back to a date
     *   string.
     * - Strings that can only contain a restricted set of values are checked
     *   against that set and emptied if not part of it.
     * - Free string values are escaped to save html.
     * - Keys we don't use are not returned. This keeps the output safe when a
     *   future API version returns additional fields, and we forget to sanitise
     *   it and thus use it non sanitised.
     *
     * Keys in $entry array (* are sanitised):
     (*) - 'token' (*)
     (*) - 'entryid' (*)
     (*) - 'entrydate' (*): yy-mm-dd
     (*) - 'entrytype'
     (*) - 'entrydescription'
     (*) - 'entrynote'
     (*) - 'fiscaltype'
     (*) - 'vatreversecharge' (*): 0 or 1
     (*) - 'foreigneu' (*): 0 or 1
     (*) - 'foreignnoneu' (*): 0 or 1
     (*) - 'marginscheme' (*): 0 or 1
     (*) - 'foreignvat' (*): 0 or 1
     (*) - 'contactid'
     (*) - 'accountnumber'
     (*) - 'costcenterid'
     (*) - 'costtypeid'
     (*) - 'invoicenumber' (*)
     (*) - 'invoicenote'
     (*) - 'descriptiontext'
     (*) - 'invoicelayoutid'
     (*) - 'paymenttoken'
     (*) - 'totalvalueexclvat' (*)
     (*) - 'totalvalue' (*)
     (*) - 'totalvalueforeignvat' (*)
     (*) - 'paymenttermdays'
     (*) - 'paymentdate' (*): yy-mm-dd
     (*) - 'paymentstatus' (*): 1 or 2
     (*) - 'deleted' (*): timestamp
     *
     * @param array $entry
     *   The entry to sanitise.
     * @return array
     *   The sanitised entry struct.
     *
     * @todo: Move this and all following methods to separate Sanitise class.
     */
    protected function sanitiseEntry(array $entry): array
    {
        if (count($entry) !== 0) {
            $result = [];
            $result['entryid'] = $this->sanitiseIntValue($entry, 'entryid');
            $result['token'] = $this->sanitiseStringValue($entry, 'token', '/^[a-zA-Z\d]{32}$/');
            $result['entrydate'] = $this->sanitiseDateValue($entry, 'entrydate');
            $result['vatreversecharge'] = $this->sanitiseBoolValue($entry, 'vatreversecharge');
            $result['foreigneu'] = $this->sanitiseBoolValue($entry, 'foreigneu');
            $result['foreignnoneu'] = $this->sanitiseBoolValue($entry, 'foreignnoneu');
            $result['marginscheme'] = $this->sanitiseBoolValue($entry, 'marginscheme');
            $result['foreignvat'] = $this->sanitiseBoolValue($entry, 'foreignvat');
            $result['invoicenumber'] = $this->sanitiseIntValue($entry, 'invoicenumber');
            $result['totalvalueexclvat'] = $this->sanitiseFloatValue($entry, 'totalvalueexclvat');
            $result['totalvalue'] = $this->sanitiseFloatValue($entry, 'totalvalue');
            $result['totalvalueforeignvat'] = $this->sanitiseFloatValue($entry, 'totalvalueforeignvat');
            $result['paymentstatus'] = $this->sanitiseIntValue($entry, 'paymentstatus');
            $result['paymentdate'] = $this->sanitiseDateValue($entry, 'paymentdate');
            $result['deleted'] = $this->sanitiseDateTimeValue($entry, 'deleted');
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Sanitises a concept info struct received via a getConceptInfo API call.
     *
     * The info received from an external API call should not be trusted, so it
     * should be sanitised. As most info from this API call is placed in markup
     * fields we cannot rely on the FormRenderer or the web shop's form API as
     * these do not sanitise markup fields.
     *
     * So we sanitise the values in the struct itself before using them:
     * - Int, float, and bool fields are cast to their proper type.
     * - Date strings are parsed to a DateTime and formatted back to a date
     *   string.
     * - Strings that can only contain a restricted set of values are checked
     *   against that set and emptied if not part of it.
     * - Free string values are escaped to save html.
     * - Keys we don't use are not returned. This keeps the output safe when a
     *   future API version returns additional fields, and we forget to sanitise
     *   it and thus use it non sanitised.
     *
     * Keys in $entry array:
     *   - 'conceptid': int
     *   - 'entryid': int|int[]
     *
     * @param array $conceptInfo
     *
     * @return array|null
     *   The sanitised entry struct.
     */
    protected function sanitiseConceptInfo(array $conceptInfo): ?array
    {
        if (count($conceptInfo) !== 0) {
            $result = [];
            $result['conceptid'] = $this->sanitiseIntValue($conceptInfo, 'conceptid');
            $result['entryid'] = $this->sanitiseIntValue($conceptInfo, 'entryid', true);
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Returns a html safe version of a string in an entry record.
     *
     * @param array $entry
     * @param string $key
     * @param string|string[]|null $additionalRestriction
     *   An optional additional restriction to apply. If it is a string it is
     *   considered a regular expression and the value is matched against it.
     *   If it is an array, it is considered a set of allowed values and the
     *   value is tested for being in the array.
     *
     * @return string
     *   The html safe version of the value under this key or the empty string
     *   if not set.
     */
    protected function sanitiseStringValue(array $entry, string $key, $additionalRestriction = null): string
    {
        $result = '';
        if (!empty($entry[$key])) {
            $value = $entry[$key];
            if (is_string($additionalRestriction)) {
                if (preg_match($additionalRestriction, $value)) {
                    $result = $value;
                }
            } elseif (is_array($additionalRestriction)) {
                if (in_array($value, $additionalRestriction, true)) {
                    $result = $value;
                }
            } else {
                $result = htmlspecialchars($value, ENT_NOQUOTES);
            }
        }
        return $result;
    }

    /**
     * Returns a sanitised integer value of an entry record.
     *
     * @return int|int[]
     *   The int value of the value under this key or 0 if not provided. If
     *   $allowArray is set, an empty array is returned, if no value is set.
     */
    protected function sanitiseIntValue(array $entry, string $key, bool $allowArray = false)
    {
        if (isset($entry[$key])) {
            if ($allowArray && is_array($entry[$key])) {
                $result = [];
                foreach ($entry[$key] as $value) {
                    $result[] = (int) $value;
                }
            } else {
                $result = (int) $entry[$key];
            }
        } else {
            $result = $allowArray ? [] : 0;
        }
        return $result;
    }

    /**
     * Returns a sanitised float value of an entry record.
     *
     * @return float
     *   The float value of the value under this key.
     */
    protected function sanitiseFloatValue(array $entry, string $key): float
    {
        return !empty($entry[$key]) ? (float) $entry[$key] : 0.0;
    }

    /**
     * Returns a sanitised bool value of an entry record.
     *
     * @return bool
     *   The bool value of the value under this key. True values are represented
     *   by 1, false values by 0.
     */
    protected function sanitiseBoolValue(array $entry, string $key): bool
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        return isset($entry[$key]) && $entry[$key] == 1;
    }

    /**
     * Returns a sanitised date value of an entry record.
     *
     * @return string
     *   The date value (yyyy-mm-dd) of the value under this key or the empty
     *   string, if the string is not in the valid date format (yyyy-mm-dd).
     */
    protected function sanitiseDateValue(array $entry, string $key): string
    {
        $date = '';
        if (!empty($entry[$key])) {
            $date = DateTime::createFromFormat(Api::DateFormat_Iso, $entry[$key]);
            if ($date instanceof DateTime) {
                $date = $date->format(Api::DateFormat_Iso);
            } else {
                $date = '';
            }
        }
        return $date;
    }

    /**
     * Returns a sanitised date time value of an entry record.
     *
     * @return DateTime|null
     *   The date time value of the value under this key or null if the string
     *   is not in the valid date-time format (yyyy-mm-dd hh:mm:ss).
     *   Note that the API might return 0000-00-00 00:00:00 which should not be
     *   accepted (recognised by a negative timestamp).
     */
    protected function sanitiseDateTimeValue(array $entry, string $key): ?DateTime
    {
        $timeStamp = null;
        if (!empty($entry[$key])) {
            $timeStamp = DateTime::createFromFormat(Api::Format_TimeStamp, $entry[$key]);
            if (!$timeStamp instanceof DateTime || $timeStamp->getTimestamp() < 0) {
                $timeStamp = null;
            }
        }
        return $timeStamp;
    }
}
