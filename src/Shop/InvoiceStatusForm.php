<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
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
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Helpers\Severity;

/**
 * Defines the Acumulus invoice status overview form.
 *
 * This form is mostly informative but contains some buttons and a few fields
 * to update the invoice in Acumulus.
 *
 * SECURITY REMARKS
 * ----------------
 * The info received from an external API call should not be trusted, so it
 * should be sanitized. As most info from this API call is placed in markup
 * fields we cannot rely on the FormRenderer or the webshop's form API
 * (who do not sanitize markup fields).
 *
 * This form uses ajax calls, values received from an ajax call are to be
 * treated as user input and thus should be sanitized and checked as all user
 * input.
 */
class InvoiceStatusForm extends Form
{
    // Constants representing the status of the Acumulus invoice for a given
    // shop order or refund.
    const Invoice_NotSent = 'invoice_not_sent';
    const Invoice_Sent = 'invoice_sent';
    const Invoice_SentConcept  = 'invoice_sent_concept';
    const Invoice_SentConceptNoInvoice  = 'invoice_sent_concept_no_invoice';
    const Invoice_Deleted = 'invoice_deleted';
    const Invoice_NonExisting = 'invoice_non_existing';
    const Invoice_CommunicationError = 'invoice_communication_error';
    const Invoice_LocalError = 'invoice_local_error';

    const Status_Unknown = 0;
    const Status_Success = 1;
    const Status_Info = 2;
    const Status_Warning = 3;
    const Status_Error = 4;

    /** @var \Siel\Acumulus\Helpers\Container*/
    protected $container;

    /** @var \Siel\Acumulus\ApiClient\Acumulus */
    protected $acumulusApiClient;

    /** @var \Siel\Acumulus\Shop\InvoiceManager */
    protected $invoiceManager;

    /** @var \Siel\Acumulus\Shop\AcumulusEntryManager */
    protected $acumulusEntryManager;

    /**
     * The main Source for this form.
     *
     * This form can handle an order and its credit notes at the same time, the
     * order being the "main" source.
     *
     * @var \Siel\Acumulus\Invoice\Source
     */
    protected $source;

    /**
     * One of the Result::Status_... constants.
     *
     * @var int
     */
    protected $status;

    /**
     * A message indicating why the status is not OK..
     *
     * @var string
     */
    protected $statusMessage;

    /**
     * @param \Siel\Acumulus\Shop\InvoiceManager $invoiceManager
     * @param \Siel\Acumulus\Shop\AcumulusEntryManager $acumulusEntryManager
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\ApiClient\Acumulus $acumulusApiClient
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(
        InvoiceManager $invoiceManager,
        AcumulusEntryManager $acumulusEntryManager,
        Container $container,
        Acumulus $acumulusApiClient,
        FormHelper $formHelper,
        ShopCapabilities $shopCapabilities,
        Config $config,
        Translator $translator,
        Log $log
    )
    {
        parent::__construct($formHelper, $shopCapabilities, $config, $translator, $log);
        $this->addMeta = false;

        $translations = new InvoiceTranslations();
        $this->translator->add($translations);

        $translations = new InvoiceStatusFormTranslations();
        $this->translator->add($translations);

        $this->container = $container;
        $this->acumulusEntryManager = $acumulusEntryManager;
        $this->invoiceManager = $invoiceManager;
        $this->acumulusApiClient = $acumulusApiClient;
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
     * @return bool
     *   Whether the form has a source set.
     *
     * @noinspection PhpUnused
     */
    public function hasSource()
    {
        return $this->source !== null;
    }

    /**
     * Sets the status, but only if it is "worse" than the current status.
     *
     * @param int $status
     *   The status to set.
     * @param string $message
     *   Optionally, a message indicating what is wrong may be given.
     */
    protected function setStatus($status, $message = '')
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
    protected function resetStatus()
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
    protected function getStatusIcon($status)
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
     * @param int $status
     * @param string $statusMessage
     *
     * @return array
     *   A set of attributes to add to the label.
     */
    protected function getStatusLabelAttributes($status, $statusMessage)
    {
        $statusClass = $this->getStatusClass($status);
        $attributes = array(
            'class' => array('notice', 'notice-' . $statusClass),
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
    protected function getAmountStatusTitle($status)
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
    public function isSubmitted()
    {
        return parent::isSubmitted() && isset($_POST['clicked']);
    }

    /**
     * @inheritDoc
     *
     * This override adds sanitation to the values and already combines some of
     * the values to retrieve a Source object
     */
    protected function setSubmittedValues()
    {
        parent::setSubmittedValues();

        // Split the service, as it is prefixed with the source type and id, it
        // looks like "Type_id_service".
        $this->setServiceAndSubmittedSource();
    }

    /**
     * Extracts the source on which the submitted action is targeted.
     */
    protected function setServiceAndSubmittedSource()
    {
        // Get parent source. The action may be on one of its children, a credit
        // note, but we also have to set the parent source,so we can fully
        // process and render the form.
        $parentType = $this->getSubmittedValue('acumulus_parent_type');
        $parentId = (int) $this->getSubmittedValue('acumulus_parent_id');
        $parentSource = $this->container->getSource($parentType, $parentId);
        if ($parentSource->getSource()) {
            $this->setSource($parentSource);
        }

        // Get actual source ($this->source may be the parent, not the source to
        // execute on). Do so without trusting the input.
        $parts = explode('_', $this->submittedValues['clicked'], 3);
        if (count($parts) === 3) {
            $this->submittedValues['service'] = $parts[2];
            $this->submittedValues['source_type'] = $parts[0];
            $this->submittedValues['source_id'] = (int) $parts[1];
            $this->submittedValues['source'] = null;
            if ($this->source->getType() === $this->getSubmittedValue('source_type')
                && $this->source->getId() === $this->getSubmittedValue('source_id')) {
                $this->submittedValues['source'] = $this->source;
            } else {
                $creditNotes = $this->source->getCreditNotes();
                foreach ($creditNotes as $creditNote) {
                    if ($creditNote->getType() === $this->getSubmittedValue('source_type')
                        && $creditNote->getId() ===$this->getSubmittedValue('source_id')) {
                        $this->submittedValues['source'] = $creditNote;
                    }
                }
            }
        } else {
            $this->submittedValues['service'] = '';
            $this->submittedValues['source_type'] = 'unknown';
            $this->submittedValues['source_id'] = 'unknown';
            $this->submittedValues['source'] = null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function validate()
    {
        if ($this->source === null) {
            // Use a basic filtering on the wrong user input.
            $this->addMessage(sprintf($this->t('unknown_source'),
                    preg_replace('/[^a-z0-9_\-]/', '', $this->getSubmittedValue('acumulus_parent_type')),
                    preg_replace('/[^a-z0-9_\-]/', '', $this->getSubmittedValue('acumulus_parent_id'))),
                Severity::Error);
        } elseif ($this->getSubmittedValue('source') === null) {
            // Use a basic filtering on the wrong user input.
            $this->addMessage(sprintf($this->t('unknown_source'),
                    preg_replace('/[^a-z0-9_\-]/', '', $this->getSubmittedValue('source_type')),
                    preg_replace('/[^a-z0-9_\-]/', '', $this->getSubmittedValue('source_id'))),
                Severity::Error);
        } elseif ($this->getSubmittedValue('service') === 'invoice_paymentstatus_set') {
            /** @var Source $source */
            $source = $this->getSubmittedValue('source');
            $idPrefix = $this->getIdPrefix($source);
            if ((int) $this->getSubmittedValue($idPrefix . 'payment_status_new') === Api::PaymentStatus_Paid) {
                $dateFieldName = $idPrefix . 'payment_date';
                if (!DateTime::createFromFormat(API::DateFormat_Iso, $this->getSubmittedValue($dateFieldName))) {
                    // Date is not a valid date.
                    $this->addMessage(sprintf($this->t('message_validate_batch_bad_payment_date'), $this->t('date_format')), Severity::Error, $dateFieldName);
                }
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
        /** @var Source $source */
        $source = $this->getSubmittedValue('source');
        $idPrefix = $this->getIdPrefix($source);
        switch ($service) {
            case 'invoice_add':
                $forceSend = (bool) $this->getSubmittedValue($idPrefix . 'force_send');
                $result = $this->invoiceManager->send1($source, $forceSend);
                break;

            case 'invoice_paymentstatus_set':
                $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);
                if ($localEntryInfo) {
                    if ((int) $this->getSubmittedValue($idPrefix . 'payment_status_new') === Api::PaymentStatus_Paid) {
                        $paymentStatus = Api::PaymentStatus_Paid;
                        $paymentDate =$this->getSubmittedValue($idPrefix . 'payment_date');
                    } else {
                        $paymentStatus = Api::PaymentStatus_Due;
                        $paymentDate = '';
                    }
                    $result = $this->acumulusApiClient->setPaymentStatus($localEntryInfo->getToken(), $paymentStatus, $paymentDate);
                } else {
                    $this->addMessage(
                        sprintf($this->t('unknown_entry'), strtolower($this->t($source->getType())),$source->getId()),
                        Severity::Error);
                }
                break;

            case 'entry_deletestatus_set':
                $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);
                if ($localEntryInfo && $localEntryInfo->getEntryId() !== null) {
                    $deleteStatus = $this->getSubmittedValue($idPrefix . 'delete_status') === Api::Entry_Delete ? Api::Entry_Delete : Api::Entry_UnDelete;
                    // @todo: clean up on receiving P2XFELO12?
                    $result = $this->acumulusApiClient->setDeleteStatus($localEntryInfo->getEntryId(), $deleteStatus);
                } else {
                    $this->addMessage(
                        sprintf($this->t('unknown_entry'), strtolower($this->t($source->getType())), $source->getId()),
                        Severity::Error);
                }
                break;

            default:
                // Use a basic filtering on the wrong user input.
                $this->addMessage(
                    sprintf($this->t('unknown_action'), preg_replace('/[^a-z0-9_\-]/', '', $service)),
                    Severity::Error);
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
        $parent = $this->source;

        // Add base information in hidden fields:
        // - Source (type and id) for the main source on this form. This will be
        //   an order. Fieldsets with children, credit notes, may follow.
        $fields['acumulus_parent_type'] = $this->getHiddenField($parent->getType());
        $fields['acumulus_parent_id'] = $this->getHiddenField($parent->getId());

        // 1st fieldset: Order.
        $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($parent);
        $idPrefix = $this->getIdPrefix($parent);
        $fields1Source = $this->addIdPrefix($this->getFields1Source($parent, $localEntryInfo), $idPrefix);
        $fields[$idPrefix] = array(
            'type' => 'fieldset',
            'fields' => $fields1Source,
        );

        // Other fieldsets: credit notes.
        $creditNotes = $parent->getCreditNotes();
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
    protected function getFields1Source(Source $source, $localEntryInfo)
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
        return array(
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
     *   - result (\Siel\Acumulus\ApiClient\Result?): result of the getEntry API call.
     *   - entry (array|null): the <entry> part of the getEntry API call.
     *   - statusField (array): a form field array representing the status.
     */
    protected function getInvoiceStatusInfo(Source $source, &$localEntryInfo)
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
                if ($localEntryInfo->getConceptId() === AcumulusEntry::conceptIdUnknown) {
                    // Old entry: no concept id stored, we cannot show more
                    // information.
                    $invoiceStatus = static::Invoice_SentConcept;
                    $description = 'concept_no_conceptid';
                    $statusSeverity = static::Status_Warning;
                } else {
                    // Entry saved with support for concept ids.
                    // Has the concept been changed into an invoice?
                    $result = $this->acumulusApiClient->getConceptInfo($localEntryInfo->getConceptId());
                    $conceptInfo = $this->sanitizeConceptInfo($result->getResponse());
                    if (empty($conceptInfo)) {
                        $invoiceStatus = static::Invoice_CommunicationError;
                        $statusSeverity = static::Status_Error;
                    } elseif ($result->getByCodeTag('FGYBSN040') || $result->getByCodeTag('FGYBSN048')) {
                        // FGYBSN040: concept id does not exist (anymore) or no access.
                        // FGYBSN048: concept id to old, cannot be tracked.
                        $invoiceStatus = static::Invoice_SentConcept;
                        $statusSeverity = static::Status_Warning;
                        $description = $result->getByCodeTag('FGYBSN040') ? 'concept_conceptid_deleted' : $description = 'concept_no_conceptid';
                        // Prevent this API call in the future, it will return
                        // the same result.
                        $this->acumulusEntryManager->save($source, null, null);
                    } elseif (empty($conceptInfo['entryid'])) {
                        // Concept has not yet been turned into a definitive
                        // invoice.
                        $invoiceStatus = static::Invoice_SentConceptNoInvoice;
                        $statusSeverity = static::Status_Warning;
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
                        $result = $this->acumulusApiClient->getEntry($conceptInfo['entryid']);
                        $entry = $this->sanitizeEntry($result->getResponse());
                        if (!$result->hasError() && !empty($entry['token'])) {
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
                $result = $this->acumulusApiClient->getEntry($localEntryInfo->getEntryId());
                $entry = $this->sanitizeEntry($result->getResponse());
                if ($result->getByCodeTag('XGYBSN000')) {
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
            } elseif (empty($invoiceStatus)) {
                $invoiceStatus = static::Invoice_LocalError;
                $statusSeverity = static::Status_Error;
                $description = 'entry_concept_noid';
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
     * @return array[]
     *   Array of form fields.
     */
    protected function getNotSentFields()
    {
        $fields = array();
        $fields += array(
            'invoice_add' => array(
                'type' => 'button',
                'value' => $this->t('send_now'),
                'attributes' => array(
                    'class' => 'acumulus-ajax',
                ),
            ),
            'force_send' => $this->getHiddenField(0),
        );

        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent as
     * concept.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getConceptFields()
    {
        $fields = array();
        $fields += array(
            'invoice_add' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
                'attributes' => array(
                    'class' => 'acumulus-ajax',
                ),
            ),
            'force_send' => $this->getHiddenField(1),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * a communication error occurred in retrieving the entry.
     *
     * @param \Siel\Acumulus\ApiClient\Result $result
     *   The result that details the error.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getCommunicationErrorFields(Result $result)
    {
        $fields = array();
        $fields += array(
            'messages' => array(
                'type' => 'markup',
                'label' => $this->t('messages'),
                'value' => $result->formatMessages(Message::Format_PlainListWithSeverity, Severity::RealMessages),
            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * does no longer exist.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getNonExistingFields()
    {
        $fields = array();
        $fields += array(
            'invoice_add' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
                'attributes' => array(
                    'class' => 'acumulus-ajax',
                ),
            ),
            'force_send' => $this->getHiddenField(1),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * subsequently has been deleted in Acumulus.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getDeletedFields()
    {
        $fields = array();
        $fields += array(
            'entry_deletestatus_set' => array(
                'type' => 'button',
                'value' => $this->t('undelete'),
                'attributes' => array(
                    'class' => 'acumulus-ajax',
                ),
            ),
            'delete_status' => $this->getHiddenField(API::Entry_UnDelete),
            'invoice_add' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
                'attributes' => array(
                    'class' => 'acumulus-ajax',
                ),
            ),
            'force_send' => $this->getHiddenField(1),
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
    protected function getEntryFields(Source $source, array $entry)
    {
        return $this->getVatTypeField($entry)
               + $this->getAmountFields($source, $entry)
               + $this->getPaymentStatusFields($source, $entry)
               + $this->getLinksField($entry['token']);
    }

    /**
     * Returns the vat type field.
     *
     * @param array $entry
     *
     * @return array
     *    The vattype field.
     */
    protected function getVatTypeField(array $entry)
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
    protected function getPaymentStatusFields(Source $source, array $entry)
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
            $paymentCompareStatus = static::Status_Warning;
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
            $fields['payment_status']['attributes'] = array(
                'title' => $paymentCompareStatustext,
            );
        }

        if ($paymentStatus === API::PaymentStatus_Paid) {
            $fields += array(
                'invoice_paymentstatus_set' => array(
                    'type' => 'button',
                    'value' => $this->t('set_due'),
                    'attributes' => array(
                        'class' => 'acumulus-ajax',
                    ),
                ),
                'payment_status_new' => $this->getHiddenField(Api::PaymentStatus_Due),
            );
        } else {
            $fields += array(
                'payment_date' => array(
                    'type' => 'date',
                    'label' => $this->t('payment_date'),
                    'attributes' => array(
                        'placeholder' => $this->t('date_format'),
                        'required' => true,
                    ),
                    'default' => date(API::DateFormat_Iso),
                ),
                'invoice_paymentstatus_set' => array(
                    'type' => 'button',
                    'value' => $this->t('set_paid'),
                    'attributes' => array(
                        'class' => 'acumulus-ajax',
                    ),
                ),
                'payment_status_new' => $this->getHiddenField(Api::PaymentStatus_Paid),
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
    protected function getAmountFields(Source $source, array $entry)
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
    protected function getAmountStatus($amount, $amountLocal)
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
    protected function getFormattedAmount($amount, $status)
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
    protected function getLinksField($token)
    {
        $result = array();
        $links = [];
        $invoiceStatusSettings = $this->container->getConfig()->getInvoiceStatusSettings();
        if ($invoiceStatusSettings['showPdfInvoice']) {
            $uri = $this->acumulusApiClient->getInvoicePdfUri($token);
            $text = ucfirst($this->t('invoice'));
            $title = sprintf($this->t('open_as_pdf'), $text);
            /** @noinspection HtmlUnknownTarget */
            $links[] = sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>', $uri, $text, $title, 'pdf');
        }

        if ($invoiceStatusSettings['showPdfPackingSlip']) {
            $uri = $this->acumulusApiClient->getPackingSlipUri($token);
            $text = ucfirst($this->t('packing_slip'));
            $title = sprintf($this->t('open_as_pdf'), $text);
            /** @noinspection HtmlUnknownTarget */
            $links[] = sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>', $uri, $text, $title, 'pdf');
        }

        if (!empty($links)) {
            $result['links'] = array(
                'type' => 'markup',
                'label' => $this->t(count($links) === 1 ? 'document' : 'documents'),
                'value' => implode(' ', $links),
            );
        }
        return $result;
    }

    /**
     * Returns a hidden field.
     *
     * @param mixed $value
     *
     * @return array
     */
    protected function getHiddenField($value)
    {
        return array(
            'type' => 'hidden',
            'value' => $value,
        );
    }

    /**
     * Returns a formatted date.
     *
     * @param \DateTime $date
     *
     * @return string
     */
    protected function getDate($date)
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
    protected function getIdPrefix(Source $source)
    {
        return $source->getType() . '_' . $source->getId() . '_';
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
     *   The set of fields with their ids prefixed.
     */
    protected function addIdPrefix($fields, $idPrefix)
    {
        $result = array();
        foreach ($fields as $key => $field) {
            $newKey = $idPrefix . $key;
            $result[$newKey] = $field;
            if (isset($field['fields'])) {
                $result[$newKey]['fields'] = $this->addIdPrefix($field['fields'], $idPrefix);
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
    protected function sanitizeEntry(array $entry)
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
    protected function sanitizeConceptInfo(array $conceptInfo)
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
    protected function sanitizeStringValue(array $entry, $key, $additionalRestriction = null)
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
    protected function sanitizeIntValue(array $entry, $key, $allowArray = false)
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
    protected function sanitizeFloatValue(array $entry, $key)
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
    protected function sanitizeBoolValue(array $entry, $key)
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
    protected function sanitizeDateValue(array $entry, $key)
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
     *   accepted (recognised by a negative timestamp).
     */
    protected function sanitizeDateTimeValue(array $entry, $key)
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
