<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

use function count;

/**
 * Extends Result with properties and features specific to the InvoiceAdd web
 * service call.
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class InvoiceAddResult extends MessageCollection
{
    // Whether to add the raw request and response to mails or log messages.
    public const AddReqResp_Never = 1;
    public const AddReqResp_Always = 2;
    public const AddReqResp_WithOther = 3;

    // Invoice send handling related constants.
    public const SendStatus_Unknown = 0;
    // Reasons for not sending.
    public const NotSent_AlreadySent = 0x1;
    public const NotSent_WrongStatus = 0x2;
    public const NotSent_EmptyInvoice = 0x3;
    public const NotSent_TriggerInvoiceCreateNotEnabled = 0x4;
    public const NotSent_TriggerInvoiceSentNotEnabled = 0x5;
    public const NotSent_LocalErrors = 0x6;
    public const NotSent_DryRun = 0x7;
    public const NotSent_TriggerCreditNoteEventNotEnabled = 0x8;
    public const NotSent_AlreadyLocked = 0x9;
    public const NotSent_LockNotAcquired = 0xa;
    public const NotSent_NoInvoiceLines = 0xb;
    public const NotSent_EventInvoiceCreateBefore = 0xc;
    public const NotSent_EventInvoiceCreateAfter = 0xd;
    public const NotSent_EventInvoiceSendBefore = 0xe;
    public const NotSent_Mask = 0xf;
    // Reasons for sending.
    public const Sent_New = 0x10;
    public const Sent_Forced = 0x20;
    public const Sent_TestMode = 0x30;
    public const Sent_LockExpired = 0x40;
    public const Send_Mask = 0xf0;

    /**
     * A string indicating the function that triggered the sending, e.g.
     * {@see \Siel\Acumulus\Magento\Invoice\SourceInvoiceManager::sourceStatusChange()}.
     */
    protected string $trigger;
    /**
     * A status indicating if and why an invoice was or was not sent. It will
     * contain 1 of the {@see InvoiceAddResult}::Send_... or
     * {@see InvoiceAddResult}::NotSent_... constants.
     */
    protected int $sendStatus;
    /**
     * A list of parameters to use when getting the send-status as text.
     */
    protected array $sendStatusArguments;
    /**
     * @var \Siel\Acumulus\Data\Invoice|null
     *   The invoice that is (attempted to) being sent to Acumulus, or null if not yet
     *   set.
     */
    protected ?Invoice $invoice = null;
    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusResult|null
     *   The API result of sending the invoice to Acumulus, null if not yet sent or if
     *   sending is prevented
     */
    protected ?AcumulusResult $acumulusResult = null;

    /**
     * InvoiceAddResult constructor.
     *
     * @param string $trigger
     *   A string indicating the function that triggered the sending, e.g.
     *   'InvoiceManager::sourceStatusChange()'.
     */
    public function __construct($trigger, Translator $translator)
    {
        parent::__construct($translator);
        $this->trigger = $trigger;
        $this->sendStatus = self::SendStatus_Unknown;
        $this->sendStatusArguments = [];
    }

    /**
     * @return int
     *   A status indicating if and why an invoice was sent or not sent. It will
     *   contain 1 of the {@see InvoiceAddResult}::Send_... or
     *   {@see InvoiceAddResult}::NotSent_... constants.
     */
    public function getSendStatus(): int
    {
        return $this->sendStatus;
    }

    /**
     * @param int $sendStatus
     *   A status indicating if and why an invoice was sent or not sent. It will
     *   contain 1 of the Result::Sent_... or Result::Invoice_NotSent_...
     *   constants.
     * @param array $arguments
     *   A list of parameters to use when getting the send-status as text.
     *
     * @return $this
     */
    public function setSendStatus(int $sendStatus, array $arguments = []): InvoiceAddResult
    {
        $this->sendStatus = $sendStatus;
        $this->sendStatusArguments = $arguments;
        return $this;
    }

    /**
     * Returns whether the invoice has been sent.
     *
     * @return bool
     *   True if the invoice has been sent, false if sending was prevented or
     *   if the sendStatus has not yet been set.
     */
    public function hasBeenSent(): bool
    {
        return ($this->sendStatus & self::Send_Mask) !== 0;
    }

    /**
     * Returns whether the invoice has been prevented from sending.
     *
     * @return bool
     *   True if the invoice has been prevented from sensing, false if it has
     *   been sent or if the sendStatus has not yet been set.
     */
    public function isSendingPrevented(): bool
    {
        return ($this->sendStatus & self::NotSent_Mask) !== 0;
    }

    /**
     * @return string
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     */
    public function getTrigger(): string
    {
        return $this->trigger;
    }

    /**
     * @param string $trigger
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function setTrigger(string $trigger): InvoiceAddResult
    {
        $this->trigger = $trigger;
        return $this;
    }


    /**
     * Returns a translated string indicating the action taken (sent or not sent).
     */
    protected function getActionText(): string
    {
        if ($this->hasBeenSent()) {
            $action = 'action_sent';
        } elseif ($this->isSendingPrevented()) {
            $action = 'action_not_sent';
        } else {
            $action = 'action_unknown';
        }
        return $this->t($action);
    }

    /**
     * Returns a translated string indicating the reason for the action taken.
     */
    protected function getSendStatusText(): string
    {
        $messages = [
            self::NotSent_AlreadySent => 'reason_not_sent_alreadySent',
            self::NotSent_AlreadyLocked => 'reason_not_sent_alreadySending',
            self::NotSent_LockNotAcquired => 'reason_not_sent_lockNotAcquired',
            self::NotSent_EventInvoiceCreateAfter => 'reason_not_sent_prevented_invoiceCreated',
            self::NotSent_EventInvoiceSendBefore => 'reason_not_sent_prevented_invoiceCompleted',
            self::NotSent_EmptyInvoice => 'reason_not_sent_empty_invoice',
            self::NotSent_NoInvoiceLines => 'reason_not_sent_no_invoice_lines',
            self::NotSent_TriggerInvoiceCreateNotEnabled => 'reason_not_sent_not_enabled_triggerInvoiceCreate',
            self::NotSent_TriggerInvoiceSentNotEnabled => 'reason_not_sent_not_enabled_triggerInvoiceSent',
            self::NotSent_LocalErrors => 'reason_not_sent_local_errors',
            self::NotSent_DryRun => 'reason_not_sent_dry_run',
            self::Sent_TestMode => 'reason_sent_testMode',
            self::Sent_LockExpired => 'reason_sent_lock_expired',
            self::Sent_Forced => 'reason_sent_forced',
        ];
        if (isset($messages[$this->sendStatus])) {
            $message = $messages[$this->sendStatus];
        } else {
            // Send statuses that can have different messages depending on whether there
            // are send status arguments.
            switch ($this->sendStatus) {
                case self::NotSent_WrongStatus:
                    $message = count($this->sendStatusArguments) === 0
                        ? 'reason_not_sent_triggerCreditNoteEvent_None'
                        : 'reason_not_sent_wrongStatus';
                    break;
                case self::Sent_New:
                    $message = count($this->sendStatusArguments) === 0
                        ? 'reason_sent_new'
                        : 'reason_sent_new_status_change';
                    break;
                default:
                    $message = 'reason_unknown';
                    $this->sendStatusArguments = [($this->sendStatus)];
                    break;
            }
        }
        $message = $this->t($message);
        if (count($this->sendStatusArguments) !== 0) {
            $message = vsprintf($message, $this->sendStatusArguments);
        }
        return $message;
    }

    /**
     * Returns the invoice that is (attempted to) being sent to Acumulus, or null if not
     * yet set.
     */
    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }

    public function getMainApiResponse(): ?array
    {
        return $this->getAcumulusResult() !== null ? $this->getAcumulusResult()->getMainAcumulusResponse() : null ;
    }

    public function getAcumulusResult(): ?AcumulusResult
    {
        return $this->acumulusResult;
    }

    /**
     * Sets the AcumulusResult and copies its messages to this object
     */
    public function setAcumulusResult(AcumulusResult $acumulusResult): void
    {
        $this->acumulusResult = $acumulusResult;
        $this->addMessages($acumulusResult->getMessages());
    }

    /**
     * Returns a translated sentence that can be used for logging.
     *
     * The returned sentence indicates what happened and why. If the invoice was
     * sent or local errors prevented it being sent, then the returned string
     * also includes any messages.
     *
     * @param int $addReqResp
     *   Whether to add the raw request and response.
     *   One of the {@see InvoiceAddResult}::AddReqResp_... constants
     */
    public function getLogText(int $addReqResp): string
    {
        $action = $this->getActionText();
        $reason = sprintf($this->t('message_invoice_reason'), $action, $this->getSendStatusText());

        $status = '';
        $messages = '';
        $requestResponse = '';
        if ($this->hasBeenSent() || $this->getSendStatus() === self::NotSent_LocalErrors) {
            if ($this->getAcumulusResult() !== null) {
                $status = ' ' . $this->getAcumulusResult()->getStatusText();
                if ($addReqResp === self::AddReqResp_Always
                    || ($addReqResp === self::AddReqResp_WithOther && $this->hasRealMessages())
                ) {
                    $requestResponse = "\nRequest: " . $this->getAcumulusResult()->getAcumulusRequest()->getMaskedRequest()
                        . "\nResponse: " . $this->getAcumulusResult()->getMaskedResponse()
                        . "\n";
                }
            }
            if ($this->hasRealMessages()) {
                $messages = "\n" . $this->formatMessages(Message::Format_PlainListWithSeverity, Severity::RealMessages);
            }
        }

        return $reason . $status . $messages . $requestResponse;
    }
}
