<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Shop\InvoiceManager as BaseInvoiceManager;
use Siel\Acumulus\Web\Result;
use Siel\Acumulus\Web\Service;

/**
 * Defines the Acumulus invoice status overview form.
 *
 * This form is mostly informative but contains some buttons and a few fields
 * to update the invoice in Acumulus.
 *
 * SECURITY REMARKS
 * ----------------
 */
class InvoiceStatusOverviewForm extends Form
{
    // Constants representing the status of the Acumulus invoice for a given
    // shop order or refund.
    const Invoice_NotSent = 'invoice_not_sent';
    const Invoice_Sent = 'invoice_sent';
    const Invoice_SentConcept  = 'invoice_sent_concept';
    const Invoice_Deleted = 'invoice_deleted';
    const Invoice_NonExisting = 'invoice_non_existing';
    const Invoice_CommunicationError = 'invoice_communication_error';
    const Invoice_LocalError = 'invoice_local_error';

    const Status_Unknown = 0;
    const Status_Success = 1;
    const Status_Info = 2;
    const Status_Warning = 3;
    const Status_Error = 4;

    /** @var \Siel\Acumulus\Web\Service */
    private $service;

    /** @var \Siel\Acumulus\Shop\InvoiceManager */
    private $invoiceManager;

    /** @var \Siel\Acumulus\Shop\AcumulusEntryManager */
    private $acumulusEntryManager;

    /**
     * The main Source for this form.
     *
     * This form can handle an order and its credit notes at the same time, the
     * order being the "main" source.
     *
     * @var \Siel\Acumulus\Invoice\Source
     */
    private $source;

    /**
     * The submitted Source for this form.
     *
     * This form can handle an order and its credit notes at the same time. A
     * submitted action will be on 1 of these Sources, we keep track of which
     * source this is in this property.
     *
     * @var \Siel\Acumulus\Invoice\Source
     */
    private $submittedSource;

    /**
     * One of the Result::Status_... constants.
     *
     * @var int
     */
    private $status;

    /**
     * A message indicating why the status is not OK..
     *
     * @var string
     */
    private $statusMessage;

    /**
     * @param \Siel\Acumulus\Shop\InvoiceManager $invoiceManager
     * @param \Siel\Acumulus\WooCommerce\Shop\AcumulusEntryManager $acumulusEntryManager
     * @param \Siel\Acumulus\Web\Service $service
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(
        BaseInvoiceManager $invoiceManager,
        AcumulusEntryManager $acumulusEntryManager,
        Service $service,
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

        $translations = new InvoiceStatusOverviewFormTranslations();
        $this->translator->add($translations);

        $this->acumulusEntryManager = $acumulusEntryManager;
        $this->invoiceManager = $invoiceManager;
        $this->service = $service;
        $this->resetStatus();
    }

    /**
     * @param \Siel\Acumulus\Invoice\Source $source
     */
    public function setSource(Source $source)
    {
        $this->source = $source;
    }

    /**
     * Sets the status, but only if it is "worse" than the current status.
     *
     * @param int $status
     *   The status to set.
     * @param string $message
     *   Optionally, a message indicating what is wrong may be given.
     */
    private function setStatus($status, $message = '')
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
    private function resetStatus()
    {
        $this->status = static::Status_Unknown;
        $this->statusMessage = '';
    }

    /**
     * Returns a string to use as css class for the current status.
     *
     * @param int $status
     *
     * @return string
     */
    public function getStatusClass($status)
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
     * @param int $status
     *
     * @return string
     *   An icon character that represents the status.
     */
    private function getStatusIcon($status)
    {
        switch ($status) {
            case static::Status_Success:
                $result = json_decode('"\u2714"');
                break;
            case static::Status_Info:
            case static::Status_Warning:
                $result = '!';
                break;
            case static::Status_Error:
            default:
                $result = json_decode('"\u2716"');
                break;
        }
        return $result;
    }

    /**
     * Returns a set of label attributes for the current status.
     *
     * @param int $status
     * @param string $statusMessage
     *
     * @return array
     *   A set of attributes to add to the label.
     */
    private function getStatusLabelAttributes($status, $statusMessage)
    {
        $statusClass = $this->getStatusClass($status);
        $attributes = array(
            'class' => array('notice', 'notice-' . $statusClass),
            'wrapper' => array(
                'class' => array('notice', 'notice-' . $statusClass),
            ),
        );
        if (!empty($statusMessage)) {
            $attributes['title'] = $statusMessage;
        }
        return $attributes;
    }

    /**
     * Returns a description of the amount status.
     *
     * @param int $status
     *
     * @return string
     *   A description of the amount status.
     */
    private function getAmountStatusTitle($status)
    {
        $result = '';
        if ($status > static::Status_Success) {
            $result = $this->t('amount_status_' . $status);
        }
        return $result;
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

        // Get the targeted source.
        $this->setSubmittedSource();
        // Sanitise service: lowercase ascii characters, numbers, _ and -.
        $this->submittedValues['service'] = preg_replace('/[^a-z0-9_\-]/', '', $this->submittedValues['service']);
    }

    /**
     * Extracts the source on which the submitted action is targeted.
     */
    private function setSubmittedSource()
    {
        // Get actual source ($this->source may be the parent, not the source to
        // execute on). Do so without trusting the input.
        $sourceType = $this->getSubmittedValue('type') === Source::Order ? Source::Order : Source::CreditNote;
        $sourceId = (int) $this->getSubmittedValue('source');
        $this->submittedSource = null;
        if ($this->source->getType() === $sourceType && $this->source->getId() === $sourceId) {
            $this->submittedSource = $this->source;
        } else {
            $creditNotes = $this->source->getCreditNotes();
            foreach ($creditNotes as $creditNote) {
                if ($creditNote->getType() === $sourceType && $creditNote->getId() === $sourceId) {
                    $this->submittedSource = $creditNote;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function validate()
    {
        if ($this->submittedSource === null) {
            $this->addErrorMessages(sprintf($this->t('unknown_source'),
                htmlspecialchars($this->getSubmittedValue('type'), ENT_COMPAT, 'UTF-8'),
                (int) $this->getSubmittedValue('source')
            ));
        } elseif ($this->getSubmittedValue('service') === 'invoice_paymentstatus_set' && (int) $this->getSubmittedValue('value') === Api::PaymentStatus_Paid) {
            $dateFieldName = $this->getIdPrefix($this->submittedSource) . 'payment_date';
            if (!DateTime::createFromFormat(API::DateFormat_Iso, $this->submittedValues[$dateFieldName])) {
                // Date is not a valid date.
                $this->errorMessages[$dateFieldName] = sprintf($this->t('message_validate_batch_bad_payment_date'), $this->t('date_format'));
            }
        }
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
        $source = $this->submittedSource;
        switch ($service) {
            case 'invoice_add':
                $forceSend = (bool) $this->getSubmittedValue('value');
                $result = $this->invoiceManager->send1($source, $forceSend);
                break;

            case 'invoice_paymentstatus_set':
                $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);
                if ($localEntryInfo) {
                    if ((int) $this->getSubmittedValue('value') === Api::PaymentStatus_Paid) {
                        $paymentStatus = Api::PaymentStatus_Paid;
                        $dateFieldName = $this->getIdPrefix($this->submittedSource) . 'payment_date';
                        $paymentDate = $this->submittedValues[$dateFieldName];
                    } else {
                        $paymentStatus = Api::PaymentStatus_Due;
                        $paymentDate = '';
                    }
                    $result = $this->service->setPaymentStatus($localEntryInfo->getToken(), $paymentStatus, $paymentDate);
                } else {
                    $this->addErrorMessages(sprintf($this->t('unknown_entry'),
                        strtolower($this->t($this->submittedSource->getType())),
                        $this->submittedSource->getId()
                    ));
                }
                break;

            case 'entry_deletestatus_set':
                $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);
                if ($localEntryInfo && $localEntryInfo->getEntryId() !== null) {
                    $deleteStatus = $this->getSubmittedValue('value') === Api::Entry_Delete ? Api::Entry_Delete : Api::Entry_UnDelete;
                    // @todo: clean up on receiving P2XFELO12?
                    $result = $this->service->setDeleteStatus($localEntryInfo->getEntryId(), $deleteStatus);
                } else {
                    $this->addErrorMessages(sprintf($this->t('unknown_entry'),
                      strtolower($this->t($this->submittedSource->getType())), $this->submittedSource->getId())
                    );
                }
                break;

            default:
                $this->addErrorMessages(sprintf($this->t('unknown_action'), $service));
                break;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFieldDefinitions()
    {
        $fields = array();

        // 1st fieldset: Order.
        $source = $this->source;
        $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);
        $idPrefix = $this->getIdPrefix($source);
        $fields1Source = $this->addIdPrefix($this->getFields1Source($source, $localEntryInfo), $idPrefix);
        $fields[$idPrefix] = array(
            'type' => 'fieldset',
            'fields' => $fields1Source,
        );

        // Other fieldsets: credit notes.
        $creditNotes = $source->getCreditNotes();
        foreach($creditNotes as $creditNote) {
            $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($creditNote);
            $idPrefix = $this->getIdPrefix($creditNote);
            $fields1Source = $this->addIdPrefix($this->getFields1Source($creditNote, $localEntryInfo), $idPrefix);
            $fields[$idPrefix] = array(
                'type' => 'details',
                'summary' => ucfirst($this->t($creditNote->getType())) . ' ' . $creditNote->getReference(),
                'fields' => $fields1Source,
            );
        }
        return $fields;
    }


    /**
     * Returns the overview for 1 source.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntryInfo
     *
     * @return array[]
     *   The fields that describe the status for 1 source.
     */
    private function getFields1Source(Source $source, $localEntryInfo)
    {
        $this->resetStatus();
        // Get invoice status field and other invoice status related info.
        $statusInfo = $this->getInvoiceStatusInfo($source, $localEntryInfo);

        $this->setStatus($statusInfo['severity'], $statusInfo['severity-message']);
        /** @var string $invoiceStatus */
        $invoiceStatus = $statusInfo['status'];
        /** @var string $statusText */
        $statusText = $statusInfo['text'];
        /** @var string $statusDescription */
        $statusDescription = $statusInfo['description'];
        /** @var Result|null $result */
        $result = $statusInfo['result'];
        /** @var array $entry */
        $entry = $statusInfo['entry'];

        // Create and add additional fields based on invoice status.
        switch ($invoiceStatus) {
            case static::Invoice_NotSent:
                $additionalFields = $this->getNotSentFields($source);
                break;
            case static::Invoice_SentConcept:
                $additionalFields = $this->getConceptFields($source);
                break;
            case static::Invoice_CommunicationError:
                /** @noinspection PhpUndefinedVariableInspection */
                $additionalFields = $this->getCommunicationErrorFields($result);
                break;
            case static::Invoice_NonExisting:
                $additionalFields = $this->getNonExistingFields($source);
                break;
            case static::Invoice_Deleted:
                $additionalFields = $this->getDeletedFields($source);
                break;
            case static::Invoice_Sent:
                $additionalFields = $this->getEntryFields($source, $entry);
                break;
            default:
                $additionalFields = array(
                    'unknown' => array(
                        'type' => 'markup',
                        'value' => sprintf($this->t('invoice_status_unknown'), $invoiceStatus),
                    )
                );
                break;
        }

        // Create main status field after we have the other fields, so we can
        // use the results in rendering the overall status.
        $fields = array(
            'status' => array(
                'type' => 'markup',
                'label' => $this->getStatusIcon($this->status),
                'attributes' => array(
                    'class' => str_replace('_', '-', $invoiceStatus),
                    'label' => $this->getStatusLabelAttributes($this->status, $this->statusMessage),
                ),
                'value' => $statusText,
                'description' => $statusDescription,
            ),
        ) + $additionalFields;
        return $fields;
    }

    /**
     * Returns status related information.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntryInfo
     *   Passed by reference as it may have to be renewed when a concept was
     *   made definitive.
     *
     * @return array
     *   Keyed array with keys:
     *   - status (string): 1 of the ShopOrderOverviewForm::Status_ constants.
     *   - send-status (string): 1 of the ShopOrderOverviewForm::Invoice_ constants.
     *   - result (\Siel\Acumulus\Web\Result?): result of the getEntry API call.
     *   - entry (array|null): the <entry> part of the getEntry API call.
     *   - statusField (array): a form field array representing the status.
     */
    private function getInvoiceStatusInfo(Source $source, &$localEntryInfo)
    {
        $result = null;
        $entry = null;
        $arg1 = null;
        $arg2 = null;
        $description = '';
        $statusMessage = null;
        if ($localEntryInfo === null) {
            $invoiceStatus = static::Invoice_NotSent;
            $statusSeverity = static::Status_Info;
        } else {
            $arg1 = $this->getDate($localEntryInfo->getUpdated());
            if ($localEntryInfo->getConceptId() !== null) {
                if ($localEntryInfo->getConceptId() === BaseAcumulusEntry::conceptIdUnknown) {
                    // Old entry: no concept id stored, we cannot show more
                    // information.
                    $invoiceStatus = static::Invoice_SentConcept;
                    $description = 'concept_no_conceptid';
                    $statusSeverity = static::Status_Warning;
                } else {
                    // Entry saved with support for concept ids.
                    // Has the concept been changed into an invoice?
                    $result = $this->service->getConceptInfo($localEntryInfo->getConceptId());
                    $conceptInfo = $this->sanitizeConceptInfo($result->getResponse());
                    if (empty($conceptInfo)) {
                        $invoiceStatus = static::Invoice_CommunicationError;
                        $statusSeverity = static::Status_Error;
                    } elseif ($result->hasCodeTag('FGYBSN040') || $result->hasCodeTag('FGYBSN048')) {
                        // Concept id does not exist (anymore).
                        $invoiceStatus = static::Invoice_SentConcept;
                        $statusSeverity = static::Status_Warning;
                        $description = 'concept_conceptid_deleted';
                        // Prevent this API call in the future, it will return
                        // the same result.
                        $this->acumulusEntryManager->save($source, null, null);
                    } elseif (empty($conceptInfo['entryid'])) {
                        // Concept has not yet been turned into a definitive
                        // invoice.
                        $invoiceStatus = static::Invoice_SentConcept;
                        $statusSeverity = static::Status_Warning;
                        $description = 'concept_no_invoiceid';
                    } elseif (is_array($conceptInfo['entryid']) && count($conceptInfo['entryid']) >= 2) {
                        // Multiple real invoices created out of this concept:
                        // cannot link concept to just 1 invoice.
                        // @nth: unless all but 1 are deleted ...
                        $invoiceStatus = static::Invoice_SentConcept;
                        $description = 'concept_multiple_invoiceid';
                        $statusSeverity = static::Status_Warning;
                    } else {
                        // Concept turned into 1 definitive invoice: update
                        // acumulus entry to have it refer to that invoice.
                        /** @noinspection PhpParamsInspection, see bug: https://youtrack.jetbrains.com/issue/WI-48388 */
                        /** @noinspection PhpParamsInspection bug:*/
                        $result = $this->service->getEntry($conceptInfo['entryid']);
                        $entry = $this->sanitizeEntry($result->getResponse());
                        if (!$result->hasError() && !empty($entry['token'])) {
                            /** @noinspection PhpParamsInspection, see bug: https://youtrack.jetbrains.com/issue/WI-48388 */
                            if ($this->acumulusEntryManager->save($source, $conceptInfo['entryid'], $entry['token'])) {
                                $newLocalEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);
                                if ($newLocalEntryInfo === null) {
                                    $invoiceStatus = static::Invoice_LocalError;
                                    $statusSeverity = static::Status_Error;
                                    $description = 'entry_concept_not_loaded';
                                } else {
                                    $localEntryInfo = $newLocalEntryInfo;
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
                        }
                    }
                }
            }

            if ($localEntryInfo->getEntryId() !== null) {
                $result = $this->service->getEntry($localEntryInfo->getEntryId());
                $entry = $this->sanitizeEntry($result->getResponse());
                if ($result->hasCodeTag('XGYBSN000')) {
                    $invoiceStatus = static::Invoice_NonExisting;
                    $statusSeverity = static::Status_Error;
                    // To prevent this error in the future, we delete the local
                    // entry.
                    $this->acumulusEntryManager->delete($localEntryInfo);
                } elseif (empty($entry)) {
                    $invoiceStatus = static::Invoice_CommunicationError;
                    $statusSeverity = static::Status_Error;
                } elseif ($entry['deleted'] instanceof DateTime) {
                    $invoiceStatus = static::Invoice_Deleted;
                    $statusSeverity = static::Status_Warning;
                    $arg2 = $entry['deleted']->format(Api::Format_TimeStamp);
                } else {
                    $invoiceStatus = static::Invoice_Sent;
                    $arg1 = $entry['invoicenumber'];
                    $arg2 = $entry['entrydate'];
                    $statusSeverity = static::Status_Success;
                    $statusMessage = $this->t('invoice_status_ok');
                }
            }
        }

        /** @noinspection PhpUndefinedVariableInspection */
        return array(
            'severity' => $statusSeverity,
            'severity-message' => $statusMessage,
            'status' => $invoiceStatus,
            'result' => $result,
            'entry' => $entry,
            'text' => sprintf($this->t($invoiceStatus), $arg1, $arg2),
            'description' => $this->t($description),
        );
    }

    /**
     * Returns additional form fields to show when the invoice has not yet been
     * sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The Source for which the invoice has not yet been sent.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getNotSentFields(Source $source)
    {
        $fields = array();
        $fields += array(
            'send' => array(
                'type' => 'button',
                'ajax' => array(
                    'service' => 'invoice_add',
                    'parent_type' => $this->source->getType(),
                    'parent_source' => $this->source->getId(),
                    'type' => $source->getType(),
                    'source' => $source->getId(),
                    'value' => 0,
                ),
                'value' => $this->t('send_now'),
            ),
        );

        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent as
     * concept.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The Source for which the invoice was sent as concept.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getConceptFields(Source $source)
    {
        $fields = array();
        $fields += array(
            'send' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
                'ajax' => array(
                    'service' => 'invoice_add',
                    'parent_type' => $this->source->getType(),
                    'parent_source' => $this->source->getId(),
                    'type' => $source->getType(),
                    'source' => $source->getId(),
                    'value' => 1,
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * a communication error occurred in retrieving the entry.
     *
     * @param \Siel\Acumulus\Web\Result $result
     *   The result that details the error.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getCommunicationErrorFields(Result $result)
    {
        $fields = array();
        $fields += array(
            'messages' => array(
                'type' => 'markup',
                'label' => $this->t('messages'),
                'value' => $result->getMessages(Result::Format_FormattedText),
            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * does no longer exist.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The Source for which the invoice does no longer exist.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getNonExistingFields(Source $source)
    {
        $fields = array();
        $fields += array(
            'send' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
                'ajax' => array(
                    'service' => 'invoice_add',
                    'parent_type' => $this->source->getType(),
                    'parent_source' => $this->source->getId(),
                    'type' => $source->getType(),
                    'source' => $source->getId(),
                    'value' => 1,
                ),
            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * subsequently has been deleted in Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *   The Source for which the invoice has been deleted.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getDeletedFields(Source $source)
    {
        $fields = array();
        $fields += array(
            'undelete' => array(
                'type' => 'button',
                'value' => $this->t('undelete'),
                'ajax' => array(
                    'service' => 'entry_deletestatus_set',
                    'parent_type' => $this->source->getType(),
                    'parent_source' => $this->source->getId(),
                    'type' => $source->getType(),
                    'source' => $source->getId(),
                    'value' => API::Entry_Delete,
                ),
            ),
            'send' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
                'ajax' => array(
                    'service' => 'invoice_add',
                    'parent_type' => $this->source->getType(),
                    'parent_source' => $this->source->getId(),
                    'type' => $source->getType(),
                    'source' => $source->getId(),
                    'value' => 1,
                ),
            ),
        );
        return $fields;
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
    private function getEntryFields(Source $source, array $entry)
    {
        $fields = $this->getVatTypeField($entry)
            + $this->getAmountFields($source, $entry)
            + $this->getPaymentStatusFields($source, $entry)
            + $this->getLinksField($entry['token']);

        return $fields;
    }

    /**
     * Returns the vat type field.
     *
     * @param array $entry
     *
     * @return array
     *    The vattype field.
     */
    private function getVatTypeField(array $entry)
    {
        if (!empty($entry['vatreversecharge'])) {
            if (!empty($entry['foreigneu'])) {
                $vatType = API::VatType_EuReversed;
            } else {
                $vatType = API::VatType_NationalReversed;
            }
        } elseif (!empty($entry['marginscheme'])) {
            $vatType = API::VatType_MarginScheme;
        } elseif (!empty($entry['foreignvat'])) {
            $vatType = API::VatType_ForeignVat;
        } elseif (!empty($entry['foreignnoneu'])) {
            $vatType = API::VatType_RestOfWorld;
        } else {
            $vatType = API::VatType_National;
        }
        return array(
            'vat_type' => array(
            'type' => 'markup',
            'label' => $this->t('vat_type'),
            'value' => $this->t('vat_type_' . $vatType),
            ),
        );
    }

    /**
     * Returns the payment status and date (if status is paid) of the invoice.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param array $entry
     *
     * @return array[]
     *   array with form fields with the payment status and date (if paid) of
     *   the invoice.
     */
    private function getPaymentStatusFields(Source $source, array $entry)
    {
        $fields = array();
        $paymentStatus = $entry['paymentstatus'];
        $paymentDate = $entry['paymentdate'];

        $paymentStatusText = $paymentStatus !== 0 ? ('payment_status_' . $paymentStatus) : 'unknown';
        if ($paymentStatus === API::PaymentStatus_Paid && !empty($paymentDate)) {
            $paymentStatusText .= '_date';
        }
        $paymentStatusText = sprintf($this->t($paymentStatusText), $paymentDate);

        $localPaymentStatus = $source->getPaymentStatus();
        if ($localPaymentStatus !== $paymentStatus) {
            $paymentCompareStatus = $paymentStatus === API::PaymentStatus_Paid ? static::Status_Warning : static::Status_Info;
            $paymentCompareStatustext = $this->t('payment_status_not_equal');
            $this->setStatus($paymentCompareStatus, $paymentCompareStatustext);
        } else {
            $paymentCompareStatus = static::Status_Success;
        }

        $paymentStatusMarkup = sprintf('<span class="notice-%s">%s</span>', $this->getStatusClass($paymentCompareStatus), $paymentStatusText);
        $fields['payment_status'] = array(
            'type' => 'markup',
            'label' => $this->t('payment_status'),
            'value' => $paymentStatusMarkup,
        );
        if (!empty($paymentCompareStatustext)) {
            $fields['payment_status'] = array_merge_recursive($fields['payment_status'],
                array(
                    'attributes' => array(
                        'title' => $paymentCompareStatustext,
                    ),
                )
            );
        }

        if ($paymentStatus === API::PaymentStatus_Paid) {
            $fields['set_paid'] = array(
                'type' => 'button',
                'value' => $this->t('set_due'),
                'ajax' => array(
                    'service' => 'invoice_paymentstatus_set',
                    'parent_type' => $this->source->getType(),
                    'parent_source' => $this->source->getId(),
                    'type' => $source->getType(),
                    'source' => $source->getId(),
                    'value' => Api::PaymentStatus_Due,
                ),
            );
        } else {
            $fields['payment_date'] = array(
                'type' => 'date',
                'label' => $this->t('payment_date'),
                'attributes' => array(
                    'placeholder' => $this->t('date_format'),
                    'class' => 'acumulus-ajax-data',
                ),
                'default' => date(API::DateFormat_Iso),
            );
            $fields['set_paid'] = array(
                'type' => 'button',
                'value' => $this->t('set_paid'),
                'ajax' => array(
                    'service' => 'invoice_paymentstatus_set',
                    'parent_type' => $this->source->getType(),
                    'parent_source' => $this->source->getId(),
                    'type' => $source->getType(),
                    'source' => $source->getId(),
                    'value' => Api::PaymentStatus_Paid,
                ),
            );
        }

        return $fields;
    }

    /**
     * Returns the amounts of this invoice.
     *
     * To check if the amounts match we have to treat local and foreign vat the
     * same, which Acumulus doesn't.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param array $entry
     *
     * @return array[]
     *   Array with form fields with the invoice amounts.
     */
    private function getAmountFields(Source $source, array $entry)
    {
        $fields = array();
        if (!empty($entry['totalvalue']) && !empty($entry['totalvalueexclvat'])) {
            // Get Acumulus amounts.
            $amountExAcumulus = $entry['totalvalueexclvat'];
            $amountIncAcumulus = $entry['totalvalue'];
            $amountVatAcumulus = $amountIncAcumulus - $amountExAcumulus;
            $amountForeignVatAcumulus = $entry['totalvalueforeignvat'];
            if (!Number::isZero($amountForeignVatAcumulus)) {
                $vatType = Number::isZero($amountVatAcumulus)
                    ? $this->t('foreign_vat')
                    : $this->t('foreign_national_vat');
                $amountExAcumulus -= $amountForeignVatAcumulus;
                $amountVatAcumulus += $amountForeignVatAcumulus;
            } else {
                $vatType = $this->t('vat');
            }

            // Get local amounts.
            $localTotals = $source->getTotals();

            // Compare.
            $amountExStatus = $this->getAmountStatus($amountExAcumulus, $localTotals[Meta::InvoiceAmount]);
            $amountIncStatus = $this->getAmountStatus($amountIncAcumulus, $localTotals[Meta::InvoiceAmountInc]);
            $amountVatStatus = $this->getAmountStatus($amountVatAcumulus, $localTotals[Meta::InvoiceVatAmount]);

            $amountEx = $this->getFormattedAmount($amountExAcumulus, $amountExStatus);
            $amountInc = $this->getFormattedAmount($amountIncAcumulus, $amountIncStatus);
            $amountVat = $this->getFormattedAmount($amountVatAcumulus, $amountVatStatus);

            $fields['invoice_amount'] = array(
                'type' => 'markup',
                'label' => $this->t('invoice_amount'),
                'value' => sprintf('<div class="acumulus-amount">%1$s%2$s %4$s%3$s</div>', $amountEx, $amountVat, $amountInc, $vatType),
            );
        }
        return $fields;
    }

    /**
     * Returns the status of an amount by comparing it with its local value.
     *
     * If the amounts differ:
     * - < 0.5 cent, they are considered equal and 'success' will be returned.
     * - < 2 cents, it is considered a mere rounding error and 'info' will be returned.
     * - < 5 cents, it is considered a probable error and 'warning' will be returned.
     * - >= 5 cents, it is considered an error and 'error' will be returned.
     *
     * @param float $amount
     * @param float $amountLocal
     *
     * @return int
     *   One of the Status_... constants.
     */
    private function getAmountStatus($amount, $amountLocal)
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
     * @param float $amount
     * @param int $status
     *   One of the Status_... constants.
     *
     * @return string
     *   An html string representing the amount and its status.
     */
    private function getFormattedAmount($amount, $status)
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
        // Prevents warning "There should be a space between attribute ...
        $wrapperBegin = "<span class=\"amount notice-$statusClass\"" . $statusMessage . '>';
        $result = $wrapperBegin . $result . '</span>';
        return $result;
    }

    /**
     * Returns links to the invoice and packing slip documents.
     *
     * @param string $token
     *
     * @return array[]
     *   Array with form field that contains links to documents related to this
     *   invoice.
     */
    private function getLinksField($token)
    {
        $uri = $this->service->getInvoicePdfUri($token);
        $text = ucfirst($this->t('invoice'));
        $title = sprintf($this->t('open_as_pdf'), $text);
        /** @noinspection HtmlUnknownTarget */
        $invoiceLink = sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>', $uri, $text, $title, 'pdf');

        $uri = $this->service->getPackingSlipUri($token);
        $text = ucfirst($this->t('packing_slip'));
        $title = sprintf($this->t('open_as_pdf'), $text);
        /** @noinspection HtmlUnknownTarget */
        $packingSlipLink = sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>', $uri, $text, $title, 'pdf');

        $fields = array(
            'links' => array(
                'type' => 'markup',
                'label' => $this->t('documents'),
                'value' => "$invoiceLink $packingSlipLink",
            ),
        );
        return $fields;
    }

    /**
     * Returns a formatted date.
     *
     * @param \DateTime $date
     *
     * @return string
     */
    private function getDate($date)
    {
        return $date->format(API::DateFormat_Iso);
    }

    /**
     * Returns a prefix for ids and names to make them unique if multiple
     * invoices (an order and its credit notes) are shown at the same time.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     *
     * @return string
     */
    private function getIdPrefix(Source $source)
    {
        return strtolower($source->getType()) . '_' . $source->getId() . '_';
    }

    /**
     * Adds a prefix to all keys in the set of $fields.
     *
     * This is done to ensure unique id's in case of repeating fieldsets.
     *
     * @param array[] $fields
     * @param string $idPrefix
     *
     * @return array[]
     *
     */
    private function addIdPrefix($fields, $idPrefix)
    {
        $result = array();
        foreach ($fields as $key => $field) {
            $newKey = $idPrefix . $key;
            $result[$newKey] = $field;
            if (isset($field['fields'])) {
                $result[$newKey]['fields'] = $this->addIdPrefix($field['fields'], $newKey . '_');
            }
        }
        return $result;
    }

    /**
     * Sanitizes an entry struct received via a getEntry API call.
     *
     * The info received from an external API call should not be trusted, so it
     * should be sanitized. As most info from this API call is placed in markup
     * fields we cannot rely on the FormRenderer or the webshop's form API
     * (which do not sanitize markup fields).
     *
     * So we sanitize the values in the struct itself before using them:
     * - Int, float, and bool fields are cast to their proper type.
     * - Date strings are parsed to a DateTime and formatted back to a date
     *   string.
     * - Strings that can only contain a restricted set of values are checked
     *   against that set and emptied if not part of it.
     * - Free string values are escaped to save html.
     * - Keys we don't use are not returned. This keeps the output safe when a
     *   future API version returns additional fields and we forget to sanitize
     *   it and thus use it non sanitised.
     *
     * Keys in $entry array (* are sanitized):
     *   * token
     *   * entryid
     *   * entrydate: yy-mm-dd
     *   - entrytype
     *   - entrydescription
     *   - entrynote
     *   - fiscaltype
     *   * vatreversecharge: 0 or 1
     *   * foreigneu: 0 or 1
     *   * foreignnoneu: 0 or 1
     *   * marginscheme: 0 or 1
     *   * foreignvat: 0 or 1
     *   - contactid
     *   - accountnumber
     *   - costcenterid
     *   - costtypeid
     *   * invoicenumber
     *   - invoicenote
     *   - descriptiontext
     *   - invoicelayoutid
     *   - paymenttoken
     *   * totalvalueexclvat
     *   * totalvalue
     *   * totalvalueforeignvat
     *   - paymenttermdays
     *   * paymentdate: yy-mm-dd
     *   * paymentstatus: 1 or 2
     *   * deleted: timestamp
     *
     * @param array $entry
     *
     * @return mixed
     *   The sanitized entry struct.
     */
    private function sanitizeEntry(array $entry)
    {
        if (!empty($entry)) {
            $result = array();
            $result['entryid'] = $this->sanitizeIntValue($entry, 'entryid');
            $result['token'] = $this->sanitizeStringValue($entry, 'token', '/^[0-9a-zA-Z]{32}$/');
            $result['entrydate'] = $this->sanitizeDateValue($entry, 'entrydate');
            $result['vatreversecharge'] = $this->sanitizeBoolValue($entry, 'vatreversecharge');
            $result['foreigneu'] = $this->sanitizeBoolValue($entry, 'foreigneu');
            $result['foreignnoneu'] = $this->sanitizeBoolValue($entry, 'foreignnoneu');
            $result['marginscheme'] = $this->sanitizeBoolValue($entry, 'marginscheme');
            $result['foreignvat'] = $this->sanitizeBoolValue($entry, 'foreignvat');
            $result['invoicenumber'] = $this->sanitizeIntValue($entry, 'invoicenumber');
            $result['totalvalueexclvat'] = $this->sanitizeFloatValue($entry, 'totalvalueexclvat');
            $result['totalvalue'] = $this->sanitizeFloatValue($entry, 'totalvalue');
            $result['totalvalueforeignvat'] = $this->sanitizeFloatValue($entry, 'totalvalueforeignvat');
            $result['paymentstatus'] = $this->sanitizeIntValue($entry, 'paymentstatus');
            $result['paymentdate'] = $this->sanitizeDateValue($entry, 'paymentdate');
            $result['deleted'] = $this->sanitizeDateTimeValue($entry, 'deleted');
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Sanitizes an concept info struct received via a getConceptInfo API call.
     *
     * The info received from an external API call should not be trusted, so it
     * should be sanitized. As most info from this API call is placed in markup
     * fields we cannot rely on the FormRenderer or the webshop's form API as
     * these do not sanitize markup fields.
     *
     * So we sanitize the values in the struct itself before using them:
     * - Int, float, and bool fields are cast to their proper type.
     * - Date strings are parsed to a DateTime and formatted back to a date
     *   string.
     * - Strings that can only contain a restricted set of values are checked
     *   against that set and emptied if not part of it.
     * - Free string values are escaped to save html.
     * - Keys we don't use are not returned. This keeps the output safe when a
     *   future API version returns additional fields and we forget to sanitize
     *   it and thus use it non sanitised.
     *
     * Keys in $entry array:
     *   - conceptid: int
     *   - entryid: int|int[]
     *
     * @param array $conceptInfo
     *
     * @return array|null
     *   The sanitized entry struct.
     */
    private function sanitizeConceptInfo(array $conceptInfo)
    {
        if (!empty($conceptInfo)) {
            $result = array();
            $result['conceptid'] = $this->sanitizeIntValue($conceptInfo, 'conceptid');
            $result['entryid'] = $this->sanitizeIntValue($conceptInfo, 'entryid', true);
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
    private function sanitizeStringValue(array $entry, $key, $additionalRestriction = null)
    {
        $result = '';
        if (!empty($entry[$key])) {
            $value = $entry[$key];
            if (is_string($additionalRestriction)) {
                if (preg_match($additionalRestriction, $value)) {
                    $result = $value;
                }
            } elseif (is_array($additionalRestriction)) {
                if (in_array($value, $additionalRestriction)) {
                    $result = $value;
                }
            } else {
                $result = htmlspecialchars($value, ENT_NOQUOTES);
            }
        }
        return $result;
    }

    /**
     * Returns a sanitized integer value of an entry record.
     *
     * @param array $entry
     * @param string $key
     * @param bool $allowArray
     *
     * @return int|int[]
     *   The int value of the value under this key or 0 if not provided. If
     *   $allowArray is set, an empty array is returned, if no value is set.
     */
    private function sanitizeIntValue(array $entry, $key, $allowArray = false)
    {
        if (isset($entry[$key])) {
            if ($allowArray && is_array($entry[$key])) {
                $result = array();
                foreach ($entry[$key] as $value) {
                    $result[] = (int) $value;
                }
            } else {
                $result = (int) $entry[$key];
            }
        } else {
            $result = $allowArray ? array() : 0;
        }
        return $result;
    }

    /**
     * Returns a sanitized float value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return int
     *   The float value of the value under this key.
     */
    private function sanitizeFloatValue(array $entry, $key)
    {
        return !empty($entry[$key]) ? (float) $entry[$key] : 0.0;
    }

    /**
     * Returns a sanitized bool value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return bool
     *   The bool value of the value under this key. True values are represented
     *   by 1, false values by 0.
     */
    private function sanitizeBoolValue(array $entry, $key)
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        return isset($entry[$key]) && $entry[$key] == 1;
    }

    /**
     * Returns a sanitized date value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return string
     *   The date value (yyyy-mm-dd) of the value under this key or the empty
     *   string, if the string is not in the valid date format (yyyy-mm-dd).
     */
    private function sanitizeDateValue(array $entry, $key)
    {
        $date = '';
        if (!empty($entry[$key])) {
            $date = DateTime::createFromFormat(API::DateFormat_Iso, $entry[$key]);
            if ($date instanceof DateTime) {
                $date = $date->format(Api::DateFormat_Iso);
            } else {
                $date = '';
            }
        }
        return $date;
    }

    /**
     * Returns a sanitized date time value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return DateTime|null
     *   The date time value of the value under this key or null if the string
     *   is not in the valid date-time format (yyyy-mm-dd hh:mm:ss).
     *   Note that the API might return 0000-00-00 00:00:00 which should not be
     *   accepted.
     */
    private function sanitizeDateTimeValue(array $entry, $key)
    {
        $timeStamp = null;
        if (!empty($entry[$key])) {
            $timeStamp = DateTime::createFromFormat(API::Format_TimeStamp, $entry[$key]);
            if (!$timeStamp instanceof DateTime || $timeStamp->getTimestamp() < 0) {
                $timeStamp = null;
            }
        }
        return $timeStamp;
    }
}
