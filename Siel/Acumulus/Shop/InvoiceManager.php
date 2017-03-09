<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

/**
 * Provides functionality to manage invoices.
 */
abstract class InvoiceManager
{
    /** @var \Siel\Acumulus\Shop\ConfigInterface */
    protected $config;

    /** @var string */
    protected $message;

    /**
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;

        $translations = new InvoiceSendTranslations();
        $config->getTranslator()->add($translations);

        $this->message = '';
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t($key)
    {
        return $this->config->getTranslator()->get($key);
    }

    /**
     * Returns a list of existing invoice sources for the given id range.
     *
     * @param string $invoiceSourceType
     * @param string $InvoiceSourceIdFrom
     * @param string $InvoiceSourceIdTo
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    abstract public function getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceIdFrom, $InvoiceSourceIdTo);

    /**
     * Returns a list of existing invoice sources for the given reference range.
     *
     * Should be overridden when the reference is not the internal id.
     *
     * @param string $invoiceSourceType
     * @param string $invoiceSourceReferenceFrom
     * @param string $invoiceSourceReferenceTo
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo)
    {
        return $this->getInvoiceSourcesByIdRange($invoiceSourceType, $invoiceSourceReferenceFrom, $invoiceSourceReferenceTo);
    }

    /**
     * Returns a list of existing invoice sources for the given date range.
     *
     * @param string $invoiceSourceType
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    abstract public function getInvoiceSourcesByDateRange($invoiceSourceType, DateTime $dateFrom, DateTime $dateTo);

    /**
     * Creates a set of Invoice Sources given their ids or shop specific sources.
     *
     * @param string $invoiceSourceType
     * @param array $idsOrSources
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   A non keyed array with invoice Sources.
     */
    public function getSourcesByIdsOrSources($invoiceSourceType, array $idsOrSources)
    {
        $results = array();
        foreach ($idsOrSources as $sourceId) {
            $results[] = $this->getSourceByIdOrSource($invoiceSourceType, $sourceId);
        }
        return $results;
    }

    /**
     * Creates a source given its type and id.
     *
     * @param string $invoiceSourceType
     * @param int|array|object $idOrSource
     *
     * @return \Siel\Acumulus\Invoice\Source
     *   An invoice Source.
     */
    protected function getSourceByIdOrSource($invoiceSourceType, $idOrSource)
    {
        return $this->config->getSource($invoiceSourceType, $idOrSource);
    }

    /**
     * Sends multiple invoices to Acumulus.
     *
     * @param \Siel\Acumulus\Invoice\Source[] $invoiceSources
     * @param bool $forceSend
     *   If true, force sending the invoices even if an invoice has already been
     *   sent for a given invoice source.
     * @param bool $dryRun
     *   If true, return the reason/status only but do not actually send the
     *   invoice, nor mail the result or store the result.
     * @param string[] $log
     *
     * @return bool
     *   Success.
     */
    public function sendMultiple(array $invoiceSources, $forceSend, $dryRun, array &$log)
    {
        $this->config->getTranslator()->add(new InvoiceSendTranslations());
        $errorLogged = false;
        $success = true;
        $time_limit = ini_get('max_execution_time');
        /** @var Source $invoiceSource */
        foreach ($invoiceSources as $invoiceSource) {
            // Try to keep the script running, but note that other systems
            // involved, like the (Apache) web server, may have their own
            // time-out. Use @ to prevent messages like "Warning:
            // set_time_limit(): Cannot set max execution time limit due to
            // system policy in ...".
            if (!@set_time_limit($time_limit) && !$errorLogged) {
                $this->config->getLog()->warning('InvoiceManager::sendMultiple(): could not set time limit.');
                $errorLogged = true;
            }

            $this->send($invoiceSource, $forceSend, $dryRun);
            $log[$invoiceSource->getId()] = $this->message;
        }
        return $success;
    }

    /**
     * Processes an invoice source status change event.
     *
     * For now we don't look at credit note states, they are always sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source whose status has changed.
     *
     * @return int
     *   Status, 1 of the WebConfigInterface::Status_... constants.
     */
    public function sourceStatusChange(Source $invoiceSource)
    {
        $status = $invoiceSource->getStatus();
        $shopEventSettings = $this->config->getShopEventSettings();
        if ($invoiceSource->getType() === Source::CreditNote || in_array($status, $shopEventSettings['triggerOrderStatus'])) {
            $result = $this->send($invoiceSource);
        } else {
            $result = ConfigInterface::Invoice_NotSent_WrongStatus;
            $messages = array(sprintf('%s not in [%s]', $status, implode(',', $shopEventSettings['triggerOrderStatus'])));
            $logMessage = $this->getInvoiceSendResultMessage($invoiceSource, $result, $messages);
            $this->config->getLog()->notice('InvoiceManager::sourceStatusChange(): %s', $logMessage);
        }
        return $result;
    }

    /**
     * Processes an invoice create event.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source for which a shop invoice was created.
     *
     * @return int
     *   Status, 1 of the WebConfigInterface::Status_... constants.
     */
    public function invoiceCreate(Source $invoiceSource)
    {
        $shopEventSettings = $this->config->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] == Config::TriggerInvoiceEvent_Create) {
            $result = $this->send($invoiceSource);
        } else {
            $result = ConfigInterface::Invoice_NotSent_TriggerInvoiceCreateNotEnabled;
            $logMessage = $this->getInvoiceSendResultMessage($invoiceSource, $result);
            $this->config->getLog()->notice('InvoiceManager::invoiceCreate(): %s', $logMessage);
        }
        return $result;
    }

    /**
     * Processes a shop invoice send event.
     *
     * This is the invoice created by the shop and that is now sent/mailed to
     * the customer.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source for which a shop invoice was created.
     *
     * @return int
     *   Status, 1 of the WebConfigInterface::Status_... constants.
     */
    public function invoiceSend(Source $invoiceSource)
    {
        $shopEventSettings = $this->config->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceEvent'] == Config::TriggerInvoiceEvent_Send) {
            $result = $this->send($invoiceSource);
        } else {
            $result = ConfigInterface::Invoice_NotSent_TriggerInvoiceSentNotEnabled;
            $logMessage = $this->getInvoiceSendResultMessage($invoiceSource, $result);
            $this->config->getLog()->notice('InvoiceManager::invoiceSend(): %s', $logMessage);
        }
        return $result;
    }

    /**
     * Creates and sends an invoice to Acumulus for an order.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was
     *   created.
     * @param bool $forceSend
     *   If true, force sending the invoice even if an invoice has already been
     *   sent for the given invoice source.
     * @param bool $dryRun
     *   If true, return the reason/status only but do not actually send the
     *   invoice, nor mail the result or store the result.
     *
     * @return int
     *   Status, 1 of the WebConfigInterface::Status_... constants.
     */
    public function send(Source $invoiceSource, $forceSend = false, $dryRun = false)
    {
        $pluginSettings = $this->config->getPluginSettings();
        $testMode = $pluginSettings['debug'] == Config::Debug_TestMode;
        $messages = array();
        if ($testMode) {
            $status = ConfigInterface::Invoice_Sent_TestMode;
        } elseif (!$this->config->getAcumulusEntryModel()->getByInvoiceSource($invoiceSource)) {
            $status = ConfigInterface::Invoice_Sent_New;
        } elseif ($forceSend) {
            $status = ConfigInterface::Invoice_Sent_Forced;
        } else {
            $status = ConfigInterface::Invoice_NotSent_AlreadySent;
        }

        if ($status !== ConfigInterface::Invoice_NotSent_AlreadySent) {
            $invoice = $this->config->getCreator()->create($invoiceSource);

            // Do not send 0-amount invoices, if set so.
            $shopEventSettings = $this->config->getShopEventSettings();
            if ($shopEventSettings['sendEmptyInvoice'] || !$this->isEmptyInvoice($invoice)) {
                // Trigger the InvoiceCreated event.
                $this->triggerInvoiceCreated($invoice, $invoiceSource);

                // If the invoice is not set to null, we continue by completing it.
                if ($invoice !== NULL) {
                    // @todo: $localMessages seems like a code smell to me, but needs to be mailed, so passed on: can/should we place that in the invoice or invoiceSource?
                    // @todo: do not send if $localMessages contains errors.
                    // @todo: The $this->message mess is a code smell.
                    // @todo: $status does not receive a proper status on $dryRun: fix
                    $localMessages = array();
                    $invoice = $this->config->getCompletor()->complete($invoice, $invoiceSource, $localMessages);

                    // Trigger the InvoiceCompleted event.
                    $this->triggerInvoiceCompleted($invoice, $invoiceSource);

                    // If the invoice is not set to null, we continue by sending it.
                    if ($invoice !== NULL) {
                        if (!$dryRun) {
                            $result = $this->doSend($invoice, $invoiceSource, $localMessages);
                            $status |= $result['status'];
                        }
                    } else {
                        $status = ConfigInterface::Invoice_NotSent_EventInvoiceCompleted;
                    }
                } else {
                    $status = ConfigInterface::Invoice_NotSent_EventInvoiceCreated;
                }
            } else {
                $status = ConfigInterface::Invoice_NotSent_EmptyInvoice;
            }

        }

        $logMessage = $this->getInvoiceSendResultMessage($invoiceSource, $status, $messages);
        if (!$dryRun) {
            $this->config->getLog()->notice('InvoiceManager::send(): %s', $logMessage);
        }

        return $status;
    }

    /**
     * Unconditionally sends the invoice.
     *
     * After sending the invoice:
     * - A successful result gets saved to the acumulus entries table.
     * - A mail with the results may be sent.
     * - The invoice sent event gets triggered
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param array $invoice
     * @param array $localMessages
     *
     * @return array
     *   The result structure of the invoice add API call merged with any local
     *   messages.
     */
    protected function doSend(array $invoice, Source $invoiceSource, array $localMessages) {
        $result = $this->config->getService()->invoiceAdd($invoice);

        // Check if an entryid was created and store entry id and token.
        if (!empty($result['invoice']['entryid'])) {
            $this->config->getAcumulusEntryModel()->save($invoiceSource, $result['invoice']['entryid'], $result['invoice']['token']);
        } else {
            // If the invoice was sent as a concept, no entryid will be returned
            // but we still want to prevent sending it again: check for the
            // concept status, the absence of errors and non test-mode.
            $pluginSettings = $this->config->getPluginSettings();
            $testMode = $pluginSettings['debug'] == Config::Debug_TestMode;
            $isConcept = $invoice['customer']['invoice']['concept'] == Config::Concept_Yes;
            if (empty($result['errors']) && $isConcept && !$testMode) {
                $this->config->getAcumulusEntryModel()->save($invoiceSource, null, null);
            }
        }

        // Merge in local messages before making the result known to the world.
        $result = $this->mergeLocalMessages($result, $localMessages);

        // Send a mail if there are messages.
        $messages = $this->config->getService()->resultToMessages($result);
        if (!empty($messages)) {
            $this->mailInvoiceAddResult($result, $messages, $invoiceSource);
        }

        // Trigger the InvoiceSent event.
        $this->triggerInvoiceSent($invoice, $invoiceSource, $result);

        return $result;
    }

    /**
     * Merges any local messages into the result structure and adapts the status
     * to correctly reflect local warnings and errors as well.
     *
     * @todo: extract this into a messages/result class.
     *
     * @param array $result
     * @param array $localMessages
     *
     * @return array
     */
    public function mergeLocalMessages(array $result, array $localMessages)
    {
        if (!empty($localMessages['errors'])) {
            $result['errors'] = array_merge($result['errors'], $localMessages['errors']);
            if ($result['status'] < ConfigInterface::Status_Errors) {
                $result['status'] = ConfigInterface::Status_Errors;
            }
        }
        if (!empty($localMessages['warnings'])) {
            $result['warnings'] = array_merge($result['warnings'], $localMessages['warnings']);
            if ($result['status'] < ConfigInterface::Status_Warnings) {
                $result['status'] = ConfigInterface::Status_Warnings;
            }
        }
        return $result;
    }

    /**
     * Returns a translated message of the result of the send() method.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     * @param $status
     * @param array $messages
     *
     * @return string
     *   A translated sentence describing the action taken, the reason for
     *   taking that action, and its results if an invoice was sent, of a call
     *   to the InvoiceManager::send() method.
     */
    protected function getInvoiceSendResultMessage(Source $invoiceSource, $status, array $messages = array())
    {
        $sent = ($status & ConfigInterface::Invoice_NotSent) === 0;
        $action = $this->t($sent ? 'action_sent' : 'action_not_sent');
        $reason = $this->getSendReason($status & (ConfigInterface::Invoice_Sent_Mask | ConfigInterface::Invoice_NotSent_Mask));
        $message = sprintf($this->t('message_invoice_send'), $this->t($invoiceSource->getType()), $invoiceSource->getReference(), $action, $reason);

        if ($sent) {
            $service = $this->config->getService();
            $message .= ' ' . $service->getStatusText($status & WebConfigInterface::Status_Mask);
            if ((($status & WebConfigInterface::Status_Mask) !== WebConfigInterface::Status_Success) && !empty($messages)) {
                $message .= ' ' . $service->messagesToText($messages);
            }
        }

        // Also store the message for later retrieval by batch send.
        $this->message = $message;

        return $message;
    }

    /**
     * Returns the translated reason of (not) sending the invoice.
     *
     * @param int $status
     *   The result status.
     *
     * @return string
     *   The translated reason of (not) sending the invoice.
     */
    protected function getSendReason($status) {
        switch ($status) {
            case ConfigInterface::Invoice_NotSent_WrongStatus:
                $message = 'reason_not_sent_wrongStatus';
                break;
            case ConfigInterface::Invoice_NotSent_AlreadySent:
                $message = 'reason_not_sent_alreadySent';
                break;
            case ConfigInterface::Invoice_NotSent_EventInvoiceCreated:
                $message = 'reason_not_sent_prevented_invoiceCreated';
                break;
            case ConfigInterface::Invoice_NotSent_EventInvoiceCompleted:
                $message = 'reason_not_sent_prevented_invoiceCompleted';
                break;
            case ConfigInterface::Invoice_NotSent_EmptyInvoice:
                $message = 'reason_not_sent_empty_invoice';
                break;
            case ConfigInterface::Invoice_NotSent_TriggerInvoiceCreateNotEnabled:
                $message = 'reason_not_sent_not_enabled_triggerInvoiceCreate';
                break;
            case ConfigInterface::Invoice_NotSent_TriggerInvoiceSentNotEnabled:
                $message = 'reason_not_sent_not_enabled_triggerInvoiceSent';
                break;
            case ConfigInterface::Invoice_Sent_TestMode:
                $message = 'reason_sent_testMode';
                break;
            case ConfigInterface::Invoice_Sent_New:
                $message = 'reason_sent_new';
                break;
            case ConfigInterface::Invoice_Sent_Forced:
                $message = 'reason_sent_forced';
                break;
            default:
                return sprintf($this->t('reason_unknown'), $status);
        }
        return $this->t($message);
    }

    /**
     * Sends an email with the results of a sent invoice.
     *
     * The mail is sent to the shop administrator (emailonerror setting).
     *
     * @param array $result
     * @param string[] $messages
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *
     * @return bool
     *   Success.
     */
    protected function mailInvoiceAddResult(array $result, array $messages, Source $invoiceSource)
    {
        return $this->config->getMailer()->sendInvoiceAddMailResult($result, $messages, $invoiceSource->getType(), $invoiceSource->getReference());
    }

    /**
     * Returns whether an invoice is empty (free products only).
     *
     * @param array $invoice
     *
     * @return bool
     *   True if the invoice amount (inc. VAT) is €0,-.
     */
    protected function isEmptyInvoice(array $invoice)
    {
        return Number::isZero($invoice['customer']['invoice']['meta-invoice-amountinc']);
    }

    /**
     * Triggers an event that an invoice for Acumulus has been created and is
     * ready to be completed and sent.
     *
     * This allows to inject custom behavior to alter the invoice just before
     * completing and sending.
     *
     * @param array $invoice
     *   The invoice that has been created.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     */
    protected function triggerInvoiceCreated(array &$invoice, Source $invoiceSource)
    {
        // Default implementation: no event.
    }

    /**
     * Triggers an event that an invoice for Acumulus has been created and
     * completed and is ready to be sent.
     *
     * This allows to inject custom behavior to alter the invoice just before
     * sending.
     *
     * @param array $invoice
     *   The invoice that has been created and completed.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     */
    protected function triggerInvoiceCompleted(array &$invoice, Source $invoiceSource)
    {
        // Default implementation: no event.
    }

    /**
     * Triggers an event after an invoice for Acumulus has been sent.
     *
     * This allows to inject custom behavior to react to invoice sending.
     *
     * @param array $invoice
     *   The invoice that has been sent.
     * @param Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was sent.
     * @param array $result
     *   The result as sent back by Acumulus. This array contains the following
     *   keys:
     *   - invoice: array
     *     - invoicenumber: string
     *     - token: string
     *     - entryid: string
     *   - errors: array
     *     - error: array
     *       - code: string
     *       - codetag: string
     *       - message: string
     *     - counterrors: int
     *   - warnings: array
     *     - warning: array
     *       - code: string
     *       - codetag: string
     *       - message: string
     *     - countwarnings: int
     */
    protected function triggerInvoiceSent(array $invoice, Source $invoiceSource, array $result)
    {
        // Default implementation: no event.
    }

    /**
     * Returns the given DateTime in a format that the actual database layer
     * accepts for comparison in a SELECT query.
     *
     * This default implementation returns the DateTime as a string in ISO format
     * (yyyy-mm-dd hh:mm:ss).
     *
     * @param \DateTime $date
     *
     * @return int|string
     */
    protected function getSqlDate(DateTime $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Helper method to retrieve the values from 1 column of a query result.
     *
     * @param array $dbResults
     * @param string $key
     *
     * @return int[]
     */
    protected function getCol(array $dbResults, $key)
    {
        $results = array();
        foreach ($dbResults as $dbResult) {
            $results[] = (int) $dbResult[$key];
        }
        return $results;
    }
}
