<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\ApiClient\AcumulusResult;

use function count;
use function sprintf;

/**
 * Result is an extension of {@see AcumulusResult} with properties and features specific
 * to more complex web API service calls, especially those that add data.
 *
 * This is not a real extension in the sense of that it extends {@see AcumulusResult}, it
 * wraps an {@see AcumulusResult} and adds methods that are more on the domain level.
 */
class Result extends MessageCollection
{
    // Whether to add the raw request and response to mails or log messages.
    public const AddReqResp_Never = 1;
    public const AddReqResp_Always = 2;
    public const AddReqResp_WithOther = 3;

    // Invoice send handling related constants.
    public const SendStatus_Unknown = 0;
    public const NotSent_Mask = 0xf;
    // Reasons for not sending.
    public const NotSent_LocalErrors = 0xc;
    public const NotSent_DryRun = 0xe;
    // Reasons for sending.
    public const Send_Mask = 0xf0;
    public const Sent_New = 0xf0;
    public const Sent_TestMode = 0xe0;

    /**
     * A string indicating the function that triggered the sending, e.g.
     * {@see \Siel\Acumulus\Magento\Invoice\SourceInvoiceManager::sourceStatusChange()}.
     */
    private string $trigger;
    /**
     * A status indicating if and why an invoice was or was not sent. It will
     * contain 1 of the {@see InvoiceAddResult}::Send_... or
     * {@see InvoiceAddResult}::NotSent_... constants.
     */
    private int $sendStatus;
    /**
     * A list of parameters to use when getting the send-status as text.
     */
    protected array $sendStatusArguments;
    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusResult|null
     *   The API result of sending the invoice to Acumulus,
     *   null if not yet sent or if sending is prevented
     */
    private ?AcumulusResult $acumulusResult = null;

    /**
     * Constructor.
     *
     * @param string $trigger
     *   A string indicating the method that triggered the sending, e.g.
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
     *   A status indicating if and why an invoice was or was not sent. It will contain
     *   1 of the {@see Result}::Send_... or  {@see Result}::NotSent_... constants.
     */
    public function getSendStatus(): int
    {
        return $this->sendStatus;
    }

    /**
     * @param int $sendStatus
     *   A status indicating if and why an invoice was or was not sent.  It should contain
     *   1 of the {@see Result}::Sent_... or {@see Result}::NotSent_... constants.
     * @param array $arguments
     *   A list of parameters to use when getting the send status as text.
     *
     * @return $this
     */
    public function setSendStatus(int $sendStatus, array $arguments = []): static
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
     *   True if the invoice has been prevented from sending,
     *   false if it has been sent or if the sendStatus has not yet been set.
     */
    public function isSendingPrevented(): bool
    {
        return ($this->sendStatus & self::NotSent_Mask) !== 0;
    }

    public function hasLocalErrors(): bool
    {
        return $this->sendStatus === self::NotSent_LocalErrors;
    }

    /**
     * {@inheritDoc}
     *
     * This override also takes the {@see AcumulusResult::getSendStatus()} and the
     * {@see AcumulusResult::getStatus()} into account, not only the severity of the
     * {@see Result::getMessages() messages}.
     */
    public function getSeverity(): int
    {
        if ($this->getAcumulusResult()?->getStatus()) {
            $severity = $this->getAcumulusResult()->getStatus();
        } elseif ($this->hasLocalErrors()) {
            $severity = Severity::Error;
        } elseif ($this->isSendingPrevented()) {
            $severity = Severity::Success;
        } else {
            $severity = Severity::Unknown;
        }
        return max($severity, parent::getSeverity());
    }

    /**
     * Returns whether the request was sent in test mode.
     *
     * @return bool|null
     *   true if the request concerned a test mode request, false if not, null if unknown
     *   (because there's not yet a result.).
     */
    public function isTestMode(): ?bool
    {
        return $this->getAcumulusResult()?->getAcumulusRequest()->isTestMode();
    }

    /**
     * @return string
     *   A string indicating the function that triggered the sending,
     *   e.g. InvoiceManager::sourceStatusChange().
     */
    public function getTrigger(): string
    {
        return $this->trigger;
    }

    /**
     * @param string $trigger
     *   A string indicating the function that triggered the sending,
     *   e.g. InvoiceManager::sourceStatusChange().
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function setTrigger(string $trigger): Result
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
     * Returns the status messages to use keyed by (not) sent status.
     */
    protected function getStatusMessages(): array
    {
        return [
            self::SendStatus_Unknown => 'reason_unknown',
            self::NotSent_LocalErrors => 'reason_not_sent_local_errors',
            self::NotSent_DryRun => 'reason_not_sent_dry_run',
            self::Sent_TestMode => 'reason_sent_test_mode',
        ];
    }

    /**
     * Returns a translated string indicating the reason for the action taken.
     */
    public function getSendStatusText(): string
    {
        $messages = $this->getStatusMessages();
        if (isset($messages[$this->sendStatus])) {
            $message = $messages[$this->sendStatus];
        } else {
            $message = 'reason_unknown';
            $this->sendStatusArguments = [($this->sendStatus)];
        }
        $message = $this->t($message);
        if (count($this->sendStatusArguments) > 0) {
            $message = vsprintf($message, $this->sendStatusArguments);
        }
        return $message;
    }

    public function getMainApiResponse(): ?array
    {
        return $this->getAcumulusResult()?->getMainAcumulusResponse();
    }

    public function getAcumulusResult(): ?AcumulusResult
    {
        return $this->acumulusResult;
    }

    /**
     * Sets the AcumulusResult and copies its messages to this object.
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
        $message = sprintf($this->t('message_reason'), $this->getActionText(), $this->getSendStatusText());

        if ($this->hasBeenSent() || $this->hasLocalErrors()) {
            if ($this->hasRealMessages()) {
                $message .= "\n" . $this->formatMessages(Message::Format_PlainListWithSeverity, Severity::RealMessages);
            }
            if ($this->getAcumulusResult() !== null) {
                $message .= ' ' . $this->getAcumulusResult()->getStatusText();
                if ($addReqResp === self::AddReqResp_Always
                    || ($addReqResp === self::AddReqResp_WithOther && $this->hasRealMessages())
                ) {
                    $message .= "\nRequest: " . $this->getAcumulusResult()->getAcumulusRequest()->getMaskedRequest()
                        . "\nResponse: " . $this->getAcumulusResult()->getMaskedResponse()
                        . "\n";
                }
            }
        }

        return $message;
    }
}
