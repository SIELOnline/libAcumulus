<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Web\ConfigInterface as WebConfigInterface;

/**
 * Provides functionality to manage invoices.
 */
abstract class InvoiceManager
{
    /** @var \Siel\Acumulus\Shop\Config */
    protected $config;

    /** @var \Siel\Acumulus\Invoice\Completor */
    protected $completor;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;

        $translations = new BatchTranslations();
        $config->getTranslator()->add($translations);
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
     * Returns a list of invoice source types supported by this shop.
     *
     * The default implementation returns order and credit note. Override if the
     * specific shop supports other types or does not support credit notes.
     *
     * @return string[]
     *   The list of supported invoice source types.
     */
    public function getSupportedInvoiceSourceTypes()
    {
        return array(
            Source::Order,
            Source::CreditNote,
        );
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
     * @param string $InvoiceSourceReferenceFrom
     * @param string $InvoiceSourceReferenceTo
     *
     * @return \Siel\Acumulus\Invoice\Source[]
     *   An array of invoice sources of the given source type.
     */
    public function getInvoiceSourcesByReferenceRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo)
    {
        return $this->getInvoiceSourcesByIdRange($invoiceSourceType, $InvoiceSourceReferenceFrom, $InvoiceSourceReferenceTo);
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
     * @param string[] $log
     *
     * @return bool
     *   Success.
     */
    public function sendMultiple(array $invoiceSources, $forceSend, array &$log)
    {
        $this->config->getTranslator()->add(new BatchTranslations());
        $errorLogged = false;
        $success = true;
        $time_limit = ini_get('max_execution_time');
        /** @var Source $invoiceSource */
        foreach ($invoiceSources as $invoiceSource) {
            // Try to keep the script running, but note that other systems involved,
            // think the (Apache) web server, may have their own time-out.
            // Use @ to prevent messages like "Warning: set_time_limit(): Cannot set
            //   max execution time limit due to system policy in ...".
            if (!@set_time_limit($time_limit) && !$errorLogged) {
                $this->config->getLog()->warning('InvoiceManager::sendMultiple(): could not set time limit.');
                $errorLogged = true;
            }

            $status = $this->send($invoiceSource, $forceSend);
            switch ($status) {
                case WebConfigInterface::Status_Success:
                    $message = 'message_batch_send_1_success';
                    break;
                case WebConfigInterface::Status_Errors:
                case WebConfigInterface::Status_Exception:
                    $message = 'message_batch_send_1_errors';
                    $success = false;
                    break;
                case WebConfigInterface::Status_Warnings:
                    $message = 'message_batch_send_1_warnings';
                    break;
                case WebConfigInterface::Status_NotSent:
                    $message = 'message_batch_send_1_skipped';
                    break;
                case WebConfigInterface::Status_SendingPrevented_InvoiceCreated:
                    $message = 'message_batch_send_1_prevented_invoiceCreated';
                    break;
                case WebConfigInterface::Status_SendingPrevented_InvoiceCompleted:
                    $message = 'message_batch_send_1_prevented_invoiceCompleted';
                    break;
                default:
                    $message = "Status unknown $status (sending invoice for %1\$s %2\$s)";
                    break;
            }
            $log[$invoiceSource->getId()] = sprintf($this->t($message), $this->t($invoiceSource->getType()), $invoiceSource->getReference());
        }
        return $success;
    }

    /**
     * Processes an invoice source status change event.
     *
     * For now we don't look at the status for credit notes: they are always sent.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source whose status changed.
     *
     * @return int
     *   Status, one of the WebConfigInterface::Status_ constants.
     */
    public function sourceStatusChange(Source $invoiceSource)
    {
        $status = $invoiceSource->getStatus();
        $statusString = $status === null ? 'null' : (string) $status;
        $this->config->getLog()->notice('InvoiceManager::sourceStatusChange(%s %d, %s)', $invoiceSource->getType(), $invoiceSource->getId(), $statusString);
        $result = WebConfigInterface::Status_NotSent;
        $shopEventSettings = $this->config->getShopEventSettings();
        if ($invoiceSource->getType() === Source::CreditNote
            || ($shopEventSettings['triggerInvoiceSendEvent'] == Config::TriggerInvoiceSendEvent_OrderStatus
                && in_array($status, $shopEventSettings['triggerOrderStatus']))
        ) {
            $result = $this->send($invoiceSource, false);
        } else {
            $this->config->getLog()->notice('InvoiceManager::sourceStatusChange(%s %d, %s): not sending triggerEvent = %d, triggerOrderStatus = [%s]',
                $invoiceSource->getType(),
                $invoiceSource->getId(),
                $statusString,
                $shopEventSettings['triggerInvoiceSendEvent'],
                is_array($shopEventSettings['triggerOrderStatus']) ? implode(',', $shopEventSettings['triggerOrderStatus']) : 'no array'
            );
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
     *   Status
     */
    public function invoiceCreate(Source $invoiceSource)
    {
        $this->config->getLog()->notice('InvoiceManager::invoiceCreate(%s %d)', $invoiceSource->getType(), $invoiceSource->getId());
        $result = WebConfigInterface::Status_NotSent;
        $shopEventSettings = $this->config->getShopEventSettings();
        if ($shopEventSettings['triggerInvoiceSendEvent'] == Config::TriggerInvoiceSendEvent_InvoiceCreate) {
            $result = $this->send($invoiceSource, false);
        }
        return $result;
    }

    /**
     * Creates and sends an invoice to Acumulus for an order.
     *
     * @param \Siel\Acumulus\Invoice\Source $invoiceSource
     *   The source object (order, credit note) for which the invoice was created.
     * @param bool $forceSend
     *   force sending the invoice even if an invoice has already been sent for
     *   the given order.
     *
     * @return int
     *   Status.
     */
    public function send(Source $invoiceSource, $forceSend = false)
    {
        $result = WebConfigInterface::Status_NotSent;
        if ($forceSend || !$this->config->getAcumulusEntryModel()->getByInvoiceSource($invoiceSource)) {
            $invoice = $this->config->getCreator()->create($invoiceSource);

            $this->triggerInvoiceCreated($invoice, $invoiceSource);

            if ($invoice !== null) {
                $localMessages = array();
                $invoice = $this->config->getCompletor()->complete($invoice, $invoiceSource, $localMessages);

                $this->triggerInvoiceCompleted($invoice, $invoiceSource);

                if ($invoice !== null) {
                    $service = $this->config->getService();
                    $result = $service->invoiceAdd($invoice);
                    $result = $service->mergeLocalMessages($result, $localMessages);

                    $this->triggerInvoiceSent($invoice, $invoiceSource, $result);

                    // Check if an entryid was created and store entry id and token.
                    if (!empty($result['invoice']['entryid'])) {
                        $this->config->getAcumulusEntryModel()->save($invoiceSource, $result['invoice']['entryid'], $result['invoice']['token']);
                    }

                    // Send a mail if there are messages.
                    $messages = $service->resultToMessages($result);
                    if (!empty($messages)) {
                        $this->config->getLog()->notice('InvoiceManager::send(%s %d, %s) result: %s',
                            $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false', $service->messagesToText($messages));
                        $this->mailInvoiceAddResult($result, $messages, $invoiceSource);
                    }

                    $result = $result['status'];
                } else {
                    $result = WebConfigInterface::Status_SendingPrevented_InvoiceCompleted;
                    $this->config->getLog()->notice('InvoiceManager::send(%s %d, %s): invoiceCompleted prevented sending',
                        $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false');
                }
            } else {
                $result = WebConfigInterface::Status_SendingPrevented_InvoiceCreated;
                $this->config->getLog()->notice('InvoiceManager::send(%s %d, %s): invoiceCreated prevented sending',
                    $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false');
            }
        } else {
            $this->config->getLog()->notice('InvoiceManager::send(%s %d, %s): not sent',
                $invoiceSource->getType(), $invoiceSource->getId(), $forceSend ? 'true' : 'false');
        }
        return $result;
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
