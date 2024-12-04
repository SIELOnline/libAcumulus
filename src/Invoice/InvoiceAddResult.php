<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Result;

use function count;

/**
 * Extension of {@see Result} with properties and features specific to the InvoiceAdd web
 * API service call.
 *
 * @noinspection PhpLackOfCohesionInspection
 */
class InvoiceAddResult extends Result
{
    // Invoice send handling related constants.
    // Reasons for not sending.
    public const NotSent_AlreadySent = 0x1;
    public const NotSent_WrongStatus = 0x2;
    public const NotSent_EmptyInvoice = 0x3;
    public const NotSent_TriggerInvoiceCreateNotEnabled = 0x4;
    public const NotSent_TriggerInvoiceSentNotEnabled = 0x5;
    public const NotSent_TriggerCreditNoteEventNotEnabled = 0x6;
    public const NotSent_AlreadyLocked = 0x7;
    public const NotSent_LockNotAcquired = 0x8;
    public const NotSent_NoInvoiceLines = 0x9;
    // @todo: waarom wordt deze niet gebruikt: kloppen deze events nog wel?
    public const NotSent_EventInvoiceCreateBefore = 0xa;
    public const NotSent_EventInvoiceCreateAfter = 0xb;
    public const NotSent_EventInvoiceSendBefore = 0xc;
    // Reasons for sending.
    public const Sent_Forced = 0x10;
    public const Sent_LockExpired = 0x20;

   /**
     * @var \Siel\Acumulus\Data\Invoice|null
     *   The invoice that is (attempted to) being sent to Acumulus,
     *   or null if not yet set.
     */
    protected ?Invoice $invoice = null;

    protected function getStatusMessages(): array
    {
        return [
            self::NotSent_AlreadySent => 'reason_not_sent_alreadySent',
            self::NotSent_WrongStatus => count($this->sendStatusArguments) === 0
                ? 'reason_not_sent_triggerCreditNoteEvent_None'
                : 'reason_not_sent_wrongStatus',
            self::NotSent_EmptyInvoice => 'reason_not_sent_empty_invoice',
            self::NotSent_TriggerInvoiceCreateNotEnabled => 'reason_not_sent_not_enabled_triggerInvoiceCreate',
            self::NotSent_TriggerInvoiceSentNotEnabled => 'reason_not_sent_not_enabled_triggerInvoiceSent',
            self::NotSent_TriggerCreditNoteEventNotEnabled => 'reason_not_sent_not_enabled_triggerCreditNoteEvent',
            self::NotSent_AlreadyLocked => 'reason_not_sent_alreadySending',
            self::NotSent_LockNotAcquired => 'reason_not_sent_lockNotAcquired',
            self::NotSent_NoInvoiceLines => 'reason_not_sent_no_invoice_lines',
            self::NotSent_EventInvoiceCreateBefore => 'reason_not_sent_prevented_invoiceCreated',
            self::NotSent_EventInvoiceCreateAfter => 'reason_not_sent_prevented_invoiceCreated',
            self::NotSent_EventInvoiceSendBefore => 'reason_not_sent_prevented_invoiceCompleted',
            self::Sent_New => count($this->sendStatusArguments) === 0
                        ? 'reason_sent_new'
                        : 'reason_sent_new_status_change',
            self::Sent_Forced => 'reason_sent_forced',
            self::Sent_LockExpired => 'reason_sent_lock_expired',
        ] + parent::getStatusMessages();
    }

    /**
     * Returns the invoice that is (attempted to) being sent to Acumulus,
     * or null if not yet set.
     */
    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice;
    }
}
