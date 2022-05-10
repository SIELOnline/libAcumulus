<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

/**
 * Extends Result with properties and features specific to the InvoiceAdd web
 * service call.
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
    public const NotSent_EventInvoiceCreated = 0x1;
    public const NotSent_EventInvoiceCompleted = 0x2;
    public const NotSent_AlreadySent = 0x3;
    public const NotSent_WrongStatus = 0x4;
    public const NotSent_EmptyInvoice = 0x5;
    public const NotSent_TriggerInvoiceCreateNotEnabled = 0x6;
    public const NotSent_TriggerInvoiceSentNotEnabled = 0x7;
    public const NotSent_LocalErrors = 0x8;
    public const NotSent_DryRun = 0x9;
    public const NotSent_TriggerCreditNoteEventNotEnabled = 0xa;
    public const NotSent_LockedForSending = 0xb;
    public const NotSent_Mask = 0xf;
    // Reasons for sending
    public const Send_New = 0x10;
    public const Send_Forced = 0x20;
    public const Send_TestMode = 0x30;
    public const Send_LockExpired = 0x40;
    public const Send_Mask = 0xf0;

    /**
     * @var string
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     */
    protected $trigger;

    /**
     * @var int
     *   A status indicating if and why an invoice was or was not sent. It will
     *   contain 1 of the Result::Sent_... or Result::Invoice_NotSent_...
     *   constants.
     */
    protected $sendStatus;

    /**
     * @var array
     *   A list of parameters to use when getting the send-status as text.
     */
    protected $sendStatusArguments;

    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusResult|null
     */
    protected $acumulusResult = null;

    /**
     * InvoiceSendResult constructor.
     *
     * @param string $trigger
     *   A string indicating the function that triggered the sending, e.g.
     *   InvoiceManager::sourceStatusChange().
     * @param \Siel\Acumulus\Helpers\Translator $translator
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
     *   contain 1 of the Result::Sent_... or Result::Invoice_NotSent_...
     *   constants.
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
     * @noinspection PhpUnused
     */
    public function setTrigger(string $trigger): InvoiceAddResult
    {
        $this->trigger = $trigger;
        return $this;
    }


    /**
     * Returns a translated string indicating the action taken (sent or not sent).
     *
     * @return string
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
     *
     * @return string
     */
    protected function getSendStatusText(): string
    {
        switch ($this->sendStatus) {
            case self::NotSent_WrongStatus:
                $message = empty($this->sendStatusArguments)
                    ? 'reason_not_sent_triggerCreditNoteEvent_None'
                    : 'reason_not_sent_wrongStatus';
                break;
            case self::NotSent_AlreadySent:
                $message = 'reason_not_sent_alreadySent';
                break;
            case self::NotSent_LockedForSending:
                $message = 'reason_not_sent_alreadySending';
                break;
            case self::NotSent_EventInvoiceCreated:
                $message = 'reason_not_sent_prevented_invoiceCreated';
                break;
            case self::NotSent_EventInvoiceCompleted:
                $message = 'reason_not_sent_prevented_invoiceCompleted';
                break;
            case self::NotSent_EmptyInvoice:
                $message = 'reason_not_sent_empty_invoice';
                break;
            case self::NotSent_TriggerInvoiceCreateNotEnabled:
                $message = 'reason_not_sent_not_enabled_triggerInvoiceCreate';
                break;
            case self::NotSent_TriggerInvoiceSentNotEnabled:
                $message = 'reason_not_sent_not_enabled_triggerInvoiceSent';
                break;
            case self::NotSent_LocalErrors:
                $message = 'reason_not_sent_local_errors';
                break;
            case self::NotSent_DryRun:
                $message = 'reason_not_sent_dry_run';
                break;
            case self::Send_TestMode:
                $message = 'reason_sent_testMode';
                break;
            case self::Send_New:
                $message = empty($this->sendStatusArguments)
                    ? 'reason_sent_new'
                    : 'reason_sent_new_status_change';
                break;
            case self::Send_LockExpired:
                $message = 'reason_sent_lock_expired';
                break;
            case self::Send_Forced:
                $message = 'reason_sent_forced';
                break;
            default:
                $message = 'reason_unknown';
                $this->sendStatusArguments = [($this->sendStatus)];
                break;
        }
        $message = $this->t($message);
        if (!empty($this->sendStatusArguments)) {
            $message = vsprintf($message, $this->sendStatusArguments);
        }
        return $message;
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
     *
     * @param \Siel\Acumulus\ApiClient\AcumulusResult $acumulusResult
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
     *   One of the Result::AddReqResp_... constants
     *
     * @return string
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
                if ($addReqResp === InvoiceAddResult::AddReqResp_Always
                    || ($addReqResp === InvoiceAddResult::AddReqResp_WithOther && $this->hasRealMessages())
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
