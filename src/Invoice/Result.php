<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Web\Result as WebResult;

/**
 * Extends Result with properties and features specific to the InvoiceAdd web
 * service call.
 */
class Result extends WebResult
{
    // Invoice send handling related constants.
    // Reason for not sending: bits 5 to 8.
    const NotSent_EventInvoiceCreated = 0x10;
    const NotSent_EventInvoiceCompleted = 0x20;
    const NotSent_AlreadySent = 0x30;
    const NotSent_WrongStatus = 0x40;
    const NotSent_EmptyInvoice = 0x50;
    const NotSent_TriggerInvoiceCreateNotEnabled = 0x60;
    const NotSent_TriggerInvoiceSentNotEnabled = 0x70;
    const NotSent_LocalErrors = 0x80;
    const NotSent_DryRun = 0x90;
    const NotSent_TriggerCreditNoteEventNotEnabled = 0xa0;
    const NotSent_AlreadySending = 0xb0;
    const NotSent_Mask = 0xf0;
    // Reason for sending: bits 9 to 11.
    const Sent_New = 0x100;
    const Sent_Forced = 0x200;
    const Sent_TestMode = 0x300;
    const Sent_LockExpired = 0x400;
    const Sent_Mask = 0x700;

    private static $translationsLoaded = false;

    /**
     * A status indicating if and why an invoice was sent or not sent. It will
     * contain 1 of the self::Sent_... or Invoice_NotSent_...
     * constants.
     *
     * @var int
     */
    protected $sendStatus;

    /**
     * A list of parameters to use when getting the send status as text.
     *
     * @var array
     */
    protected $sendStatusArguments;

    /**
     * A string indicating the function that triggered the sending.
     *
     * @var string
     */
    protected $trigger;

    /**
     * InvoiceSendResult constructor.
     *
     * @param string $trigger
     * @param \Siel\Acumulus\Helpers\Translator $translator
     */
    public function __construct($trigger, Translator $translator)
    {
        parent::__construct($translator);
        $this->trigger = $trigger;
        $this->sendStatus = 0;
        $this->sendStatusArguments = array();

        if (!self::$translationsLoaded) {
            $translations = new ResultTranslations();
            $this->translator->add($translations);
            self::$translationsLoaded = true;
        }
    }

    /**
     * @return int
     */
    public function getSendStatus()
    {
        return $this->sendStatus;
    }

    /**
     * @param int $sendStatus
     * @param array $arguments
     *
     * @return $this
     */
    public function setSendStatus($sendStatus, $arguments = array())
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
    public function hasBeenSent()
    {
        return ($this->sendStatus & self::Sent_Mask) !== 0;
    }

    /**
     * Returns whether the invoice has been prevented from sending.
     *
     * @return bool
     *   True if the invoice has been prevnted from sensing, false if it has
     *   been sent or if the sendStatus has not yet been set.
     */
    public function isSendingPrevented()
    {
        return ($this->sendStatus & self::NotSent_Mask) !== 0;
    }

    /**
     * @return string
     */
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * @param string $trigger
     *
     * @return $this
     */
    public function setTrigger($trigger)
    {
        $this->trigger = $trigger;
        return $this;
    }


    /**
     * Returns a translated string indicating the action taken (sent or not sent).
     *
     * @return string
     */
    protected function getActionText()
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
    protected function getSendStatusText()
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
            case self::NotSent_AlreadySending:
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
            case self::Sent_TestMode:
                $message = 'reason_sent_testMode';
                break;
            case self::Sent_New:
                $message = empty($this->sendStatusArguments)
                    ? 'reason_sent_new'
                    : 'reason_sent_new_status_change';
                break;
            case self::Sent_LockExpired:
                $message = 'reason_sent_lock_expired';
                break;
            case self::Sent_Forced:
                $message = 'reason_sent_forced';
                break;
            default:
                $message = 'reason_unknown';
                $this->sendStatusArguments = array(($this->sendStatus));
                break;
        }
        $message = $this->t($message);
        if (!empty($this->sendStatusArguments)) {
            $message = vsprintf($message, $this->sendStatusArguments);
        }
        return $message;
    }

    /**
     * Returns a translated sentence that can be used for logging.
     *
     * The returned sentence indicated what happened and why. If the invoice was
     * sent or local errors prevented it being sent, then the returned string
     * also includes any messages (warnings, errors, or exception).
     *
     * @param int $addReqResp
     *   Whether to add the raw request and response.
     *   One of the Result::AddReqResp_... constants
     *
     * @return string
     */
    public function getLogText($addReqResp)
    {
        $action = $this->getActionText();
        $reason = $this->getSendStatusText();
        $message = sprintf($this->t('message_invoice_reason'), $action, $reason);

        if ($this->hasBeenSent() || $this->getSendStatus() === self::NotSent_LocalErrors) {
            if ($this->hasBeenSent()) {
                $message .= ' ' . $this->getStatusText();
            }
            if ($this->hasMessages()) {
                $message .= "\n" . $this->getMessages(Result::Format_FormattedText);
            }
            if ($addReqResp === Result::AddReqResp_Always || ($addReqResp === Result::AddReqResp_WithOther && $this->hasMessages())) {
                $message .= ' ' . $this->getRawRequestResponse(Result::Format_FormattedText);
            }
        }
        return $message;
    }
}
