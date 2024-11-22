<?php

declare(strict_types=1);

namespace Siel\Acumulus\Product;

use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;

use function count;
use function sprintf;

/**
 * Extension of {@see AcumulusResult} with properties and features specific to the
 * StockTransaction web API service call.
 */
class StockTransactionResult extends MessageCollection
{
    // Whether to add the raw request and response to mails or log messages.
    public const AddReqResp_Never = 1;
    public const AddReqResp_Always = 2;
    public const AddReqResp_WithOther = 3;

    // Stock transaction handling related constants.
    public const SendStatus_Unknown = 0;
    // Reasons for not sending.
    public const NotSent_StockManagementNotEnabled = 0x1;
    public const NotSent_StockManagementDisabledForProduct = 0x2;
    public const NotSent_NoProduct = 0x3;
    public const NotSent_ZeroChange = 0x4;
    public const NotSent_NoMatchValueInProduct = 0x6;
    public const NotSent_NoMatchInAcumulus = 0x6;
    public const NotSent_TooManyMatchesInAcumulus = 0x7;
    public const NotSent_LocalErrors = 0x8;
    public const NotSent_DryRun = 0xf;
    public const NotSent_Mask = 0xf;
    // Reasons for sending.
    public const Sent_New = 0x10;
    public const Sent_TestMode = 0x30;
    public const Send_Mask = 0xf0;

    /**
     * A string indicating the function or event that triggered the sending, e.g.
     * 'woocommerce_reduce_order_item_stock'.
     */
    protected string $trigger;
    /**
     * A status indicating if and why an invoice was or was not sent. It will
     * contain 1 of the {@see StockTransactionResult}::Send_... or
     * {@see StockTransactionResult}::NotSent_... constants.
     */
    protected int $sendStatus;
    /**
     * A list of parameters to use when getting the send-status as text.
     */
    protected array $sendStatusArguments;
    /**
     * @var \Siel\Acumulus\Data\StockTransaction|null
     *   The stock transaction that is (attempted to) being sent to Acumulus,
     *   or null if not yet set.
     */
    protected ?StockTransaction $stockTransaction = null;
    /**
     * @var \Siel\Acumulus\ApiClient\AcumulusResult|null
     *   The API result of sending the stock transaction to Acumulus,
     *   null if not yet sent or if sending is prevented
     */
    protected ?AcumulusResult $acumulusResult = null;

    /**
     * Constructor.
     *
     * @param string $trigger
     *   A string indicating the function or event that triggered the sending, e.g.
     *   'woocommerce_reduce_order_item_stock'.
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
     *   A status indicating if and why a stock transaction was or was not sent.
     *   It will contain 1 of the {@see StockTransaction}::Send_... or
     *   {@see StockTransaction}::NotSent_... constants.
     */
    public function getSendStatus(): int
    {
        return $this->sendStatus;
    }

    /**
     * @param int $sendStatus
     *   A status indicating if and why a stock transaction was or was not sent.
     *   It should contain 1 of the {@see StockTransaction}::Sent_... or
     *   {@see StockTransaction}::NotSent_... constants.
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
     * Returns whether the stock transaction has been sent.
     *
     * @return bool
     *   True if the stock transaction has been sent, false if sending was prevented or
     *   if the sendStatus has not yet been set.
     */
    public function hasBeenSent(): bool
    {
        return ($this->sendStatus & self::Send_Mask) !== 0;
    }

    /**
     * Returns whether the stock transaction has been prevented from sending.
     *
     * @return bool
     *   True if the stock transaction has been prevented from sending,
     *   false if it has been sent or if the sendStatus has not yet been set.
     */
    public function isSendingPrevented(): bool
    {
        return ($this->sendStatus & self::NotSent_Mask) !== 0;
    }

    /**
     * @return string
     *   A string indicating the function or event that triggered the sending,
     *   e.g. 'woocommerce_reduce_order_item_stock'.
     */
    public function getTrigger(): string
    {
        return $this->trigger;
    }

    /**
     * @param string $trigger
     *   A string indicating the function or event that triggered the sending,
     *   e.g.'woocommerce_reduce_order_item_stock'.
     *
     * @return $this
     *
     * @noinspection PhpUnused
     */
    public function setTrigger(string $trigger): StockTransactionResult
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
            self::NotSent_StockManagementNotEnabled => 'reason_not_sent_not_enabled',
            self::NotSent_NoProduct => 'reason_not_sent_no_product',
            self::NotSent_ZeroChange => 'reason_not_sent_zero_change',
            self::NotSent_NoMatchInAcumulus => 'reason_not_sent_no_match',
            self::NotSent_LocalErrors => 'reason_not_sent_local_errors',
            self::NotSent_DryRun => 'reason_not_sent_dry_run',
            self::Sent_New => 'reason_sent_new',
            self::Sent_TestMode => 'reason_sent_test_mode',
        ];
        $message = $messages[$this->sendStatus];
        $message = $this->t($message);
        if (count($this->sendStatusArguments) !== 0) {
            $message = vsprintf($message, $this->sendStatusArguments);
        }
        return $message;
    }

    /**
     * Returns the stock transaction that is (attempted to) being sent to Acumulus,
     * or null if not yet set.
     */
    public function getStockTransaction(): ?StockTransaction
    {
        return $this->stockTransaction;
    }

    public function setStockTransaction(StockTransaction $stockTransaction): void
    {
        $this->stockTransaction = $stockTransaction;
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
     * The returned sentence indicates what happened and why. If the stock transaction was
     * sent or local errors prevented it being sent, then the returned string
     * also includes any messages.
     *
     * @param int $addReqResp
     *   Whether to add the raw request and response.
     *   One of the {@see StockTransaction}::AddReqResp_... constants
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
